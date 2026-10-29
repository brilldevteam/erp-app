<?php

namespace App\Http\Controllers;

use App\Jobs\InspectZohoMigrationFile;
use App\Jobs\ImportZohoMigrationStage;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\Warehouse;
use App\Rules\SpreadsheetUpload;
use App\Services\ZohoMigration\ZohoStageCatalog;
use App\Services\ZohoMigration\ZohoManualPrerequisiteService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZohoMigrationController extends Controller
{
    public function __construct(private readonly ZohoStageCatalog $stages)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorizeAction($request, 'view-zoho-migrations');

        $batches = ZohoMigrationBatch::where('tenant_id', $this->tenantId($request))
            ->withCount('files')
            ->latest()
            ->get()
            ->map(fn (ZohoMigrationBatch $batch) => $this->payload($batch));

        return Inertia::render('zoho-migrations/index', [
            'batches' => $batches,
            'permissions' => [
                'create' => $request->user()->can('create-zoho-migrations'),
                'upload' => $request->user()->can('upload-zoho-migration-files'),
                'approve' => $request->user()->can('approve-zoho-migrations'),
                'execute' => $request->user()->can('execute-zoho-migrations'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'create-zoho-migrations');
        $tenantId = $this->tenantId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'workflow_mode' => ['required', Rule::in(['full', 'standalone'])],
            'zoho_organization_id' => ['nullable', 'string', 'max:100'],
            'accounting_mode' => ['required', Rule::in(ZohoMigrationBatch::ACCOUNTING_MODES)],
            'base_currency' => ['required', 'string', 'size:3'],
            'source_date_format' => ['required', Rule::in(['dd/MM/yyyy', 'MM/dd/yyyy', 'yyyy-MM-dd', 'dd-MM-yyyy'])],
            'inventory_cutover_at' => ['required', 'date'],
            'default_warehouse_name' => ['required', 'string', 'max:255'],
            'duplicate_strategy' => ['required', Rule::in(ZohoMigrationBatch::DUPLICATE_STRATEGIES)],
            'ai_enabled' => ['sometimes', 'boolean'],
        ]);

        $batch = DB::transaction(function () use ($validated, $tenantId, $request): ZohoMigrationBatch {
            $warehouseName = trim($validated['default_warehouse_name']);
            $warehouse = Warehouse::where('created_by', $tenantId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($warehouseName)])
                ->first();
            if (!$warehouse) {
                abort_unless($request->user()->can('create-warehouses'), 403, 'You need permission to create the default warehouse.');
                $warehouse = Warehouse::create([
                    'name' => $warehouseName,
                    'address' => 'Not provided during migration setup',
                    'city' => 'Not specified',
                    'zip_code' => 'N/A',
                    'is_active' => true,
                    'creator_id' => $request->user()->id,
                    'created_by' => $tenantId,
                ]);
            }

            unset($validated['default_warehouse_name']);
            $batch = ZohoMigrationBatch::create([
                ...$validated,
                'default_warehouse_id' => $warehouse->id,
                'base_currency' => strtoupper($validated['base_currency']),
                'tenant_id' => $tenantId,
                'created_by' => $request->user()->id,
                'status' => 'active',
                'ai_enabled' => (bool) ($validated['ai_enabled'] ?? false),
            ]);

            foreach ($this->stages->all() as $stage) {
                ZohoMigrationStage::create([
                    'batch_id' => $batch->id,
                    'tenant_id' => $tenantId,
                    'stage_key' => $stage['key'],
                    'position' => $stage['position'],
                    'status' => $stage['key'] === 'configuration' ? 'completed' : 'not_started',
                    'completed_at' => $stage['key'] === 'configuration' ? now() : null,
                    'completed_by' => $stage['key'] === 'configuration' ? $request->user()->id : null,
                ]);
            }

            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $tenantId,
                'user_id' => $request->user()->id,
                'stage_key' => 'configuration',
                'event_type' => 'batch_created',
                'message' => 'Migration workspace created and configuration completed.',
            ]);

            return $batch;
        });

        return response()->json($this->payload($batch), 201);
    }

    public function show(Request $request, ZohoMigrationBatch $zohoMigration): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');

        return response()->json($this->payload($zohoMigration->load(['files', 'stages', 'issues', 'events'])));
    }

    public function upload(Request $request, ZohoMigrationBatch $zohoMigration): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'upload-zoho-migration-files');
        abort_if($zohoMigration->configuration_locked_at, 409, 'This migration configuration is locked.');

        $validated = $request->validate([
            'stage_key' => ['required', 'string', 'max:80'],
            'file' => [
                'required', 'file', 'max:10240', new SpreadsheetUpload(),
            ],
        ]);

        $stage = $this->stages->find($validated['stage_key']);
        abort_unless($stage, 422, 'Unknown migration stage.');
        abort_unless($stage['supported'], 422, 'This Zoho dataset is not yet supported.');
        abort_if($stage['key'] === 'configuration', 422, 'Configuration does not accept a source file.');
        $this->ensureStageIsAvailable($zohoMigration, $stage);
        abort_if(
            $zohoMigration->stages()->where('stage_key', $stage['key'])->where('status', 'completed')->exists(),
            409,
            'This stage is already imported and cannot have its source replaced.'
        );

        $uploaded = $validated['file'];
        $sha256 = hash_file('sha256', $uploaded->getRealPath());
        if ($zohoMigration->files()->where('sha256', $sha256)->exists()) {
            return response()->json(['message' => 'This exact source file is already attached to the migration.'], 422);
        }

        $directory = "zoho-migrations/{$zohoMigration->tenant_id}/{$zohoMigration->id}/".Str::uuid();
        $path = $uploaded->storeAs($directory, 'source.'.strtolower($uploaded->getClientOriginalExtension()), 'local');

        try {
            $file = ZohoMigrationFile::create([
                'batch_id' => $zohoMigration->id,
                'tenant_id' => $zohoMigration->tenant_id,
                'stage_key' => $stage['key'],
                'uploaded_by' => $request->user()->id,
                'original_filename' => $uploaded->getClientOriginalName(),
                'disk' => 'local',
                'path' => $path,
                'sha256' => $sha256,
                'size_bytes' => $uploaded->getSize(),
                'mime_type' => $uploaded->getMimeType(),
                'status' => 'uploaded',
            ]);
        } catch (QueryException $exception) {
            Storage::disk('local')->delete($path);
            if ($zohoMigration->files()->where('sha256', $sha256)->exists()) {
                return response()->json(['message' => 'This exact source file is already attached to the migration.'], 422);
            }

            throw $exception;
        }

        $zohoMigration->files()
            ->where('stage_key', $stage['key'])
            ->whereKeyNot($file->id)
            ->whereIn('status', ['uploaded', 'detecting', 'staged', 'review_required'])
            ->update(['status' => 'superseded']);

        InspectZohoMigrationFile::dispatch($file->id);
        $zohoMigration->stages()->where('stage_key', $stage['key'])->update(['status' => 'processing']);

        ZohoMigrationEvent::create([
            'batch_id' => $zohoMigration->id,
            'tenant_id' => $zohoMigration->tenant_id,
            'user_id' => $request->user()->id,
            'stage_key' => $stage['key'],
            'event_type' => 'file_uploaded',
            'message' => $zohoMigration->files()->where('stage_key', $stage['key'])->where('status', 'superseded')->exists()
                ? 'Previous unimported source replaced; new file uploaded for inspection.'
                : 'Source file uploaded for inspection.',
            'metadata' => ['file_id' => $file->id, 'filename' => $file->original_filename],
        ]);

        return response()->json($file, 202);
    }

    public function validateStage(Request $request, ZohoMigrationBatch $zohoMigration, string $stageKey, ZohoMigrationValidator $validator): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'upload-zoho-migration-files');
        $stage = $this->stages->find($stageKey);
        abort_unless($stage && $stage['supported'], 422, 'This migration stage cannot be validated.');
        $this->ensureStageIsAvailable($zohoMigration, $stage);

        return response()->json($validator->validate($zohoMigration, $stageKey, $request->user()->id));
    }

    public function executeStage(Request $request, ZohoMigrationBatch $zohoMigration, string $stageKey): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'execute-zoho-migrations');
        $stage = $this->stages->find($stageKey);
        abort_unless($stage && $stageKey === 'chart_of_accounts', 422, 'This migration stage is not executable yet.');
        $state = $zohoMigration->stages()->where('stage_key', $stageKey)->firstOrFail();
        abort_unless($state->status === 'ready' && $state->rejected_count === 0, 409, 'Resolve validation issues before importing.');

        $state->update(['status' => 'processing']);
        ImportZohoMigrationStage::dispatch($zohoMigration->id, $stageKey, $request->user()->id);

        return response()->json(['message' => 'Import started.'], 202);
    }

    public function importManualPrerequisite(
        Request $request,
        ZohoMigrationBatch $zohoMigration,
        string $stageKey,
        ZohoManualPrerequisiteService $manualPrerequisites
    ): JsonResponse {
        $this->authorizeBatch($request, $zohoMigration, 'upload-zoho-migration-files');
        abort_if($zohoMigration->configuration_locked_at, 409, 'This migration configuration is locked.');
        $stage = $this->stages->find($stageKey);
        abort_unless($stage && ($stage['manual_entry'] ?? false), 422, 'This stage does not accept pasted prerequisite data.');
        $this->ensureStageIsAvailable($zohoMigration, $stage);

        $validated = $request->validate([
            'pasted_data' => ['required', 'string', 'max:2000000'],
            'accuracy_confirmed' => ['accepted'],
        ], [
            'accuracy_confirmed.accepted' => 'Confirm that every pasted value has been checked. Later document imports depend on this data.',
        ]);

        return response()->json($manualPrerequisites->import(
            $zohoMigration,
            $stageKey,
            $validated['pasted_data'],
            $request->user()->id
        ));
    }

    public function source(Request $request, ZohoMigrationBatch $zohoMigration, ZohoMigrationFile $file): StreamedResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');
        abort_unless($file->batch_id === $zohoMigration->id && $file->tenant_id === $zohoMigration->tenant_id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_filename);
    }

    public function issueReport(Request $request, ZohoMigrationBatch $zohoMigration, string $stageKey): StreamedResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');
        abort_unless($this->stages->find($stageKey), 404);
        $issues = $zohoMigration->issues()->where('stage_key', $stageKey)->with(['file:id,original_filename', 'stagedRow:id,source_row_number'])->get();

        return response()->streamDownload(function () use ($issues): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Source file', 'Source row', 'Type', 'Field', 'Source value', 'Message', 'Status']);
            foreach ($issues as $issue) {
                fputcsv($output, array_map([$this, 'safeCsv'], [
                    $issue->file?->original_filename,
                    $issue->stagedRow?->source_row_number,
                    $issue->type,
                    $issue->field,
                    $issue->source_value,
                    $issue->message,
                    $issue->status,
                ]));
            }
            fclose($output);
        }, "zoho-{$stageKey}-issues.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function payload(ZohoMigrationBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'workflow_mode' => $batch->workflow_mode,
            'accounting_mode' => $batch->accounting_mode,
            'base_currency' => $batch->base_currency,
            'source_date_format' => $batch->source_date_format,
            'inventory_cutover_at' => $batch->inventory_cutover_at?->toIso8601String(),
            'default_warehouse_id' => $batch->default_warehouse_id,
            'duplicate_strategy' => $batch->duplicate_strategy,
            'ai_enabled' => $batch->ai_enabled,
            'configuration_locked_at' => $batch->configuration_locked_at?->toIso8601String(),
            'files_count' => $batch->files_count ?? $batch->files?->count(),
            'files' => $batch->relationLoaded('files')
                ? $batch->files->map(fn (ZohoMigrationFile $file) => $this->filePayload($file))->values()
                : null,
            'stages' => $this->stagePayloads($batch),
            'issues' => $batch->relationLoaded('issues') ? $batch->issues : null,
            'events' => $batch->relationLoaded('events') ? $batch->events->take(30)->values() : null,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    private function stagePayloads(ZohoMigrationBatch $batch): array
    {
        $states = $batch->relationLoaded('stages')
            ? $batch->stages->keyBy('stage_key')
            : $batch->stages()->get()->keyBy('stage_key');
        $files = $batch->relationLoaded('files')
            ? $batch->files->groupBy('stage_key')
            : $batch->files()->get()->groupBy('stage_key');
        $previousCompleted = true;

        return array_map(function (array $definition) use ($batch, $states, $files, &$previousCompleted): array {
            $state = $states->get($definition['key']);
            $locked = $batch->workflow_mode === 'full' && !$previousCompleted;
            $status = $state?->status ?? ($definition['key'] === 'configuration' ? 'completed' : 'not_started');
            $destinationUrl = $definition['destination'] && Route::has($definition['destination'])
                ? route($definition['destination'])
                : null;
            $payload = $definition + [
                'status' => $locked ? 'locked' : ($definition['supported'] ? $status : 'unsupported'),
                'locked' => $locked,
                'accepted_count' => $state?->accepted_count ?? 0,
                'rejected_count' => $state?->rejected_count ?? 0,
                'warning_count' => $state?->warning_count ?? 0,
                'completed_at' => $state?->completed_at?->toIso8601String(),
                'summary' => $state?->summary,
                'files' => ($files->get($definition['key']) ?? collect())
                    ->map(fn (ZohoMigrationFile $file) => $this->filePayload($file))
                    ->values(),
                'destination_url' => $destinationUrl,
            ];
            $previousCompleted = !$definition['supported'] || $status === 'completed';

            return $payload;
        }, $this->stages->all());
    }

    private function filePayload(ZohoMigrationFile $file): array
    {
        return [
            'id' => $file->id,
            'stage_key' => $file->stage_key,
            'original_filename' => $file->original_filename,
            'size_bytes' => $file->size_bytes,
            'status' => $file->status,
            'dataset_type' => $file->dataset_type,
            'profile_key' => $file->profile_key,
            'detection_confidence' => $file->detection_confidence,
            'headers' => $file->headers,
            'row_count' => $file->row_count,
            'failure_message' => $file->failure_message,
            'preview_rows' => ($file->status === 'staged' || str_starts_with((string) $file->profile_key, 'manual.'))
                ? $file->stagedRows()->orderBy('source_row_number')->limit(5)->get(['source_row_number', 'original_values'])
                : [],
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function ensureStageIsAvailable(ZohoMigrationBatch $batch, array $stage): void
    {
        if ($batch->workflow_mode !== 'full' || !$stage['previous_key']) {
            return;
        }

        $previous = $batch->stages()->where('stage_key', $stage['previous_key'])->first();
        abort_unless($previous?->status === 'completed', 409, 'Complete the previous migration stage first.');
    }

    private function authorizeBatch(Request $request, ZohoMigrationBatch $batch, string $permission): void
    {
        abort_unless($batch->tenant_id === $this->tenantId($request), 404);
        $this->authorizeAction($request, $permission);
    }

    private function authorizeAction(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403);
    }

    private function tenantId(Request $request): int
    {
        return $request->user()->type === 'company'
            ? $request->user()->id
            : (int) $request->user()->created_by;
    }

    private function safeCsv(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@]/', $text) ? "'{$text}" : $text;
    }
}

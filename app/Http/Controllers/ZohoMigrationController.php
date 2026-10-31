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
use App\Services\ZohoMigration\ZohoExportTemplate;
use App\Services\ZohoMigration\ZohoUploadGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;

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
            'duplicate_strategy' => ['required', Rule::in(ZohoMigrationBatch::DUPLICATE_STRATEGIES)],
            'ai_enabled' => ['sometimes', 'boolean'],
        ]);

        // The migration's base currency becomes the company's currency. A company that already has accounting
        // entries keeps its currency: its migration must use that currency instead.
        $baseCurrency = strtoupper($validated['base_currency']);
        $companyCurrency = strtoupper((string) company_setting('defaultCurrency', $tenantId));
        if ($companyCurrency !== $baseCurrency && DB::table('journal_entries')->where('created_by', $tenantId)->exists()) {
            throw ValidationException::withMessages([
                'base_currency' => "This company already has accounting entries in {$companyCurrency}. Use {$companyCurrency} as the base currency.",
            ]);
        }

        $batch = DB::transaction(function () use ($validated, $tenantId, $request, $baseCurrency, $companyCurrency): ZohoMigrationBatch {
            if ($companyCurrency !== $baseCurrency) {
                $currency = collect(config('default_currency.currencies', []))->firstWhere('code', $baseCurrency);
                setSetting('defaultCurrency', $baseCurrency, $tenantId);
                setSetting('currencySymbol', $currency['symbol'] ?? $baseCurrency, $tenantId);
                setSetting('currencyName', $currency['name'] ?? $baseCurrency, $tenantId);
            }

            $batch = ZohoMigrationBatch::create([
                ...$validated,
                'default_warehouse_id' => null,
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

    public function referenceData(Request $request, ZohoMigrationBatch $zohoMigration): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');
        $tenantId = $zohoMigration->tenant_id;

        return response()->json([
            'categories' => ProductServiceCategory::where('created_by', $tenantId)->orderBy('name')->pluck('name'),
            'units' => ProductServiceUnit::where('created_by', $tenantId)->orderBy('unit_name')->pluck('unit_name'),
            'taxes' => ProductServiceTax::where('created_by', $tenantId)->orderBy('tax_name')->get(['tax_name', 'rate']),
            // Warehouse stock step: services hold no stock.
            'stock_items' => ProductServiceItem::where('created_by', $tenantId)->where('type', '!=', 'service')->orderBy('name')->get(['sku', 'name']),
            'warehouses' => Warehouse::where('created_by', $tenantId)->orderBy('name')->pluck('name'),
            'stock' => WarehouseStock::query()
                ->join('product_service_items as i', 'i.id', '=', 'warehouse_stocks.product_id')
                ->join('warehouses as w', 'w.id', '=', 'warehouse_stocks.warehouse_id')
                ->where('i.created_by', $tenantId)->where('w.created_by', $tenantId)
                ->get(['i.sku', 'w.name as warehouse', 'warehouse_stocks.quantity']),
        ]);
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
        // A top-up stage takes a newer full export after its import; rows imported earlier are skipped.
        abort_if(
            !($stage['top_up'] ?? false) && $zohoMigration->stages()->where('stage_key', $stage['key'])->where('status', 'completed')->exists(),
            409,
            'This stage is already imported and cannot have its source replaced.'
        );

        $uploaded = $validated['file'];
        $sha256 = hash_file('sha256', $uploaded->getRealPath());
        // The same export may feed several stages (e.g. the Chart of Accounts export also feeds bank accounts),
        // so a re-selected file is matched within this stage only.
        $file = $zohoMigration->files()->where('stage_key', $stage['key'])->where('sha256', $sha256)->first();
        $isRetry = $file !== null;

        if ($file) {
            $file->update([
                'uploaded_by' => $request->user()->id,
                'original_filename' => $uploaded->getClientOriginalName(),
                'status' => 'uploaded',
                'failure_message' => null,
            ]);
        } else {
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
                if ($zohoMigration->files()->where('stage_key', $stage['key'])->where('sha256', $sha256)->exists()) {
                    return response()->json(['message' => 'This source file was attached by another request. Select it again to retry.'], 409);
                }

                throw $exception;
            }
        }

        // A stage that takes several kinds of file replaces only the file of the same kind, once inspection knows it.
        if (!($stage['multi_source'] ?? false)) {
            $zohoMigration->files()
                ->where('stage_key', $stage['key'])
                ->whereKeyNot($file->id)
                ->whereIn('status', ['uploaded', 'detecting', 'staged', 'review_required'])
                ->update(['status' => 'superseded']);
        }

        InspectZohoMigrationFile::dispatch($file->id);
        $topUp = $zohoMigration->stages()->where('stage_key', $stage['key'])->where('status', 'completed')->exists();
        $zohoMigration->stages()->where('stage_key', $stage['key'])->update([
            'status' => 'processing',
            'accepted_count' => 0,
            'rejected_count' => 0,
            'warning_count' => 0,
            'summary' => null,
            'completed_at' => null,
        ]);

        ZohoMigrationEvent::create([
            'batch_id' => $zohoMigration->id,
            'tenant_id' => $zohoMigration->tenant_id,
            'user_id' => $request->user()->id,
            'stage_key' => $stage['key'],
            'event_type' => 'file_uploaded',
            'message' => $topUp
                ? 'Newer export uploaded for inspection; records imported earlier will be checked and skipped.'
                : ($isRetry
                ? 'Existing unimported source selected again; inspection restarted.'
                : ($zohoMigration->files()->where('stage_key', $stage['key'])->where('status', 'superseded')->exists()
                    ? 'Previous unimported source replaced; new file uploaded for inspection.'
                    : 'Source file uploaded for inspection.')),
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
        abort_unless($stage && ($stage['executable'] ?? false), 422, 'This migration stage is not executable yet.');
        $this->ensureStageIsAvailable($zohoMigration, $stage);
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
            'pasted_data' => ['nullable', 'string', 'max:2000000', 'required_unless:no_records_used,true'],
            'no_records_used' => ['sometimes', 'boolean'],
            'accuracy_confirmed' => ['accepted'],
        ], [
            'accuracy_confirmed.accepted' => 'Confirm that every pasted value has been checked. Later document imports depend on this data.',
        ]);

        if (($validated['no_records_used'] ?? false) === true) {
            return response()->json($manualPrerequisites->completeWithoutRows($zohoMigration, $stageKey, $request->user()->id));
        }

        return response()->json($manualPrerequisites->import(
            $zohoMigration,
            $stageKey,
            (string) $validated['pasted_data'],
            $request->user()->id
        ));
    }

    public function manualTemplate(
        Request $request,
        ZohoMigrationBatch $zohoMigration,
        string $stageKey,
        ZohoManualPrerequisiteService $manualPrerequisites
    ): BinaryFileResponse {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);
        $path = $manualPrerequisites->uploadTemplate($zohoMigration, $stageKey, $format);

        return response()->download($path, "{$stageKey}-import-template.{$format}")->deleteFileAfterSend();
    }

    public function exportTemplate(Request $request, ZohoMigrationBatch $zohoMigration, string $stageKey, string $template): BinaryFileResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);
        // Only the templates this step's upload guide offers.
        $offered = array_column(ZohoUploadGuide::for($stageKey)['files'] ?? [], 'template');
        abort_unless(in_array($template, $offered, true) && ZohoExportTemplate::exists($template), 404);

        return response()->download(app(ZohoExportTemplate::class)->write($template, $format), "zoho-{$template}-template.{$format}")->deleteFileAfterSend();
    }

    public function uploadManualPrerequisite(
        Request $request,
        ZohoMigrationBatch $zohoMigration,
        string $stageKey,
        ZohoManualPrerequisiteService $manualPrerequisites
    ): JsonResponse {
        $this->authorizeBatch($request, $zohoMigration, 'upload-zoho-migration-files');
        abort_if($zohoMigration->configuration_locked_at, 409, 'This migration configuration is locked.');
        $stage = $this->stages->find($stageKey);
        abort_unless($stage && ($stage['manual_entry'] ?? false), 422, 'This stage does not accept spreadsheet uploads.');
        $this->ensureStageIsAvailable($zohoMigration, $stage);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', new SpreadsheetUpload()],
            'dry_run' => ['required', 'boolean'],
        ]);
        $dryRun = filter_var($validated['dry_run'], FILTER_VALIDATE_BOOLEAN);
        if (!$dryRun) {
            $request->validate(['accuracy_confirmed' => ['accepted']], [
                'accuracy_confirmed.accepted' => 'Confirm that every value in the file has been checked. Later document imports depend on this data.',
            ]);
        }

        // The spreadsheet reader picks its format from the file extension, so keep it on the temporary copy.
        $file = $validated['file'];
        $path = $file->storeAs('zoho-migrations/uploads', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');
        try {
            $absolutePath = Storage::disk('local')->path($path);

            return response()->json($dryRun
                ? $manualPrerequisites->previewUpload($zohoMigration, $stageKey, $absolutePath)
                : $manualPrerequisites->importUpload($zohoMigration, $stageKey, $absolutePath, $file->getClientOriginalName(), $request->user()->id));
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function source(Request $request, ZohoMigrationBatch $zohoMigration, ZohoMigrationFile $file): StreamedResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');
        abort_unless($file->batch_id === $zohoMigration->id && $file->tenant_id === $zohoMigration->tenant_id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_filename);
    }

    /** Removes a file attached by mistake, before it is imported; the step's other files are checked again. */
    public function destroyFile(Request $request, ZohoMigrationBatch $zohoMigration, ZohoMigrationFile $file, ZohoMigrationValidator $validator): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'upload-zoho-migration-files');
        abort_if($zohoMigration->configuration_locked_at, 409, 'This migration configuration is locked.');
        abort_unless($file->batch_id === $zohoMigration->id && $file->tenant_id === $zohoMigration->tenant_id, 404);
        abort_if($file->status === 'superseded', 409, 'This file was already replaced by a newer one.');
        $state = $zohoMigration->stages()->where('stage_key', $file->stage_key)->first();
        abort_if($state?->status === 'completed', 409, 'This step is already imported; its files cannot be removed.');
        abort_if($state?->status === 'processing', 409, 'The file is still being checked or imported. Remove it once that has finished.');
        abort_if($file->stagedRows()->where('status', 'imported')->exists(), 409, 'This file is already imported and cannot be removed.');

        DB::transaction(function () use ($request, $zohoMigration, $file): void {
            ZohoMigrationEvent::create([
                'batch_id' => $zohoMigration->id,
                'tenant_id' => $zohoMigration->tenant_id,
                'user_id' => $request->user()->id,
                'stage_key' => $file->stage_key,
                'event_type' => 'file_removed',
                'message' => "{$file->original_filename} was removed before import.",
                'metadata' => ['file_id' => $file->id, 'filename' => $file->original_filename],
            ]);
            // Staged rows and their issues go with the file.
            $file->delete();
        });
        Storage::disk($file->disk)->delete($file->path);

        $stageKey = (string) $file->stage_key;
        if ($zohoMigration->files()->where('stage_key', $stageKey)->where('status', 'staged')->exists()) {
            $validator->validate($zohoMigration, $stageKey, $request->user()->id);
        } elseif ($imported = $zohoMigration->events()->where('stage_key', $stageKey)->where('event_type', 'stage_imported')->latest('id')->first()) {
            // A newer export of an imported step was removed: the step is back to its completed import.
            $zohoMigration->issues()->where('stage_key', $stageKey)->delete();
            $zohoMigration->stages()->where('stage_key', $stageKey)->update([
                'status' => 'completed', 'completed_at' => $imported->created_at, 'completed_by' => $imported->user_id,
                'summary' => json_encode($imported->metadata), 'rejected_count' => 0,
            ]);
        } else {
            $zohoMigration->issues()->where('stage_key', $stageKey)->delete();
            $zohoMigration->stages()->where('stage_key', $stageKey)->update([
                'status' => 'not_started', 'accepted_count' => 0, 'rejected_count' => 0, 'warning_count' => 0, 'summary' => null,
            ]);
        }

        return response()->json(['message' => 'File removed.']);
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

        // Unsupported stages cannot be completed, so gate on the nearest supported one (matches stagePayloads).
        $previousStage = $this->stages->find($stage['previous_key']);
        while ($previousStage && !$previousStage['supported']) {
            $previousStage = $previousStage['previous_key'] ? $this->stages->find($previousStage['previous_key']) : null;
        }
        if (!$previousStage) {
            return;
        }

        $previous = $batch->stages()->where('stage_key', $previousStage['key'])->first();
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

<?php

namespace App\Http\Controllers;

use App\Jobs\InspectZohoMigrationFile;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Rules\SpreadsheetUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;

class ZohoMigrationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'view-zoho-migrations');

        $batches = ZohoMigrationBatch::where('tenant_id', $this->tenantId($request))
            ->withCount('files')
            ->latest()
            ->paginate(20);

        return response()->json($batches);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAction($request, 'create-zoho-migrations');
        $tenantId = $this->tenantId($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'zoho_organization_id' => ['nullable', 'string', 'max:100'],
            'accounting_mode' => ['required', Rule::in(ZohoMigrationBatch::ACCOUNTING_MODES)],
            'base_currency' => ['required', 'string', 'size:3'],
            'source_date_format' => ['required', Rule::in(['dd/MM/yyyy', 'MM/dd/yyyy', 'yyyy-MM-dd', 'dd-MM-yyyy'])],
            'inventory_cutover_at' => ['required', 'date'],
            'default_warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(fn ($query) => $query->where('created_by', $tenantId)),
            ],
            'duplicate_strategy' => ['required', Rule::in(ZohoMigrationBatch::DUPLICATE_STRATEGIES)],
            'ai_enabled' => ['sometimes', 'boolean'],
        ]);

        $batch = ZohoMigrationBatch::create([
            ...$validated,
            'base_currency' => strtoupper($validated['base_currency']),
            'tenant_id' => $tenantId,
            'created_by' => $request->user()->id,
            'status' => 'draft',
            'ai_enabled' => (bool) ($validated['ai_enabled'] ?? false),
        ]);

        return response()->json($this->payload($batch), 201);
    }

    public function show(Request $request, ZohoMigrationBatch $zohoMigration): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'view-zoho-migrations');

        return response()->json($this->payload($zohoMigration->load('files')));
    }

    public function upload(Request $request, ZohoMigrationBatch $zohoMigration): JsonResponse
    {
        $this->authorizeBatch($request, $zohoMigration, 'upload-zoho-migration-files');
        abort_if($zohoMigration->configuration_locked_at, 409, 'This migration configuration is locked.');

        $validated = $request->validate([
            'file' => [
                'required', 'file', 'max:10240', new SpreadsheetUpload(),
            ],
        ]);

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

        InspectZohoMigrationFile::dispatch($file->id);

        return response()->json($file, 202);
    }

    private function payload(ZohoMigrationBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'name' => $batch->name,
            'status' => $batch->status,
            'accounting_mode' => $batch->accounting_mode,
            'base_currency' => $batch->base_currency,
            'source_date_format' => $batch->source_date_format,
            'inventory_cutover_at' => $batch->inventory_cutover_at?->toIso8601String(),
            'default_warehouse_id' => $batch->default_warehouse_id,
            'duplicate_strategy' => $batch->duplicate_strategy,
            'ai_enabled' => $batch->ai_enabled,
            'configuration_locked_at' => $batch->configuration_locked_at?->toIso8601String(),
            'files_count' => $batch->files_count ?? $batch->files?->count(),
            'files' => $batch->relationLoaded('files') ? $batch->files : null,
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
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
}

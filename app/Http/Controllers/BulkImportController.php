<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkImport;
use App\Jobs\ValidateBulkImport;
use App\Models\BulkImport;
use App\Services\BulkImport\BulkImportRegistry;
use App\Services\BulkImport\SpreadsheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkImportController extends Controller
{
    public function template(
        Request $request,
        string $entity,
        BulkImportRegistry $registry,
        SpreadsheetService $spreadsheets
    ): BinaryFileResponse {
        $definition = $this->authorizeEntity($request, $entity, $registry);
        $format = $request->query('format', 'xlsx');
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);
        $path = $spreadsheets->template($definition, $format);

        return response()
            ->download($path, "{$entity}-import-template.{$format}")
            ->deleteFileAfterSend();
    }

    public function store(Request $request, string $entity, BulkImportRegistry $registry): JsonResponse
    {
        $this->authorizeEntity($request, $entity, $registry);
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                'mimes:csv,txt,xlsx',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!in_array(strtolower($value->getClientOriginalExtension()), ['csv', 'xlsx'], true)) {
                        $fail('The import file must use the .csv or .xlsx extension.');
                    }
                },
            ],
        ]);

        $tenantId = $this->tenantId($request);
        $directory = "bulk-imports/{$tenantId}/".Str::uuid();
        $file = $validated['file'];
        $path = $file->storeAs(
            $directory,
            'source.'.strtolower($file->getClientOriginalExtension()),
            'local'
        );

        $import = BulkImport::create([
            'entity_type' => $entity,
            'status' => 'uploaded',
            'strategy' => 'skip',
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'tenant_id' => $tenantId,
            'creator_id' => $request->user()->id,
        ]);
        ValidateBulkImport::dispatch($import->id);

        return response()->json($this->payload($import), 202);
    }

    public function show(Request $request, BulkImport $bulkImport, BulkImportRegistry $registry): JsonResponse
    {
        $this->authorizeImport($request, $bulkImport, $registry);

        return response()->json($this->payload($bulkImport->fresh(), true));
    }

    public function confirm(
        Request $request,
        BulkImport $bulkImport,
        BulkImportRegistry $registry
    ): JsonResponse {
        $this->authorizeImport($request, $bulkImport, $registry);
        $validated = $request->validate([
            'strategy' => ['required', Rule::in(['skip', 'update'])],
        ]);

        $queued = BulkImport::whereKey($bulkImport->id)
            ->where('status', 'ready')
            ->update(['strategy' => $validated['strategy'], 'status' => 'queued']);
        abort_unless($queued === 1, 409, 'Import is not ready.');

        ProcessBulkImport::dispatch($bulkImport->id);

        return response()->json($this->payload($bulkImport->fresh()), 202);
    }

    public function history(Request $request, string $entity, BulkImportRegistry $registry): JsonResponse
    {
        $this->authorizeEntity($request, $entity, $registry);
        $imports = BulkImport::where('tenant_id', $this->tenantId($request))
            ->where('entity_type', $entity)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($import) => $this->payload($import));

        return response()->json(['data' => $imports]);
    }

    public function errors(
        Request $request,
        BulkImport $bulkImport,
        BulkImportRegistry $registry
    ): StreamedResponse {
        $this->authorizeImport($request, $bulkImport, $registry);
        abort_unless(
            $bulkImport->error_path && Storage::disk('local')->exists($bulkImport->error_path),
            404
        );

        return Storage::disk('local')->download(
            $bulkImport->error_path,
            "{$bulkImport->entity_type}-import-errors.csv"
        );
    }

    private function authorizeEntity(Request $request, string $entity, BulkImportRegistry $registry)
    {
        abort_unless(array_key_exists($entity, $registry->all()), 404);
        $definition = $registry->get($entity);
        abort_unless(
            $request->user()->can($definition->permission())
            && $request->user()->can($definition->createPermission()),
            403
        );

        return $definition;
    }

    private function authorizeImport(
        Request $request,
        BulkImport $import,
        BulkImportRegistry $registry
    ): void {
        abort_unless($import->tenant_id === $this->tenantId($request), 404);
        $this->authorizeEntity($request, $import->entity_type, $registry);
    }

    private function tenantId(Request $request): int
    {
        return $request->user()->type === 'company'
            ? $request->user()->id
            : (int) $request->user()->created_by;
    }

    private function payload(BulkImport $import, bool $withPreview = false): array
    {
        $payload = [
            'id' => $import->id,
            'entity_type' => $import->entity_type,
            'status' => $import->status,
            'strategy' => $import->strategy,
            'original_filename' => $import->original_filename,
            'total_rows' => $import->total_rows,
            'valid_rows' => $import->valid_rows,
            'invalid_rows' => $import->invalid_rows,
            'duplicate_rows' => $import->duplicate_rows,
            'new_rows' => $import->new_rows,
            'processed_rows' => $import->processed_rows,
            'imported_rows' => $import->imported_rows,
            'updated_rows' => $import->updated_rows,
            'skipped_rows' => $import->skipped_rows,
            'failure_message' => $import->failure_message,
            'has_errors' => (bool) $import->error_path,
            'created_at' => $import->created_at?->toIso8601String(),
        ];

        if ($withPreview && $import->preview_path
            && Storage::disk('local')->exists($import->preview_path)) {
            $rows = json_decode(Storage::disk('local')->get($import->preview_path), true) ?: [];
            $payload['preview'] = array_slice($rows, 0, 20);
        }

        return $payload;
    }
}

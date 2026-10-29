<?php

namespace App\Services\ZohoMigration;

use App\Models\Warehouse;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationIssue;
use App\Models\ZohoMigrationStagedRow;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\WarehouseStock;

class ZohoManualPrerequisiteService
{
    private const DEFINITIONS = [
        'items' => [
            'dataset' => 'items',
            'profile' => 'manual.items.v1',
            'filename' => 'manual-products-services.csv',
            'headers' => ['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price', 'description'],
        ],
        'inventory_cutover' => [
            'dataset' => 'inventory_snapshot',
            'profile' => 'manual.inventory_snapshot.v1',
            'filename' => 'manual-warehouse-stock.csv',
            'headers' => ['sku', 'warehouse', 'quantity'],
        ],
    ];

    public function __construct(private readonly ProductServiceDefinition $products)
    {
    }

    public function import(ZohoMigrationBatch $batch, string $stageKey, string $pastedData, int $actorId): array
    {
        $definition = self::DEFINITIONS[$stageKey] ?? null;
        if (!$definition) {
            throw ValidationException::withMessages(['stage_key' => 'This stage does not accept manual prerequisite data.']);
        }

        [$headers, $rows] = $this->parse($pastedData);
        if ($headers !== $definition['headers']) {
            throw ValidationException::withMessages([
                'pasted_data' => 'The pasted header row must exactly match: '.implode("\t", $definition['headers']),
            ]);
        }
        if (!$rows) {
            throw ValidationException::withMessages(['pasted_data' => 'Paste at least one data row below the header row.']);
        }

        $prepared = $stageKey === 'items'
            ? $this->prepareProducts($rows, $batch)
            : $this->prepareStock($rows, $batch);
        $errors = array_filter($prepared, fn (array $row) => $row['errors']);

        if ($errors) {
            return $this->storeReview($batch, $stageKey, $definition, $headers, $prepared, $actorId);
        }

        return $this->storeAndImport($batch, $stageKey, $definition, $headers, $prepared, $actorId);
    }

    public function template(string $stageKey): ?array
    {
        $definition = self::DEFINITIONS[$stageKey] ?? null;

        return $definition ? ['headers' => $definition['headers']] : null;
    }

    private function parse(string $pastedData): array
    {
        $pastedData = trim(str_replace(["\r\n", "\r"], "\n", $pastedData));
        if ($pastedData === '') {
            throw ValidationException::withMessages(['pasted_data' => 'Paste the copied spreadsheet rows before importing.']);
        }
        if (strlen($pastedData) > 2_000_000) {
            throw ValidationException::withMessages(['pasted_data' => 'The pasted data is too large. Import no more than 10,000 rows at once.']);
        }

        $lines = array_values(array_filter(explode("\n", $pastedData), fn (string $line) => trim($line) !== ''));
        if (count($lines) > 10_001) {
            throw ValidationException::withMessages(['pasted_data' => 'Import no more than 10,000 rows at once.']);
        }
        $delimiter = str_contains($lines[0], "\t") ? "\t" : ',';
        $headers = array_map([$this, 'normalizeHeader'], str_getcsv(array_shift($lines), $delimiter));
        $rows = [];

        foreach ($lines as $index => $line) {
            $values = str_getcsv($line, $delimiter);
            if (count($values) !== count($headers)) {
                throw ValidationException::withMessages([
                    'pasted_data' => 'Row '.($index + 2).' has '.count($values).' columns; '.count($headers).' columns are required. Copy complete rows without moving columns.',
                ]);
            }
            $rows[] = [
                'source_row_number' => $index + 2,
                'values' => array_combine($headers, array_map(fn ($value) => trim((string) $value), $values)),
            ];
        }

        return [$headers, $rows];
    }

    private function prepareProducts(array $rows, ZohoMigrationBatch $batch): array
    {
        $seen = [];

        return array_map(function (array $row) use ($batch, &$seen): array {
            $original = $row['values'];
            $values = $this->products->prepare($row['values']);
            $sku = mb_strtolower(trim((string) ($values['sku'] ?? '')));
            $errors = $this->products->validate($values, $batch->tenant_id);
            foreach (['type', 'category', 'unit', 'sale_price', 'purchase_price'] as $requiredField) {
                if (trim((string) ($original[$requiredField] ?? '')) === '') {
                    $errors[] = ucfirst(str_replace('_', ' ', $requiredField)).' is required for the manual product master.';
                }
            }
            if ($sku !== '' && isset($seen[$sku])) {
                $errors[] = "SKU is duplicated in pasted rows {$seen[$sku]} and {$row['source_row_number']}.";
            }
            if ($sku !== '') {
                $seen[$sku] = $row['source_row_number'];
            }
            if ($sku !== '' && $batch->duplicate_strategy === 'block' && $this->products->duplicate($values, $batch->tenant_id)) {
                $errors[] = 'SKU already exists in ERP and this migration is configured to block duplicates.';
            }

            return $row + ['values' => $values, 'errors' => array_values(array_unique($errors))];
        }, $rows);
    }

    private function prepareStock(array $rows, ZohoMigrationBatch $batch): array
    {
        $seen = [];

        return array_map(function (array $row) use ($batch, &$seen): array {
            $values = $row['values'];
            $errors = [];
            $sku = trim((string) ($values['sku'] ?? ''));
            $warehouseName = trim((string) ($values['warehouse'] ?? ''));
            $quantity = trim((string) ($values['quantity'] ?? ''));
            $item = $sku === '' ? null : ProductServiceItem::where('created_by', $batch->tenant_id)
                ->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])
                ->first();
            $warehouse = $warehouseName === '' ? null : Warehouse::where('created_by', $batch->tenant_id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($warehouseName)])
                ->first();

            if ($sku === '') {
                $errors[] = 'SKU is required.';
            } elseif (!$item) {
                $errors[] = 'SKU was not found in ERP. Complete Products & Services first.';
            } elseif ($item->type === 'service') {
                $errors[] = 'Warehouse stock cannot be entered for a service item.';
            }
            if ($warehouseName === '') {
                $errors[] = 'Warehouse is required.';
            } elseif (!$warehouse) {
                $errors[] = 'Warehouse was not found for this company.';
            }
            if ($quantity === '' || filter_var($quantity, FILTER_VALIDATE_INT) === false || (int) $quantity < 0) {
                $errors[] = 'Quantity must be a whole number of zero or greater.';
            }

            $identity = mb_strtolower($sku).'|'.mb_strtolower($warehouseName);
            if ($sku !== '' && $warehouseName !== '' && isset($seen[$identity])) {
                $errors[] = "SKU and warehouse are duplicated in pasted rows {$seen[$identity]} and {$row['source_row_number']}.";
            }
            $seen[$identity] = $row['source_row_number'];

            return $row + [
                'values' => $values,
                'errors' => array_values(array_unique($errors)),
                'item' => $item,
                'warehouse' => $warehouse,
            ];
        }, $rows);
    }

    private function storeReview(ZohoMigrationBatch $batch, string $stageKey, array $definition, array $headers, array $rows, int $actorId): array
    {
        return $this->persist($batch, $stageKey, $definition, $headers, $rows, $actorId, function (ZohoMigrationFile $file) use ($batch, $stageKey, $rows): array {
            $rejected = 0;
            foreach ($rows as $row) {
                $staged = $this->createStagedRow($batch, $file, $row, $row['errors'] ? 'invalid' : 'validated');
                foreach ($row['errors'] as $message) {
                    $rejected++;
                    ZohoMigrationIssue::create([
                        'batch_id' => $batch->id,
                        'file_id' => $file->id,
                        'staged_row_id' => $staged->id,
                        'tenant_id' => $batch->tenant_id,
                        'stage_key' => $stageKey,
                        'type' => 'manual_prerequisite_validation',
                        'severity' => 'error',
                        'message' => $message,
                        'status' => 'open',
                    ]);
                }
            }

            return ['accepted' => count($rows) - count(array_filter($rows, fn ($row) => $row['errors'])), 'rejected' => count(array_filter($rows, fn ($row) => $row['errors'])), 'warnings' => 0, 'imported' => 0, 'updated' => 0, 'linked' => 0, 'issues' => $rejected];
        }, false);
    }

    private function storeAndImport(ZohoMigrationBatch $batch, string $stageKey, array $definition, array $headers, array $rows, int $actorId): array
    {
        return $this->persist($batch, $stageKey, $definition, $headers, $rows, $actorId, function (ZohoMigrationFile $file) use ($batch, $stageKey, $rows, $actorId): array {
            $summary = ['accepted' => count($rows), 'rejected' => 0, 'warnings' => 0, 'imported' => 0, 'updated' => 0, 'linked' => 0, 'issues' => 0];

            foreach ($rows as $row) {
                if ($stageKey === 'items') {
                    $strategy = $batch->duplicate_strategy === 'link' ? 'skip' : 'update';
                    $result = $this->products->import($row['values'] + ['_mapped_fields' => array_keys($row['values'])], $strategy, $batch->tenant_id, $actorId);
                    $summary[$result === 'skipped' ? 'linked' : $result]++;
                    $status = $result;
                } else {
                    $existing = WarehouseStock::where('product_id', $row['item']->id)->where('warehouse_id', $row['warehouse']->id)->exists();
                    WarehouseStock::updateOrCreate(
                        ['product_id' => $row['item']->id, 'warehouse_id' => $row['warehouse']->id],
                        ['quantity' => (int) $row['values']['quantity']]
                    );
                    $summary[$existing ? 'updated' : 'imported']++;
                    $status = $existing ? 'updated' : 'imported';
                }
                $this->createStagedRow($batch, $file, $row, $status);
            }

            return $summary;
        }, true);
    }

    private function persist(ZohoMigrationBatch $batch, string $stageKey, array $definition, array $headers, array $rows, int $actorId, callable $operation, bool $complete): array
    {
        $csv = $this->csv($headers, array_column($rows, 'values'));
        $path = "zoho-migrations/{$batch->tenant_id}/{$batch->id}/manual/{$stageKey}/".Str::uuid().'.csv';
        Storage::disk('local')->put($path, $csv);
        $oldSources = $batch->files()
            ->where('stage_key', $stageKey)
            ->where('profile_key', $definition['profile'])
            ->get(['disk', 'path'])
            ->map(fn (ZohoMigrationFile $file): array => ['disk' => $file->disk, 'path' => $file->path])
            ->all();

        try {
            $summary = DB::transaction(function () use ($batch, $stageKey, $definition, $headers, $rows, $actorId, $operation, $complete, $path, $csv): array {
                $batch->files()->where('stage_key', $stageKey)->where('profile_key', $definition['profile'])->delete();
                $batch->issues()->where('stage_key', $stageKey)->delete();

                $file = ZohoMigrationFile::create([
                    'batch_id' => $batch->id,
                    'tenant_id' => $batch->tenant_id,
                    'stage_key' => $stageKey,
                    'uploaded_by' => $actorId,
                    'original_filename' => $definition['filename'],
                    'disk' => 'local',
                    'path' => $path,
                    'sha256' => hash('sha256', $csv),
                    'size_bytes' => strlen($csv),
                    'mime_type' => 'text/csv',
                    'status' => $complete ? 'validated' : 'review_required',
                    'dataset_type' => $definition['dataset'],
                    'profile_key' => $definition['profile'],
                    'detection_confidence' => 100,
                    'headers' => $headers,
                    'row_count' => count($rows),
                ]);

                $summary = $operation($file);
                $batch->stages()->where('stage_key', $stageKey)->update([
                    'status' => $complete ? 'completed' : 'review_required',
                    'accepted_count' => $summary['accepted'],
                    'rejected_count' => $summary['rejected'],
                    'warning_count' => $summary['warnings'],
                    'completed_at' => $complete ? now() : null,
                    'completed_by' => $complete ? $actorId : null,
                    'summary' => $summary,
                    'updated_at' => now(),
                ]);

                ZohoMigrationEvent::create([
                    'batch_id' => $batch->id,
                    'tenant_id' => $batch->tenant_id,
                    'user_id' => $actorId,
                    'stage_key' => $stageKey,
                    'event_type' => $complete ? 'manual_prerequisite_imported' : 'manual_prerequisite_rejected',
                    'message' => $complete
                        ? "Confirmed manual prerequisite data imported: {$summary['accepted']} rows accepted."
                        : "Manual prerequisite validation rejected {$summary['rejected']} rows. No records were imported.",
                    'metadata' => $summary,
                ]);

                return $summary;
            });

            foreach ($oldSources as $oldSource) {
                Storage::disk($oldSource['disk'])->delete($oldSource['path']);
            }

            return $summary;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function createStagedRow(ZohoMigrationBatch $batch, ZohoMigrationFile $file, array $row, string $status): ZohoMigrationStagedRow
    {
        return ZohoMigrationStagedRow::create([
            'batch_id' => $batch->id,
            'file_id' => $file->id,
            'tenant_id' => $batch->tenant_id,
            'sheet_name' => 'Manual paste',
            'source_row_number' => $row['source_row_number'],
            'source_external_id' => $row['values']['sku'] ?? null,
            'original_values' => $row['values'],
            'normalized_values' => $row['values'],
            'status' => $status,
            'validation_errors' => $row['errors'] ?: null,
        ]);
    }

    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, array_map(function (string $header) use ($row): string {
                $value = (string) ($row[$header] ?? '');

                return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
            }, $headers));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)->trim()->lower()->replaceMatches('/[\s\-]+/', '_');
    }
}

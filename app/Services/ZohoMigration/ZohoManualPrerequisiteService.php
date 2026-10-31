<?php

namespace App\Services\ZohoMigration;

use App\Models\Warehouse;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationIssue;
use App\Models\ZohoMigrationStagedRow;
use App\Services\BulkImport\Definitions\ProductServiceCategoryDefinition;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use App\Services\BulkImport\Definitions\ProductServiceTaxDefinition;
use App\Services\BulkImport\Definitions\ProductServiceUnitDefinition;
use App\Services\BulkImport\Definitions\WarehouseDefinition;
use App\Services\BulkImport\EntityDefinition;
use App\Services\BulkImport\SpreadsheetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;

class ZohoManualPrerequisiteService
{
    private const DEFINITIONS = [
        'taxes' => [
            'dataset' => 'taxes',
            'profile' => 'manual.taxes.v1',
            'filename' => 'manual-taxes.csv',
            'headers' => ['tax_name', 'rate'],
        ],
        'categories_units' => [
            'dataset' => 'item_categories_units',
            'profile' => 'manual.categories_units.v1',
            'filename' => 'manual-categories-units.csv',
            'headers' => ['record_type', 'name', 'color'],
            'example' => [
                ['category', 'Example Category', '#10B981'],
                ['unit', 'Pcs', ''],
            ],
            'optional_columns' => ['color'],
        ],
        'warehouses' => [
            'dataset' => 'warehouses',
            'profile' => 'manual.warehouses.v1',
            'filename' => 'manual-warehouses.csv',
            'headers' => ['name', 'address', 'city', 'zip_code', 'phone', 'email'],
        ],
        'items' => [
            'dataset' => 'items',
            'profile' => 'manual.items.v1',
            'filename' => 'manual-products-services.csv',
            'headers' => ['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price', 'description'],
            // Spreadsheet uploads: template example row, and columns an uploaded file may leave out.
            'example' => ['Example Product', 'SKU-100', 'product', 'General', 'Pcs', '', '100', '70', 'Short description'],
            'optional_columns' => ['taxes', 'description'],
        ],
        'inventory_cutover' => [
            'dataset' => 'inventory_snapshot',
            'profile' => 'manual.inventory_snapshot.v1',
            'filename' => 'manual-warehouse-stock.csv',
            'headers' => ['sku', 'warehouse', 'quantity'],
        ],
    ];

    public function __construct(
        private readonly ProductServiceDefinition $products,
        private readonly ProductServiceTaxDefinition $taxes,
        private readonly ProductServiceCategoryDefinition $categories,
        private readonly ProductServiceUnitDefinition $units,
        private readonly WarehouseDefinition $warehouses,
        private readonly ZohoManualSpreadsheet $spreadsheets,
    ) {
    }

    public function import(ZohoMigrationBatch $batch, string $stageKey, string $pastedData, int $actorId): array
    {
        $definition = $this->definition($stageKey);
        $this->ensureStageRowExists($batch, $stageKey);

        [$headers, $rows] = $this->parse($pastedData);
        if ($headers !== $definition['headers']) {
            throw ValidationException::withMessages([
                'pasted_data' => 'The pasted header row must exactly match: '.implode("\t", $definition['headers']),
            ]);
        }
        if (!$rows) {
            throw ValidationException::withMessages(['pasted_data' => 'Paste at least one data row below the header row.']);
        }

        return $this->importRows($batch, $stageKey, $definition, $rows, $actorId);
    }

    /**
     * Validates an uploaded spreadsheet without writing anything, returning ERP-import style counts and row results.
     */
    public function previewUpload(ZohoMigrationBatch $batch, string $stageKey, string $path): array
    {
        $definition = $this->definition($stageKey, true);
        $prepared = $this->prepare($stageKey, $this->readUpload($path, $definition), $batch);
        $results = array_map(function (array $row) use ($batch, $stageKey): array {
            $existing = false;
            if (!$row['errors']) {
                $existing = $stageKey === 'items'
                    ? $this->products->duplicate($row['values'], $batch->tenant_id)
                    : ($row['list']['definition'] ?? null)?->duplicate($row['record'] ?? [], $batch->tenant_id);
            }

            return [
                'row_number' => $row['source_row_number'],
                'name' => $row['values']['name'] ?? '',
                'sku' => $row['values']['sku'] ?? '',
                'result' => $row['errors'] ? 'invalid' : ($existing ? 'existing' : 'new'),
                'errors' => $row['errors'],
            ];
        }, $prepared);
        $count = fn (string $result) => count(array_filter($results, fn (array $row) => $row['result'] === $result));

        return [
            'total' => count($results),
            'valid' => count($results) - $count('invalid'),
            'invalid' => $count('invalid'),
            'new' => $count('new'),
            'existing' => $count('existing'),
            'rows' => $results,
        ];
    }

    public function importUpload(ZohoMigrationBatch $batch, string $stageKey, string $path, string $originalFilename, int $actorId): array
    {
        $definition = $this->definition($stageKey, true);
        $this->ensureStageRowExists($batch, $stageKey);
        $rows = $this->readUpload($path, $definition);

        return $this->importRows($batch, $stageKey, ['filename' => $originalFilename] + $definition, $rows, $actorId);
    }

    public function uploadTemplate(ZohoMigrationBatch $batch, string $stageKey, string $format): string
    {
        $definition = $this->definition($stageKey, true);
        if ($stageKey === 'categories_units') {
            return $this->spreadsheets->template($definition['headers'], $definition['example'], [
                'Use record_type category for category rows and unit for unit rows.',
                'name is required. color is optional and applies only to categories.',
                'Delete both example rows before uploading your own data.',
                'Keep the first-row column names. Column order does not matter.',
                'Files may contain at most '.SpreadsheetService::MAX_ROWS.' data rows.',
            ], $format);
        }
        $tenantId = $batch->tenant_id;
        $names = [
            ProductServiceCategory::where('created_by', $tenantId)->orderBy('name')->pluck('name')->all(),
            ProductServiceUnit::where('created_by', $tenantId)->orderBy('unit_name')->pluck('unit_name')->all(),
            ProductServiceTax::where('created_by', $tenantId)->orderBy('tax_name')->pluck('tax_name')->all(),
        ];
        $referenceRows = [['category', 'unit', 'taxes']];
        for ($index = 0; $index < max(array_map('count', $names)); $index++) {
            $referenceRows[] = array_map(fn (array $list) => $list[$index] ?? '', $names);
        }

        return $this->spreadsheets->template($definition['headers'], $definition['example'], [
            'Fill one row per product or service. Delete the example row before uploading.',
            'Keep the column names in the first row; column order does not matter.',
            'type is product, service or part. Services need no unit; products and parts do.',
            'category, unit and taxes must exactly match names already in ERP (see the "ERP names" sheet).',
            'Separate several taxes with commas. Leave taxes empty for no tax.',
            'sale_price and purchase_price are numbers of zero or more.',
            'SKU must be unique. Spreadsheet formulas are not accepted.',
            'Files may contain at most '.SpreadsheetService::MAX_ROWS.' data rows.',
        ], $format, ['ERP names' => $referenceRows]);
    }

    private function definition(string $stageKey, bool $upload = false): array
    {
        $definition = self::DEFINITIONS[$stageKey] ?? null;
        if (!$definition || ($upload && !isset($definition['example']))) {
            throw ValidationException::withMessages(['stage_key' => $upload
                ? 'This stage does not accept spreadsheet uploads.'
                : 'This stage does not accept manual prerequisite data.']);
        }

        return $definition;
    }

    private function prepare(string $stageKey, array $rows, ZohoMigrationBatch $batch): array
    {
        return match ($stageKey) {
            'taxes', 'categories_units', 'warehouses' => $this->prepareSimpleList($rows, $batch, $stageKey),
            'items' => $this->prepareProducts($rows, $batch),
            default => $this->prepareStock($rows, $batch),
        };
    }

    private function importRows(ZohoMigrationBatch $batch, string $stageKey, array $definition, array $rows, int $actorId): array
    {
        $prepared = $this->prepare($stageKey, $rows, $batch);
        if ($stageKey === 'categories_units') {
            $this->ensureCategoriesAndUnitsExist($prepared, $batch);
        }
        $errors = array_filter($prepared, fn (array $row) => $row['errors']);

        if ($errors) {
            return $this->storeReview($batch, $stageKey, $definition, $definition['headers'], $prepared, $actorId);
        }

        return $this->storeAndImport($batch, $stageKey, $definition, $definition['headers'], $prepared, $actorId);
    }

    /**
     * Reads an uploaded CSV/XLSX into rows keyed by the stage's columns. Columns are matched by name (or a
     * known alias) in any order; optional columns may be left out, and extra columns are ignored.
     */
    private function readUpload(string $path, array $definition): array
    {
        try {
            $sheet = $this->spreadsheets->read($path);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $normalize = fn (string $value): string => trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value))), '_');
        $sourceColumns = [];
        foreach ($sheet['headers'] as $column => $label) {
            $sourceColumns[$normalize($label)] ??= $column;
        }
        $aliases = $this->products->aliases();
        $columns = [];
        foreach ($definition['headers'] as $field) {
            foreach ([$field, ...($aliases[$field] ?? [])] as $name) {
                if (isset($sourceColumns[$normalize($name)])) {
                    $columns[$field] = $sourceColumns[$normalize($name)];
                    break;
                }
            }
        }
        $missing = array_values(array_diff($definition['headers'], $definition['optional_columns'] ?? [], array_keys($columns)));
        if ($missing) {
            throw ValidationException::withMessages(['file' => 'The file is missing these columns: '.implode(', ', $missing).'. Download the template to see the expected columns.']);
        }

        $formulaRows = array_column(array_filter($sheet['rows'], fn (array $row) => $row['formula']), 'row_number');
        if ($formulaRows) {
            throw ValidationException::withMessages(['file' => 'Spreadsheet formulas are not accepted (rows '.implode(', ', array_slice($formulaRows, 0, 10)).'). Paste values instead, then upload again.']);
        }

        return array_map(fn (array $row): array => [
            'source_row_number' => $row['row_number'],
            'values' => array_combine($definition['headers'], array_map(
                fn (string $field) => isset($columns[$field]) ? $this->cellText($row['values'][$columns[$field]] ?? null) : '',
                $definition['headers']
            )),
        ], $sheet['rows']);
    }

    private function cellText(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    public function template(string $stageKey): ?array
    {
        $definition = self::DEFINITIONS[$stageKey] ?? null;

        return $definition ? ['headers' => $definition['headers']] : null;
    }

    public function completeWithoutRows(ZohoMigrationBatch $batch, string $stageKey, int $actorId): array
    {
        // Taxes and stock may genuinely be unused; categories and units may already exist in ERP and need no new rows.
        $completion = match ($stageKey) {
            'taxes' => ['flag' => 'no_records_used', 'message' => 'Confirmed that this migration uses no taxes; no tax records were created.'],
            'inventory_cutover' => ['flag' => 'no_records_used', 'message' => 'Confirmed that this company holds no stock (all items are services); no opening stock was recorded.'],
            'categories_units' => ['flag' => 'existing_records_used', 'message' => 'Confirmed the categories and units already in ERP; no new records were created.'],
            'warehouses' => ['flag' => 'existing_records_used', 'message' => 'Confirmed the warehouses already in ERP; no new warehouses were created.'],
            default => null,
        };
        if (!$completion) {
            throw ValidationException::withMessages(['stage_key' => 'This stage cannot be completed without rows.']);
        }
        $this->ensureStageRowExists($batch, $stageKey);
        if ($stageKey === 'categories_units') {
            $this->ensureCategoriesAndUnitsExist([], $batch);
        }
        if ($stageKey === 'inventory_cutover') {
            $stocked = ProductServiceItem::where('created_by', $batch->tenant_id)->where('type', '!=', 'service')->limit(5)->pluck('name');
            if ($stocked->isNotEmpty()) {
                throw ValidationException::withMessages(['pasted_data' => 'ERP has stock-tracked items ('.$stocked->implode(', ').'); enter their opening quantities instead.']);
            }
        }
        if ($stageKey === 'warehouses' && !Warehouse::where('created_by', $batch->tenant_id)->exists()) {
            throw ValidationException::withMessages(['pasted_data' => 'ERP has no warehouse yet. Add at least one warehouse.']);
        }

        $summary = ['accepted' => 0, 'rejected' => 0, 'warnings' => 0, 'imported' => 0, 'updated' => 0, 'linked' => 0, 'issues' => 0, $completion['flag'] => true];
        DB::transaction(function () use ($batch, $stageKey, $actorId, $summary, $completion): void {
            $batch->issues()->where('stage_key', $stageKey)->delete();
            $batch->files()->where('stage_key', $stageKey)->update(['status' => 'superseded']);
            $batch->stages()->where('stage_key', $stageKey)->update([
                'status' => 'completed',
                'accepted_count' => 0,
                'rejected_count' => 0,
                'warning_count' => 0,
                'completed_at' => now(),
                'completed_by' => $actorId,
                'summary' => $summary,
                'updated_at' => now(),
            ]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => $stageKey,
                'event_type' => 'manual_prerequisite_skipped',
                'message' => $completion['message'],
                'metadata' => $summary,
            ]);
        });

        return $summary;
    }

    // Stage status is written with an update; without its row the records would import while the journey never advanced.
    private function ensureStageRowExists(ZohoMigrationBatch $batch, string $stageKey): void
    {
        if (!$batch->stages()->where('stage_key', $stageKey)->exists()) {
            throw ValidationException::withMessages([
                'stage_key' => 'This migration has no record for this step yet, so nothing was imported. Ask an administrator to run the pending database migrations, then try again.',
            ]);
        }
    }

    /**
     * Resolves a pasted row of a simple-list stage to the ERP definition that validates and imports it,
     * plus the row's values in that definition's field names. Returns [null, []] for an unknown record type.
     */
    private function simpleListRecord(string $stageKey, array $values): array
    {
        if ($stageKey === 'taxes') {
            return [['definition' => $this->taxes, 'label' => 'Tax name', 'type' => 'tax'], $values];
        }
        if ($stageKey === 'warehouses') {
            return [['definition' => $this->warehouses, 'label' => 'Warehouse name', 'type' => 'warehouse'], $values];
        }

        return match (strtolower(trim((string) ($values['record_type'] ?? '')))) {
            'category' => [['definition' => $this->categories, 'label' => 'Category name', 'type' => 'category'], ['name' => $values['name'] ?? '', 'color' => $values['color'] ?? '']],
            'unit' => [['definition' => $this->units, 'label' => 'Unit name', 'type' => 'unit'], ['unit_name' => $values['name'] ?? '']],
            default => [null, []],
        };
    }

    private function ensureCategoriesAndUnitsExist(array $rows, ZohoMigrationBatch $batch): void
    {
        $types = array_map(fn (array $row): ?string => $row['list']['type'] ?? null, $rows);
        $missing = [];
        if (!in_array('category', $types, true) && !ProductServiceCategory::where('created_by', $batch->tenant_id)->exists()) {
            $missing[] = 'Add at least one category.';
        }
        if (!in_array('unit', $types, true) && !ProductServiceUnit::where('created_by', $batch->tenant_id)->exists()) {
            $missing[] = 'Add at least one unit.';
        }
        if ($missing) {
            throw ValidationException::withMessages(['pasted_data' => implode(' ', $missing)]);
        }
    }

    private function parse(string $pastedData): array
    {
        // Trim line breaks only: a trailing tab is an empty last cell, not whitespace.
        $pastedData = trim(str_replace(["\r\n", "\r"], "\n", $pastedData), "\n");
        if (trim($pastedData) === '') {
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
                // Matches the ERP item form: services carry no unit.
                if ($requiredField === 'unit' && ($values['type'] ?? null) === 'service') {
                    continue;
                }
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

    private function prepareSimpleList(array $rows, ZohoMigrationBatch $batch, string $stageKey): array
    {
        $seen = [];

        return array_map(function (array $row) use ($batch, &$seen, $stageKey): array {
            [$list, $record] = $this->simpleListRecord($stageKey, $row['values']);
            if (!$list) {
                return $row + ['list' => null, 'record' => [], 'errors' => ['Record type must be category or unit.']];
            }

            /** @var EntityDefinition $definition */
            $definition = $list['definition'];
            $label = $list['label'];
            $record = $definition->prepare($record);
            $identity = $definition->identity($record);
            // The category definition reports a bare "Name"; name the record type so mixed lists stay clear.
            $errors = array_map(fn (string $message) => $message === 'Name is required.' ? "{$label} is required." : $message, $definition->validate($record, $batch->tenant_id));
            $seenKey = $list['type'].'|'.$identity;
            if ($identity !== '' && isset($seen[$seenKey])) {
                $errors[] = "{$label} is duplicated in rows {$seen[$seenKey]} and {$row['source_row_number']}.";
            }
            if ($identity !== '') {
                $seen[$seenKey] = $row['source_row_number'];
            }
            if ($identity !== '' && $batch->duplicate_strategy === 'block' && $definition->duplicate($record, $batch->tenant_id)) {
                $errors[] = "{$label} already exists in ERP and this migration is configured to block duplicates.";
            }

            return $row + ['list' => $list, 'record' => $record, 'errors' => array_values(array_unique($errors))];
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
                if (isset($row['list'])) {
                    $strategy = $batch->duplicate_strategy === 'link' ? 'skip' : 'update';
                    $result = $row['list']['definition']->import($row['record'], $strategy, $batch->tenant_id, $actorId);
                    $summary[$result === 'skipped' ? 'linked' : $result]++;
                    $status = $result;
                } elseif ($stageKey === 'items') {
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
            'source_external_id' => $row['values']['sku'] ?? $row['values']['tax_name'] ?? $row['values']['name'] ?? null,
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

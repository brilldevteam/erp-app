<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\Warehouse;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Http\Request;
use Workdo\ProductService\Events\CreateProductServiceItem;
use Workdo\ProductService\Events\UpdateProductServiceItem;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;

class ProductServiceDefinition implements EntityDefinition
{
    use NormalizesImportValues;

    public function key(): string { return 'product-service-items'; }
    public function permission(): string { return 'import-product-service-items'; }
    public function createPermission(): string { return 'create-product-service-item'; }

    public function headers(): array
    {
        return [
            'name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price',
            'purchase_price', 'description', 'long_description', 'warehouse', 'quantity',
        ];
    }

    public function example(): array
    {
        return [
            'Example Product', 'SKU-100', 'product', 'General', 'Piece', 'VAT 15%',
            '100', '70', 'Short description', 'Long description', 'Main Warehouse', '25',
        ];
    }

    public function instructions(): array
    {
        return [
            'type accepts product, service, or part.',
            'category, unit, taxes, and warehouse must match existing tenant setup names.',
            'Separate multiple tax names with commas, semicolons, or pipes.',
            'warehouse and quantity are optional; service stock values are ignored.',
            'SKU is the duplicate key and is matched without case sensitivity.',
        ];
    }

    public function identity(array $row): string
    {
        return strtolower($this->text($row['sku'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach (['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price'] as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        $type = strtolower($this->text($row['type'] ?? ''));
        if (!in_array($type, ['product', 'service', 'part'], true)) {
            $errors[] = 'Type must be product, service, or part.';
        }

        foreach (['sale_price', 'purchase_price'] as $field) {
            if (!is_numeric($row[$field] ?? null) || (float) $row[$field] < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' must be zero or greater.';
            }
        }

        if (!$this->category($row, $tenantId)) {
            $errors[] = 'Category was not found for this company.';
        }
        if (!$this->unit($row, $tenantId)) {
            $errors[] = 'Unit was not found for this company.';
        }
        foreach ($this->split($row['taxes'] ?? '') as $tax) {
            if (!ProductServiceTax::where('created_by', $tenantId)
                ->whereRaw('LOWER(tax_name) = ?', [strtolower($tax)])->exists()) {
                $errors[] = "Tax '{$tax}' was not found for this company.";
            }
        }

        if (($warehouse = $this->nullableText($row['warehouse'] ?? null))
            && !Warehouse::where('created_by', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($warehouse)])->exists()) {
            $errors[] = 'Warehouse was not found for this company.';
        }

        if ($type !== 'service' && $this->nullableText($row['quantity'] ?? null) !== null
            && (!is_numeric($row['quantity']) || (int) $row['quantity'] < 0)) {
            $errors[] = 'Quantity must be a non-negative integer.';
        }

        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return ProductServiceItem::where('created_by', $tenantId)
            ->whereRaw('LOWER(sku) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $item = ProductServiceItem::where('created_by', $tenantId)
            ->whereRaw('LOWER(sku) = ?', [$this->identity($row)])
            ->first();

        if ($item && $strategy === 'skip') {
            return 'skipped';
        }

        $category = $this->category($row, $tenantId);
        $unit = $this->unit($row, $tenantId);
        $taxNames = array_map('strtolower', $this->split($row['taxes'] ?? ''));
        $taxIds = ProductServiceTax::where('created_by', $tenantId)
            ->get()
            ->filter(fn ($tax) => in_array(strtolower($tax->tax_name), $taxNames, true))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $values = [
            'name' => $this->text($row['name']),
            'sku' => $this->text($row['sku']),
            'type' => strtolower($this->text($row['type'])),
            'category_id' => $category->id,
            'unit' => (string) $unit->id,
            'tax_ids' => $taxIds,
            'sale_price' => (float) $row['sale_price'],
            'purchase_price' => (float) $row['purchase_price'],
            'description' => $this->nullableText($row['description'] ?? null),
            'long_description' => $this->nullableText($row['long_description'] ?? null),
        ];
        $request = new Request($values);

        if ($item) {
            $item->update($values);
            UpdateProductServiceItem::dispatch($request, $item);
            $result = 'updated';
        } else {
            $item = ProductServiceItem::create($values + [
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);
            CreateProductServiceItem::dispatch($request, $item);
            $result = 'imported';
        }

        if ($values['type'] !== 'service' && ($warehouseName = $this->nullableText($row['warehouse'] ?? null))) {
            $warehouse = Warehouse::where('created_by', $tenantId)
                ->whereRaw('LOWER(name) = ?', [strtolower($warehouseName)])
                ->firstOrFail();
            WarehouseStock::updateOrCreate(
                ['product_id' => $item->id, 'warehouse_id' => $warehouse->id],
                ['quantity' => (int) ($row['quantity'] ?? 0)]
            );
        }

        return $result;
    }

    private function category(array $row, int $tenantId): ?ProductServiceCategory
    {
        return ProductServiceCategory::where('created_by', $tenantId)
            ->whereRaw('LOWER(name) = ?', [strtolower($this->text($row['category'] ?? ''))])
            ->first();
    }

    private function unit(array $row, int $tenantId): ?ProductServiceUnit
    {
        return ProductServiceUnit::where('created_by', $tenantId)
            ->whereRaw('LOWER(unit_name) = ?', [strtolower($this->text($row['unit'] ?? ''))])
            ->first();
    }
}

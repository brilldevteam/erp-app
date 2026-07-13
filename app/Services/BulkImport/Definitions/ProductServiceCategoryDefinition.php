<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\EntityDefinition;
use Workdo\ProductService\Models\ProductServiceCategory;

class ProductServiceCategoryDefinition implements EntityDefinition
{
    use NormalizesImportValues;

    public function key(): string { return 'product-service-categories'; }
    public function permission(): string { return 'import-product-service-categories'; }
    public function createPermission(): string { return 'create-product-service-categories'; }
    public function headers(): array { return ['name', 'color']; }
    public function requiredFields(): array { return ['name']; }
    public function aliases(): array { return ['name' => ['category', 'category_name'], 'color' => ['colour', 'hex_color']]; }
    public function example(): array { return ['General', '#10b981']; }
    public function instructions(): array { return ['name is the duplicate key.', 'color is optional and defaults to #10b981.']; }
    public function prepare(array $row): array { $row['name'] = $this->text($row['name'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['name'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        if ($this->identity($row) === '') $errors[] = 'Name is required.';
        $color = $this->nullableText($row['color'] ?? null);
        if ($color && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $errors[] = 'Color must be a hex value like #10b981.';
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return ProductServiceCategory::where('created_by', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $category = ProductServiceCategory::where('created_by', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$this->identity($row)])
            ->first();

        if ($category && $strategy === 'skip') return 'skipped';

        $values = [
            'name' => $this->text($row['name']),
            'color' => $this->nullableText($row['color'] ?? null) ?? '#10b981',
        ];

        if ($category) {
            $category->update($values);
            return 'updated';
        }

        ProductServiceCategory::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }
}

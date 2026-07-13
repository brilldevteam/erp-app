<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\EntityDefinition;
use Workdo\ProductService\Models\ProductServiceUnit;

class ProductServiceUnitDefinition implements EntityDefinition
{
    use NormalizesImportValues;

    public function key(): string { return 'product-service-units'; }
    public function permission(): string { return 'import-product-service-units'; }
    public function createPermission(): string { return 'create-product-service-units'; }
    public function headers(): array { return ['unit_name']; }
    public function requiredFields(): array { return ['unit_name']; }
    public function aliases(): array { return ['unit_name' => ['unit', 'name', 'uom', 'unit of measure']]; }
    public function example(): array { return ['Piece']; }
    public function instructions(): array { return ['unit_name is the duplicate key.']; }
    public function prepare(array $row): array { $row['unit_name'] = $this->text($row['unit_name'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['unit_name'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        return $this->identity($row) === '' ? ['Unit name is required.'] : [];
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return ProductServiceUnit::where('created_by', $tenantId)
            ->whereRaw('LOWER(unit_name) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $unit = ProductServiceUnit::where('created_by', $tenantId)
            ->whereRaw('LOWER(unit_name) = ?', [$this->identity($row)])
            ->first();

        if ($unit && $strategy === 'skip') return 'skipped';

        $values = ['unit_name' => $this->text($row['unit_name'])];
        if ($unit) {
            $unit->update($values);
            return 'updated';
        }

        ProductServiceUnit::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }
}

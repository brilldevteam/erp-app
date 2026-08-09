<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\Warehouse;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\EntityDefinition;

class WarehouseDefinition implements EntityDefinition
{
    use NormalizesImportValues;

    public function key(): string { return 'warehouses'; }
    public function permission(): string { return 'import-warehouses'; }
    public function createPermission(): string { return 'create-warehouses'; }
    public function headers(): array { return ['name', 'address', 'city', 'zip_code', 'phone', 'email', 'is_active']; }
    public function requiredFields(): array { return ['name', 'address', 'city', 'zip_code']; }
    public function aliases(): array { return ['name' => ['warehouse', 'warehouse_name'], 'zip_code' => ['zip', 'postal_code']]; }
    public function example(): array { return ['Main Warehouse', 'Industrial Area', 'Doha', '00000', '+97400000000', 'warehouse@example.com', 'yes']; }
    public function instructions(): array { return ['name is the duplicate key.', 'address, city, and zip_code are required.', 'is_active defaults to yes.']; }
    public function prepare(array $row): array { $row['name'] = $this->text($row['name'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['name'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return Warehouse::where('created_by', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $warehouse = Warehouse::where('created_by', $tenantId)
            ->whereRaw('LOWER(name) = ?', [$this->identity($row)])
            ->first();

        if ($warehouse && $strategy === 'skip') return 'skipped';

        $values = [
            'name' => $this->text($row['name']),
            'address' => $this->text($row['address']),
            'city' => $this->text($row['city']),
            'zip_code' => $this->text($row['zip_code']),
            'phone' => $this->nullableText($row['phone'] ?? null),
            'email' => $this->nullableText($row['email'] ?? null),
            'is_active' => $this->boolean($row['is_active'] ?? true, true),
        ];

        if ($warehouse) {
            $warehouse->update($values);
            return 'updated';
        }

        Warehouse::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }
}

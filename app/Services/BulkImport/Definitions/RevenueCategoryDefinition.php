<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Models\RevenueCategories;

class RevenueCategoryDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'revenue-categories'; }
    public function permission(): string { return 'import-revenue-categories'; }
    public function createPermission(): string { return 'create-revenue-categories'; }
    public function headers(): array { return ['category_name', 'category_code', 'chart_of_account', 'description', 'is_active']; }
    public function requiredFields(): array { return ['category_name', 'category_code']; }
    public function aliases(): array { return ['category_name' => ['name', 'revenue_category'], 'category_code' => ['code'], 'chart_of_account' => ['gl_account', 'account']]; }
    public function example(): array { return ['Product Sales', 'REV-SALES', '4100', 'Revenue from product sales', 'yes']; }
    public function instructions(): array { return ['category_code is the duplicate key.', 'chart_of_account is optional and may be an account code or name.']; }
    public function prepare(array $row): array { $row['category_code'] = $this->text($row['category_code'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['category_code'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        if ($this->nullableText($row['chart_of_account'] ?? null) && !$this->chartAccount($row['chart_of_account'], $tenantId)) {
            $errors[] = 'Chart of account was not found.';
        }
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return (bool) $this->findCategory($row, $tenantId);
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $category = $this->findCategory($row, $tenantId);

        if ($category && $strategy === 'skip') return 'skipped';

        $values = [
            'category_name' => $this->text($row['category_name']),
            'category_code' => $this->text($row['category_code']),
            'gl_account_id' => $this->chartAccount($row['chart_of_account'] ?? null, $tenantId)?->id,
            'description' => $this->nullableText($row['description'] ?? null),
            'is_active' => $this->boolean($row['is_active'] ?? true, true),
        ];

        if ($category) {
            $category->update($values);
            return 'updated';
        }

        RevenueCategories::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }

    private function findCategory(array $row, int $tenantId): ?RevenueCategories
    {
        $code = strtolower($this->text($row['category_code'] ?? ''));
        $name = strtolower($this->text($row['category_name'] ?? ''));

        return RevenueCategories::where('created_by', $tenantId)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(category_code) = ?', [$code])
                ->orWhereRaw('LOWER(category_name) = ?', [$name]))
            ->first();
    }
}

<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Models\AccountCategory;
use Workdo\Account\Models\AccountType;

class AccountTypeDefinition implements EntityDefinition
{
    use NormalizesImportValues;

    public function key(): string { return 'account-types'; }
    public function permission(): string { return 'import-account-types'; }
    public function createPermission(): string { return 'create-account-types'; }
    public function headers(): array { return ['category', 'name', 'code', 'normal_balance', 'description', 'is_active']; }
    public function requiredFields(): array { return ['category', 'name', 'code', 'normal_balance']; }
    public function aliases(): array { return ['category' => ['account_category', 'category_code'], 'name' => ['type_name'], 'code' => ['type_code']]; }
    public function example(): array { return ['assets', 'Current Assets', 'CA', 'debit', 'Short term asset accounts', 'yes']; }
    public function instructions(): array { return ['code is the duplicate key.', 'category accepts Assets, Liabilities, Equity, Revenue, or Expenses. Missing default account categories are created automatically during import.', 'normal_balance accepts debit or credit.']; }
    public function prepare(array $row): array { $row['code'] = $this->text($row['code'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['code'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        if (!$this->accountCategory($row, $tenantId)) $errors[] = 'Account category must be Assets, Liabilities, Equity, Revenue, or Expenses.';
        if (!$this->normalBalance($row['normal_balance'] ?? null)) $errors[] = 'Normal balance must be debit or credit.';
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return (bool) $this->findAccountType($row, $tenantId);
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $type = $this->findAccountType($row, $tenantId);

        if ($type && $strategy === 'skip') return 'skipped';

        $values = [
            'category_id' => $this->accountCategory($row, $tenantId)?->id,
            'name' => $this->text($row['name']),
            'code' => $this->text($row['code']),
            'normal_balance' => $this->normalBalance($row['normal_balance']),
            'description' => $this->nullableText($row['description'] ?? null),
            'is_active' => $this->boolean($row['is_active'] ?? true, true),
            'is_system_type' => false,
        ];

        if ($type) {
            $type->update($values);
            return 'updated';
        }

        AccountType::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }

    private function accountCategory(array $row, int $tenantId): ?AccountCategory
    {
        $this->ensureDefaultAccountCategories($tenantId);

        $value = strtolower($this->text($row['category'] ?? ''));

        return AccountCategory::where(fn ($query) => $query
                ->where('created_by', $tenantId)
                ->orWhereNull('created_by'))
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(name) = ?', [$value])
                ->orWhereRaw('LOWER(code) = ?', [$value])
                ->orWhereRaw('LOWER(type) = ?', [$value]))
            ->first();
    }

    private function ensureDefaultAccountCategories(int $tenantId): void
    {
        foreach ($this->defaultAccountCategories() as $category) {
            AccountCategory::firstOrCreate(
                [
                    'created_by' => $tenantId,
                    'type' => $category['type'],
                ],
                [
                    'name' => $category['name'],
                    'code' => $category['code'],
                    'description' => $category['description'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function defaultAccountCategories(): array
    {
        return [
            ['name' => 'Assets', 'code' => 'ASSETS', 'type' => 'assets', 'description' => 'Asset account category'],
            ['name' => 'Liabilities', 'code' => 'LIABILITIES', 'type' => 'liabilities', 'description' => 'Liability account category'],
            ['name' => 'Equity', 'code' => 'EQUITY', 'type' => 'equity', 'description' => 'Equity account category'],
            ['name' => 'Revenue', 'code' => 'REVENUE', 'type' => 'revenue', 'description' => 'Revenue account category'],
            ['name' => 'Expenses', 'code' => 'EXPENSES', 'type' => 'expenses', 'description' => 'Expense account category'],
        ];
    }

    private function findAccountType(array $row, int $tenantId): ?AccountType
    {
        $code = strtolower($this->text($row['code'] ?? ''));
        $name = strtolower($this->text($row['name'] ?? ''));

        return AccountType::where('created_by', $tenantId)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(code) = ?', [$code])
                ->orWhereRaw('LOWER(name) = ?', [$name]))
            ->first();
    }

    private function normalBalance(mixed $value): ?string
    {
        $value = strtolower($this->text($value));
        if (in_array($value, ['debit', 'dr', '0'], true)) return 'debit';
        if (in_array($value, ['credit', 'cr', '1'], true)) return 'credit';
        return null;
    }
}

<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Models\AccountType;
use Workdo\Account\Models\ChartOfAccount;

class ChartOfAccountDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'chart-of-accounts'; }
    public function permission(): string { return 'import-chart-of-accounts'; }
    public function createPermission(): string { return 'create-chart-of-accounts'; }
    public function headers(): array { return ['account_code', 'account_name', 'account_type', 'parent_account', 'normal_balance', 'opening_balance', 'current_balance', 'description', 'is_active']; }
    public function requiredFields(): array { return ['account_code', 'account_name', 'account_type', 'normal_balance']; }
    public function aliases(): array { return ['account_type' => ['type', 'account_type_code'], 'parent_account' => ['parent', 'parent_account_code']]; }
    public function example(): array { return ['1100', 'Accounts Receivable', 'Current Assets', '', 'debit', '0', '0', 'Customer receivables', 'yes']; }
    public function instructions(): array { return ['account_code is the duplicate key.', 'account_type must match an existing account type name or code.', 'parent_account is optional and may be an account code or name.']; }
    public function prepare(array $row): array { $row['account_code'] = $this->text($row['account_code'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['account_code'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        if (!$this->accountType($row, $tenantId)) $errors[] = 'Account type was not found.';
        if (!$this->normalBalance($row['normal_balance'] ?? null)) $errors[] = 'Normal balance must be debit or credit.';
        if ($this->nullableText($row['parent_account'] ?? null) && !$this->chartAccount($row['parent_account'], $tenantId)) {
            $errors[] = 'Parent account was not found.';
        }
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return ChartOfAccount::where('created_by', $tenantId)
            ->whereRaw('LOWER(account_code) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $account = ChartOfAccount::where('created_by', $tenantId)
            ->whereRaw('LOWER(account_code) = ?', [$this->identity($row)])
            ->first();

        if ($account && $strategy === 'skip') return 'skipped';

        $parent = $this->chartAccount($row['parent_account'] ?? null, $tenantId);
        $values = [
            'account_code' => $this->text($row['account_code']),
            'account_name' => $this->text($row['account_name']),
            'account_type_id' => $this->accountType($row, $tenantId)?->id,
            'parent_account_id' => $parent?->id,
            'level' => $parent ? ((int) ($parent->level ?? 1) + 1) : 1,
            'normal_balance' => $this->normalBalance($row['normal_balance']),
            'opening_balance' => $this->decimal($row['opening_balance'] ?? 0),
            'current_balance' => $this->decimal($row['current_balance'] ?? $row['opening_balance'] ?? 0),
            'description' => $this->nullableText($row['description'] ?? null),
            'is_active' => $this->boolean($row['is_active'] ?? true, true),
            'is_system_account' => false,
        ];

        if ($account) {
            $account->update($values);
            return 'updated';
        }

        ChartOfAccount::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        return 'imported';
    }

    private function accountType(array $row, int $tenantId): ?AccountType
    {
        $value = strtolower($this->text($row['account_type'] ?? ''));
        return AccountType::where('created_by', $tenantId)
            ->where(fn ($query) => $query
                ->whereRaw('LOWER(name) = ?', [$value])
                ->orWhereRaw('LOWER(code) = ?', [$value]))
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

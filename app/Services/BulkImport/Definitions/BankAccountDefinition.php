<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Http\Request;
use Workdo\Account\Events\CreateBankAccount;
use Workdo\Account\Events\UpdateBankAccount;
use Workdo\Account\Models\BankAccount;

class BankAccountDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'bank-accounts'; }
    public function permission(): string { return 'import-bank-accounts'; }
    public function createPermission(): string { return 'create-bank-accounts'; }
    public function headers(): array { return ['account_name', 'account_number', 'bank_name', 'branch_name', 'account_type', 'opening_balance', 'current_balance', 'iban', 'swift_code', 'routing_number', 'gl_account', 'is_active']; }
    public function requiredFields(): array { return ['account_name', 'account_number', 'bank_name']; }
    public function aliases(): array { return ['account_name' => ['name', 'bank account', 'account'], 'account_number' => ['number', 'bank account number'], 'bank_name' => ['bank'], 'gl_account' => ['chart of account', 'account code']]; }
    public function example(): array { return ['Main Bank', '100200300', 'Doha Bank', 'Main', 'checking', '0', '0', 'QA00BANK000000000000', '', '', '1000', 'yes']; }
    public function instructions(): array { return ['account_number is the duplicate key.', 'gl_account may be a chart of account code or name and is optional.']; }
    public function prepare(array $row): array { $row['account_number'] = $this->text($row['account_number'] ?? ''); return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['account_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        }
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return BankAccount::where('created_by', $tenantId)->whereRaw('LOWER(account_number) = ?', [$this->identity($row)])->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $account = BankAccount::where('created_by', $tenantId)->whereRaw('LOWER(account_number) = ?', [$this->identity($row)])->first();
        if ($account && $strategy === 'skip') return 'skipped';
        $values = [
            'account_number' => $this->text($row['account_number']),
            'account_name' => $this->text($row['account_name']),
            'bank_name' => $this->text($row['bank_name']),
            'branch_name' => $this->nullableText($row['branch_name'] ?? null),
            'account_type' => $this->nullableText($row['account_type'] ?? null) ?? 'checking',
            'opening_balance' => $this->decimal($row['opening_balance'] ?? 0),
            'current_balance' => $this->decimal($row['current_balance'] ?? $row['opening_balance'] ?? 0),
            'iban' => $this->nullableText($row['iban'] ?? null),
            'swift_code' => $this->nullableText($row['swift_code'] ?? null),
            'routing_number' => $this->nullableText($row['routing_number'] ?? null),
            'gl_account_id' => $this->chartAccount($row['gl_account'] ?? null, $tenantId)?->id,
            'is_active' => $this->boolean($row['is_active'] ?? true, true),
        ];
        if ($account) {
            $account->update($values);
            UpdateBankAccount::dispatch(new Request($values), $account);
            return 'updated';
        }
        $account = BankAccount::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        CreateBankAccount::dispatch(new Request($values), $account);
        return 'imported';
    }
}

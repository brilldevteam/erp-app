<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Http\Request;
use Workdo\PettyCashManagement\Events\CreatePettyCash;
use Workdo\PettyCashManagement\Events\UpdatePettyCash;
use Workdo\PettyCashManagement\Models\PettyCash;

class PettyCashDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'petty-cashes'; }
    public function permission(): string { return 'import-petty-cashes'; }
    public function createPermission(): string { return 'create-petty-cashes'; }
    public function headers(): array { return ['pettycash_number', 'date', 'opening_balance', 'added_amount', 'total_expense', 'closing_balance', 'status', 'remarks', 'bank_account', 'account_number']; }
    public function requiredFields(): array { return ['pettycash_number', 'date']; }
    public function aliases(): array
    {
        return [
            'pettycash_number' => ['petty cash number', 'number', 'cash number'],
            'date' => ['petty cash date', 'transaction date'],
            'added_amount' => ['amount added', 'added', 'fund amount'],
            'total_expense' => ['expense', 'expenses'],
            'bank_account' => ['bank', 'bank account name'],
            'account_number' => ['bank account number'],
        ];
    }
    public function example(): array { return ['PC-2026-06-003', '2026-06-05', '12030', '500', '0', '12530', 'draft', 'Imported petty cash fund', 'Main Bank', '100200300']; }
    public function instructions(): array
    {
        return [
            'pettycash_number is the duplicate key.',
            'status accepts draft/pending/0 or approved/1. Imported approved rows stay approved.',
            'opening_balance, added_amount, total_expense, and closing_balance are numeric. Blank amounts default to 0, and closing_balance defaults to opening + added - expense.',
            'bank_account/account_number is optional. If provided it must match an existing bank account by name or account number.',
        ];
    }
    public function prepare(array $row): array
    {
        $row['pettycash_number'] = $this->text($row['pettycash_number'] ?? '');
        $row['date'] = $this->dateValue($row['date'] ?? null);

        return $row;
    }
    public function identity(array $row): string { return strtolower($this->text($row['pettycash_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        if ($this->text($row['date'] ?? '') === '') {
            $errors[] = 'Date must be a valid date.';
        }

        if ($this->text($row['bank_account'] ?? '') !== '' || $this->text($row['account_number'] ?? '') !== '') {
            if (!$this->bankAccount($row, $tenantId)) {
                $errors[] = 'Bank account was not found.';
            }
        }

        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return PettyCash::where('created_by', $tenantId)
            ->whereRaw('LOWER(pettycash_number) = ?', [$this->identity($row)])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $pettyCash = PettyCash::where('created_by', $tenantId)
            ->whereRaw('LOWER(pettycash_number) = ?', [$this->identity($row)])
            ->first();

        if ($pettyCash && $strategy === 'skip') {
            return 'skipped';
        }

        $openingBalance = $this->decimal($row['opening_balance'] ?? 0);
        $addedAmount = $this->decimal($row['added_amount'] ?? 0);
        $totalExpense = $this->decimal($row['total_expense'] ?? 0);
        $totalBalance = $this->decimal($row['total_balance'] ?? ($openingBalance + $addedAmount));
        $closingBalance = $this->decimal($row['closing_balance'] ?? ($totalBalance - $totalExpense));
        $bankAccount = $this->bankAccount($row, $tenantId);
        $status = $this->statusValue($row['status'] ?? 0);

        $values = [
            'pettycash_number' => $this->text($row['pettycash_number']),
            'date' => $row['date'],
            'opening_balance' => $openingBalance,
            'added_amount' => $addedAmount,
            'total_balance' => $totalBalance,
            'total_expense' => $totalExpense,
            'closing_balance' => $closingBalance,
            'remarks' => $this->nullableText($row['remarks'] ?? null),
            'status' => $status,
            'bank_account_id' => $bankAccount?->id,
        ];

        if ($pettyCash) {
            $pettyCash->update($values);
            UpdatePettyCash::dispatch(new Request($values), $pettyCash);
            return 'updated';
        }

        $pettyCash = PettyCash::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        CreatePettyCash::dispatch(new Request($values), $pettyCash);

        return 'imported';
    }

    private function statusValue(mixed $value): string
    {
        $status = strtolower($this->text($value));

        return in_array($status, ['1', 'approved', 'approve', 'active'], true) ? '1' : '0';
    }
}

<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Workdo\Account\Events\CreateBankTransfer;
use Workdo\Account\Models\BankTransfer;

class BankTransferDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'bank-transfers'; }
    public function permission(): string { return 'import-bank-transfers'; }
    public function createPermission(): string { return 'create-bank-transfers'; }
    public function headers(): array { return ['transfer_number', 'transfer_date', 'from_bank_account', 'from_account_number', 'to_bank_account', 'to_account_number', 'transfer_amount', 'transfer_charges', 'reference_number', 'description', 'status']; }
    public function requiredFields(): array { return ['transfer_number', 'transfer_date', 'from_bank_account', 'to_bank_account', 'transfer_amount']; }
    public function aliases(): array { return ['transfer_number' => ['transfer no', 'transaction_id'], 'transfer_date' => ['date'], 'from_bank_account' => ['from account', 'source account'], 'to_bank_account' => ['to account', 'destination account'], 'transfer_amount' => ['amount', 'debit', 'credit'], 'reference_number' => ['reference']]; }
    public function example(): array { return ['BT-ZOHO-1001', date('Y-m-d'), 'Credit Card', '3010112', 'Cash In Hand', '1020101', '250', '0', 'Transfer To Credit card', 'Imported transfer from Zoho Books', 'completed']; }
    public function instructions(): array { return ['transfer_number is the duplicate key.', 'From and to bank accounts must already exist in Bank Accounts.', 'Status can be pending, completed, or failed.']; }
    public function prepare(array $row): array { $row['transfer_number'] = $this->text($row['transfer_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'pending')) ?: 'pending'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['transfer_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        if (!$this->dateValue($row['transfer_date'] ?? null)) $errors[] = 'Transfer date is invalid.';
        if (!$this->fromBankAccount($row, $tenantId)) $errors[] = 'From bank account was not found.';
        if (!$this->toBankAccount($row, $tenantId)) $errors[] = 'To bank account was not found.';
        $from = $this->fromBankAccount($row, $tenantId);
        $to = $this->toBankAccount($row, $tenantId);
        if ($from && $to && $from->id === $to->id) $errors[] = 'From and to bank accounts must be different.';
        if ($this->decimal($row['transfer_amount'] ?? 0) <= 0) $errors[] = 'Transfer amount must be greater than zero.';
        if ($this->decimal($row['transfer_charges'] ?? 0) < 0) $errors[] = 'Transfer charges cannot be negative.';
        if (!in_array($row['status'], ['pending', 'completed', 'failed'], true)) $errors[] = 'Status is invalid.';
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return BankTransfer::where('created_by', $tenantId)->whereRaw('LOWER(transfer_number) = ?', [$this->identity($row)])->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $transfer = BankTransfer::where('created_by', $tenantId)->whereRaw('LOWER(transfer_number) = ?', [$this->identity($row)])->first();
        if ($transfer && $strategy === 'skip') return 'skipped';

        $values = [
            'transfer_number' => $this->text($row['transfer_number']),
            'transfer_date' => $this->dateValue($row['transfer_date']),
            'from_account_id' => $this->fromBankAccount($row, $tenantId)->id,
            'to_account_id' => $this->toBankAccount($row, $tenantId)->id,
            'transfer_amount' => $this->decimal($row['transfer_amount']),
            'transfer_charges' => $this->decimal($row['transfer_charges'] ?? 0),
            'reference_number' => $this->nullableText($row['reference_number'] ?? null),
            'description' => $this->text($row['description'] ?? 'Imported bank transfer'),
            'status' => $row['status'],
        ];

        if ($transfer) { $transfer->update($values); return 'updated'; }
        $transfer = BankTransfer::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        CreateBankTransfer::dispatch(request(), $transfer);
        return 'imported';
    }

    private function fromBankAccount(array $row, int $tenantId)
    {
        return $this->bankAccount(['bank_account' => $row['from_bank_account'] ?? '', 'account_number' => $row['from_account_number'] ?? ''], $tenantId);
    }

    private function toBankAccount(array $row, int $tenantId)
    {
        return $this->bankAccount(['bank_account' => $row['to_bank_account'] ?? '', 'account_number' => $row['to_account_number'] ?? ''], $tenantId);
    }
}

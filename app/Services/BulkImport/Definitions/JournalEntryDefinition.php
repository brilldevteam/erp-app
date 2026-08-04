<?php

namespace App\Services\BulkImport\Definitions;

use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\JournalEntry;

class JournalEntryDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'journal-entries'; }
    public function permission(): string { return 'import-journal-entries'; }
    public function createPermission(): string { return 'create-journal-entries'; }
    public function headers(): array { return ['journal_number', 'journal_date', 'reference', 'description', 'debit_account', 'credit_account', 'amount', 'line_description', 'status']; }
    public function requiredFields(): array { return ['journal_number', 'journal_date', 'description', 'debit_account', 'credit_account', 'amount']; }
    public function aliases(): array { return ['journal_number' => ['journal no', 'transaction_id', 'entity_number'], 'journal_date' => ['date'], 'reference' => ['reference_number'], 'debit_account' => ['debit chart account'], 'credit_account' => ['credit chart account'], 'amount' => ['debit', 'credit', 'net_amount']]; }
    public function example(): array { return ['JE-ZOHO-1001', date('Y-m-d'), 'BJV004', 'Imported manual journal from Zoho Books', '1020101', '5300', '423', 'Cash in hand adjustment', 'posted']; }
    public function instructions(): array { return ['journal_number is the duplicate key.', 'Each row creates one balanced journal with one debit account and one credit account.', 'Debit and credit accounts must already exist in Chart Of Accounts.']; }
    public function prepare(array $row): array { $row['journal_number'] = $this->text($row['journal_number'] ?? ''); $row['status'] = strtolower($this->text($row['status'] ?? 'posted')) ?: 'posted'; return $row; }
    public function identity(array $row): string { return strtolower($this->text($row['journal_number'] ?? '')); }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) if ($this->text($row[$field] ?? '') === '') $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
        if (!$this->dateValue($row['journal_date'] ?? null)) $errors[] = 'Journal date is invalid.';
        $debit = $this->chartAccount($row['debit_account'] ?? null, $tenantId);
        $credit = $this->chartAccount($row['credit_account'] ?? null, $tenantId);
        if (!$debit) $errors[] = 'Debit account was not found.';
        if (!$credit) $errors[] = 'Credit account was not found.';
        if ($debit && $credit && $debit->id === $credit->id) $errors[] = 'Debit and credit accounts must be different.';
        if ($this->decimal($row['amount'] ?? 0) <= 0) $errors[] = 'Amount must be greater than zero.';
        if (!in_array($row['status'], ['draft', 'posted'], true)) $errors[] = 'Status is invalid.';
        return $errors;
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        return JournalEntry::where('created_by', $tenantId)->where('entry_type', 'manual')->whereRaw('LOWER(journal_number) = ?', [$this->identity($row)])->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $journal = JournalEntry::where('created_by', $tenantId)->where('entry_type', 'manual')->whereRaw('LOWER(journal_number) = ?', [$this->identity($row)])->first();
        if ($journal && $strategy === 'skip') return 'skipped';

        $amount = round($this->decimal($row['amount']), 2);
        $debitAccount = $this->chartAccount($row['debit_account'] ?? null, $tenantId);
        $creditAccount = $this->chartAccount($row['credit_account'] ?? null, $tenantId);
        $header = [
            'journal_number' => $this->text($row['journal_number']),
            'journal_date' => $this->dateValue($row['journal_date']),
            'entry_type' => 'manual',
            'reference_type' => $this->nullableText($row['reference'] ?? null) ?: 'Manual Journal',
            'reference_id' => null,
            'description' => $this->text($row['description']),
            'total_debit' => $amount,
            'total_credit' => $amount,
            'status' => $row['status'],
        ];
        $lineDescription = $this->nullableText($row['line_description'] ?? null) ?: $this->text($row['description']);

        return DB::transaction(function () use ($journal, $header, $strategy, $tenantId, $actorId, $debitAccount, $creditAccount, $amount, $lineDescription) {
            if ($journal) {
                $journal->update($header);
                $journal->items()->delete();
                $result = 'updated';
            } else {
                $journal = JournalEntry::create($header + ['creator_id' => $actorId, 'created_by' => $tenantId]);
                $result = 'imported';
            }

            $journal->items()->createMany([
                ['account_id' => $debitAccount->id, 'description' => $lineDescription, 'debit_amount' => $amount, 'credit_amount' => 0, 'creator_id' => $actorId, 'created_by' => $tenantId],
                ['account_id' => $creditAccount->id, 'description' => $lineDescription, 'debit_amount' => 0, 'credit_amount' => $amount, 'creator_id' => $actorId, 'created_by' => $tenantId],
            ]);

            return $result;
        });
    }
}

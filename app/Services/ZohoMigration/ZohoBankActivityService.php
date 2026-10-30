<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use App\Services\ZohoMigration\Concerns\ResolvesZohoBankAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransfer;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\RevenueCategories;

/**
 * Bank activity from Zoho's Detailed General Ledger report: the money movements no other step imports.
 *
 * The report has one row per account leg, keyed by Zoho transaction id and Zoho account id; account ids are
 * matched to account codes through the migration's Chart of Accounts export. Only these transaction types are
 * taken, everything else belongs to other steps and is skipped:
 * - transfer_fund between two ERP bank accounts → an ERP bank transfer (journal + both bank register lines);
 * - transfer_fund to or from another account, owner_contribution, expense_refund → a journal with the bank's
 *   register line, since the ERP has no screen for these;
 * - other_income, interest_income → an ERP revenue (category named after the Zoho account, created if missing).
 */
class ZohoBankActivityService
{
    use ReadsZohoDocuments;
    use ResolvesZohoBankAccounts;

    public const TYPES = ['transfer_fund', 'owner_contribution', 'expense_refund', 'other_income', 'interest_income'];

    private const REVENUE_TYPES = ['other_income', 'interest_income'];

    private const LABELS = ['transfer_fund' => 'Transfer', 'owner_contribution' => 'Owner contribution', 'expense_refund' => 'Expense refund', 'other_income' => 'Other income', 'interest_income' => 'Interest income'];

    /** @var array<int, array<string, string>> Zoho account id → account code, per batch. */
    private array $zohoCodes = [];

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoJournalWriter $journals,
    ) {
    }

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] ??= is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);
        $type = mb_strtolower($get('transaction type'));
        $zohoAccountId = $get('account id');

        return [
            'include' => in_array($type, self::TYPES, true),
            'transaction_id' => $get('transaction id'),
            'type' => $type,
            'date' => $this->date($get('date'), (string) $batch->source_date_format),
            'date_source' => $get('date'),
            'zoho_account_id' => $zohoAccountId,
            'account_name' => $get('account name'),
            'account_code' => $this->zohoCodes($batch)[$zohoAccountId] ?? '',
            'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
            'debit' => $amount('debit'),
            'credit' => $amount('credit'),
            'reference' => $get('reference number'),
            'details' => $get('transaction details'),
        ];
    }

    /** Groups the legs of each transaction. */
    public function context(iterable $preparedRows): array
    {
        $transactions = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $transactions[$row['transaction_id']]['rows'][] = $rowNumber;
            $transactions[$row['transaction_id']]['legs'][] = $row;
        }

        return ['transactions' => $transactions];
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $errors = [];
        $transaction = $context['transactions'][$row['transaction_id']] ?? null;

        if ($row['transaction_id'] === '') {
            $errors[] = 'transaction_id is required.';
        }
        if (!$row['date']) {
            $errors[] = "Date '{$row['date_source']}' is not a valid date.";
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency)) {
            $errors[] = 'Foreign-currency bank activity is not supported by this import yet.';
        }
        if (!$this->bankAccount($row, $batch->tenant_id) && !$this->ledger($row, $batch->tenant_id)) {
            $errors[] = $row['account_code'] === ''
                ? "Account '{$row['account_name']}' (Zoho id {$row['zoho_account_id']}) is not in the Chart of Accounts export of this migration. Upload a current Chart of Accounts export in step 2."
                : "Account {$row['account_code']} ({$row['account_name']}) was not found in ERP. Complete the Chart of Accounts step first.";
        }

        // Transaction-level checks are reported once, on its first leg.
        if ($transaction && $transaction['rows'][0] === $sourceRowNumber) {
            $legs = $transaction['legs'];
            $debits = array_values(array_filter($legs, fn (array $leg) => $leg['debit'] > 0));
            $credits = array_values(array_filter($legs, fn (array $leg) => $leg['credit'] > 0));
            if (count($legs) !== 2 || count($debits) !== 1 || count($credits) !== 1) {
                $errors[] = count($legs) === 1
                    ? 'Only one side of this '.mb_strtolower(self::LABELS[$row['type']]).' is in the file. Upload the Detailed General Ledger for all accounts, not a single account\'s transactions.'
                    : 'This '.mb_strtolower(self::LABELS[$row['type']]).' has '.count($legs).' ledger lines; one debit and one credit line are expected.';
            } elseif (abs($debits[0]['debit'] - $credits[0]['credit']) > 0.005) {
                $errors[] = 'Debit '.number_format($debits[0]['debit'], 2).' and credit '.number_format($credits[0]['credit'], 2).' of this transaction differ.';
            } else {
                $banks = array_filter($legs, fn (array $leg) => $this->bankAccount($leg, $batch->tenant_id) !== null);
                if (!$banks) {
                    $errors[] = 'Neither side of this '.mb_strtolower(self::LABELS[$row['type']]).' is an ERP bank account. Complete the Bank accounts step first.';
                } elseif ($row['type'] !== 'transfer_fund' && count($banks) !== 1) {
                    $errors[] = 'This '.mb_strtolower(self::LABELS[$row['type']]).' moves money between two bank accounts; only transfers can.';
                }
                $plan = $this->plan($legs, $batch->tenant_id);
                if ($plan['kind'] === 'revenue' && (!$plan['to_bank'] || !$this->ledger($plan['from'], $batch->tenant_id))) {
                    $errors[] = 'This '.mb_strtolower(self::LABELS[$row['type']]).' does not pay into a bank account from an income account.';
                }
                if ($this->exists($plan, $legs, $batch->tenant_id)) {
                    if ($batch->duplicate_strategy === 'block') {
                        $errors[] = 'This '.mb_strtolower(self::LABELS[$row['type']])." ({$this->number($plan, $legs)}) already exists in ERP and this migration is configured to block duplicates.";
                    } elseif ($batch->duplicate_strategy === 'update') {
                        $errors[] = 'This '.mb_strtolower(self::LABELS[$row['type']])." ({$this->number($plan, $legs)}) already exists in ERP. Imported bank activity is not updated in place because it is already posted; remove it or use the link duplicate setting.";
                    }
                }
            }
        } elseif ($transaction && ($row['type'] !== $transaction['legs'][0]['type'] || $row['date'] !== $transaction['legs'][0]['date'])) {
            $errors[] = "This line's type or date differs from row {$transaction['rows'][0]} of the same transaction.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_bank_activity',
            'field' => 'transaction_id',
            'source_value' => $row['transaction_id'],
            'message' => $message,
            'resolution_url' => Route::has('account.bank-transfers.index') ? route('account.bank-transfers.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'bank_activity')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing bank activity.');
        }

        $rows = $batch->files()->where('stage_key', 'bank_activity')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException('No validated bank activity rows are ready to import.');
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $data = $this->prepare($row->original_values, $batch);
            if (!$data['include']) {
                throw new RuntimeException('Row '.$row->source_row_number.' is no longer bank activity in the file. Upload the file again to re-check it.');
            }
            $prepared[(int) $row->source_row_number] = $data;
        }
        $context = $this->context($prepared);
        $transactions = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $transactions[$data['transaction_id']][] = [$row, $data];
        }
        // Oldest first, so bank running balances read in date order.
        uasort($transactions, fn (array $a, array $b) => [$a[0][1]['date'], $a[0][0]->source_row_number] <=> [$b[0][1]['date'], $b[0][0]->source_row_number]);

        return DB::transaction(function () use ($transactions, $batch, $actorId, $stage): array {
            $counts = ['transactions' => 0, 'amount' => 0.0, 'bank_transfers' => 0, 'bank_journals' => 0, 'revenues' => 0, 'skipped_existing' => 0, 'revenue_categories_created' => 0, 'by_type' => []];

            foreach ($transactions as $lines) {
                $legs = array_column($lines, 1);
                $plan = $this->plan($legs, $batch->tenant_id);
                $type = $legs[0]['type'];
                if ($this->exists($plan, $legs, $batch->tenant_id)) {
                    // Only reachable with the "link" duplicate setting: the existing ERP record is kept as is.
                    $counts['skipped_existing']++;
                    foreach ($lines as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $amount = $plan['amount'];
                match ($plan['kind']) {
                    'transfer' => $this->transfer($plan, $legs, $actorId, $batch->tenant_id),
                    'revenue' => $counts['revenue_categories_created'] += $this->revenue($plan, $legs, $actorId, $batch->tenant_id),
                    'journal' => $this->bankJournal($plan, $legs, $actorId, $batch->tenant_id),
                };
                $counts[['transfer' => 'bank_transfers', 'revenue' => 'revenues', 'journal' => 'bank_journals'][$plan['kind']]]++;
                $counts['by_type'][$type] = ['count' => ($counts['by_type'][$type]['count'] ?? 0) + 1, 'amount' => round(($counts['by_type'][$type]['amount'] ?? 0) + $amount, 2)];

                foreach ($lines as [$row, $data]) {
                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                }
                $counts['transactions']++;
                $counts['amount'] = round($counts['amount'] + $amount, 2);
            }

            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $counts]);
            $parts = array_map(fn (string $type, array $total) => "{$total['count']} ".mb_strtolower(self::LABELS[$type]).($total['count'] === 1 ? '' : 's').' ('.number_format($total['amount'], 2).')', array_keys($counts['by_type']), $counts['by_type']);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'bank_activity',
                'event_type' => 'stage_imported',
                'message' => "Bank activity import completed: {$counts['transactions']} Zoho transactions — ".implode(', ', $parts)
                    ."; {$counts['bank_transfers']} bank transfers, {$counts['bank_journals']} bank journals and {$counts['revenues']} revenues created"
                    .($counts['revenue_categories_created'] ? "; {$counts['revenue_categories_created']} revenue categories created" : '')
                    .($counts['skipped_existing'] ? "; {$counts['skipped_existing']} already in ERP" : '').'.',
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    /**
     * How a balanced two-leg transaction is recorded: bank to bank is a transfer, income is a revenue, everything
     * else a bank journal. Legs are oriented as the side money goes to (debit) and comes from (credit).
     */
    private function plan(array $legs, int $tenantId): array
    {
        $to = collect($legs)->first(fn (array $leg) => $leg['debit'] > 0);
        $from = collect($legs)->first(fn (array $leg) => $leg['credit'] > 0);
        $toBank = $this->bankAccount($to, $tenantId);
        $fromBank = $this->bankAccount($from, $tenantId);

        return [
            'kind' => $toBank && $fromBank && $legs[0]['type'] === 'transfer_fund' ? 'transfer' : (in_array($legs[0]['type'], self::REVENUE_TYPES, true) ? 'revenue' : 'journal'),
            'to' => $to, 'from' => $from, 'to_bank' => $toBank, 'from_bank' => $fromBank,
            'amount' => $to['debit'],
        ];
    }

    private function transfer(array $plan, array $legs, int $actorId, int $tenantId): void
    {
        $header = $legs[0];
        $transfer = BankTransfer::create([
            'transfer_number' => $this->number($plan, $legs),
            'transfer_date' => $header['date'],
            'from_account_id' => $plan['from_bank']->id,
            'to_account_id' => $plan['to_bank']->id,
            'transfer_amount' => $plan['amount'],
            'transfer_charges' => 0,
            'reference_number' => $this->reference($legs),
            'description' => $this->reference($legs) ?: 'Transfer imported from Zoho',
            'status' => 'completed',
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        $journal = $this->journals->post($this->journalHeader($plan, $legs, 'bank_transfer', $transfer->id, 'Bank Transfer #'.$transfer->transfer_number, $tenantId), [
            ['account' => $plan['to_bank']->glAccount, 'debit' => $plan['amount'], 'credit' => 0.0, 'description' => 'Transfer received from '.$plan['from_bank']->account_name],
            ['account' => $plan['from_bank']->glAccount, 'debit' => 0.0, 'credit' => $plan['amount'], 'description' => 'Transfer sent to '.$plan['to_bank']->account_name],
        ], $actorId, $tenantId);
        $transfer->update(['journal_entry_id' => $journal->id]);
        $this->recordBankMovement($plan['from_bank'], 'debit', $header['date'], $transfer->transfer_number, 'Transfer to '.$plan['to_bank']->account_name, $plan['amount'], $tenantId);
        $this->recordBankMovement($plan['to_bank'], 'credit', $header['date'], $transfer->transfer_number, 'Transfer from '.$plan['from_bank']->account_name, $plan['amount'], $tenantId);
    }

    /** Returns the number of revenue categories created (0 or 1). */
    private function revenue(array $plan, array $legs, int $actorId, int $tenantId): int
    {
        $header = $legs[0];
        $account = $this->ledger($plan['from'], $tenantId);
        $created = 0;
        $category = RevenueCategories::where('created_by', $tenantId)->whereRaw('LOWER(category_name) = ?', [mb_strtolower($account->account_name)])->first();
        if (!$category) {
            $category = RevenueCategories::create([
                'category_name' => $account->account_name,
                'category_code' => $account->account_code,
                'description' => 'Created by the Zoho migration for income booked to this account.',
                'is_active' => true,
                'gl_account_id' => $account->id,
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);
            $created = 1;
        }
        $number = $this->number($plan, $legs);
        $revenue = Revenue::create([
            'revenue_number' => $number,
            'revenue_date' => $header['date'],
            'category_id' => $category->id,
            'bank_account_id' => $plan['to_bank']->id,
            'chart_of_account_id' => $account->id,
            'amount' => $plan['amount'],
            'currency_code' => $header['currency_code'],
            'exchange_rate' => 1,
            'base_amount' => $plan['amount'],
            'description' => $this->reference($legs) ?: self::LABELS[$header['type']].' imported from Zoho',
            'reference_number' => $this->reference($legs),
            'status' => 'posted',
            'approved_by' => $actorId,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        $this->journals->post($this->journalHeader($plan, $legs, 'revenue', $revenue->id, 'Revenue Entry - #'.$number, $tenantId), [
            ['account' => $plan['to_bank']->glAccount, 'debit' => $plan['amount'], 'credit' => 0.0, 'description' => 'Revenue received'],
            ['account' => $account, 'debit' => 0.0, 'credit' => $plan['amount'], 'description' => 'Revenue earned'],
        ], $actorId, $tenantId);
        $this->recordBankMovement($plan['to_bank'], 'credit', $header['date'], $number, 'Revenue Posted: '.($this->reference($legs) ?: self::LABELS[$header['type']]), $plan['amount'], $tenantId);

        return $created;
    }

    private function bankJournal(array $plan, array $legs, int $actorId, int $tenantId): void
    {
        $header = $legs[0];
        $label = self::LABELS[$header['type']];
        $toAccount = $plan['to_bank']?->glAccount ?? $this->ledger($plan['to'], $tenantId);
        $fromAccount = $plan['from_bank']?->glAccount ?? $this->ledger($plan['from'], $tenantId);
        $this->journals->post($this->journalHeader($plan, $legs, $header['type'], null, "{$label}: {$plan['from']['account_name']} to {$plan['to']['account_name']}", $tenantId), [
            ['account' => $toAccount, 'debit' => $plan['amount'], 'credit' => 0.0, 'description' => $label.($this->reference($legs) ? ' - '.$this->reference($legs) : '')],
            ['account' => $fromAccount, 'debit' => 0.0, 'credit' => $plan['amount'], 'description' => $label.($this->reference($legs) ? ' - '.$this->reference($legs) : '')],
        ], $actorId, $tenantId);
        $number = $this->number($plan, $legs);
        if ($plan['to_bank']) {
            $this->recordBankMovement($plan['to_bank'], 'credit', $header['date'], $number, "{$label} from {$plan['from']['account_name']}", $plan['amount'], $tenantId);
        }
        if ($plan['from_bank']) {
            $this->recordBankMovement($plan['from_bank'], 'debit', $header['date'], $number, "{$label} to {$plan['to']['account_name']}", $plan['amount'], $tenantId);
        }
    }

    private function journalHeader(array $plan, array $legs, string $referenceType, ?int $referenceId, string $description, int $tenantId): array
    {
        return [
            'journal_number' => $this->journalNumber($legs, $tenantId),
            'journal_date' => $legs[0]['date'],
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description.' (imported from Zoho)',
            'currency_code' => $legs[0]['currency_code'],
            'exchange_rate' => 1,
            'foreign_amount' => $plan['amount'],
        ];
    }

    private function exists(array $plan, array $legs, int $tenantId): bool
    {
        return match ($plan['kind']) {
            'transfer' => BankTransfer::where('created_by', $tenantId)->where('transfer_number', $this->number($plan, $legs))->exists(),
            'revenue' => Revenue::where('created_by', $tenantId)->where('revenue_number', $this->number($plan, $legs))->exists(),
            'journal' => false,
        } || JournalEntry::where('journal_number', $this->journalNumber($legs, $tenantId))->exists();
    }

    /** The ERP record number: Zoho transfers and income have no number of their own, so the Zoho id is used. */
    private function number(array $plan, array $legs): string
    {
        return ['transfer' => 'ZTRF-', 'revenue' => 'ZREV-', 'journal' => 'ZBNK-'][$plan['kind']].$legs[0]['transaction_id'];
    }

    private function journalNumber(array $legs, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $legs[0]['transaction_id'], 'BANK', $legs[0]['date']);
    }

    /** Zoho keeps a transfer's note in the reference number column. */
    private function reference(array $legs): ?string
    {
        return collect($legs)->pluck('reference')->first(fn (string $value) => $value !== '') ?: null;
    }

    /** The ERP chart account for a ledger leg: by the Zoho code its account id maps to, else by a unique name. */
    private function ledger(array $leg, int $tenantId): ?ChartOfAccount
    {
        if ($leg['account_code'] !== '') {
            return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $leg['account_code'])->first();
        }
        $matches = ChartOfAccount::where('created_by', $tenantId)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($leg['account_name'])])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Zoho account id → account code, from the migration's Chart of Accounts export (step 2). */
    private function zohoCodes(ZohoMigrationBatch $batch): array
    {
        return $this->zohoCodes[$batch->id] ??= $batch->files()->where('stage_key', 'chart_of_accounts')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->get(['original_values']))
            ->mapWithKeys(function ($row) {
                $values = [];
                foreach ((array) $row->original_values as $key => $value) {
                    $values[$this->profiles->normalize($key)] = trim((string) $value);
                }

                return [($values['account id'] ?? '') => ($values['account code'] ?? '')];
            })
            ->filter(fn (string $code, string $id) => $id !== '' && $code !== '')
            ->all();
    }
}

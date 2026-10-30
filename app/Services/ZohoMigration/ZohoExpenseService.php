<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use App\Services\ZohoMigration\Concerns\ResolvesZohoBankAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\JournalEntry;

/**
 * Zoho expenses as posted ERP expenses. An ERP expense has one account, so each line of an itemized Zoho expense
 * becomes its own expense, numbered ZEXP-<Zoho entry number> (ZEXP-<entry>-<line> for itemized ones).
 *
 * Each expense posts its own balanced journal — debit the Zoho expense account, credit the paid-through account —
 * and a bank register line, as the ERP's expense posting does. Its category is the ERP expense category named after
 * the Zoho account (step 10 creates them for expense accounts); expenses booked to other Zoho accounts, such as
 * Salary Payable or Staff Advance, get that category created here. Expenses paid through a non-bank Zoho account
 * use an internal clearing bank account linked to that ledger account.
 *
 * The step can be run again with a newer full export after it was imported: expenses imported earlier are checked
 * against the export and skipped, and only the new ones are imported.
 */
class ZohoExpenseService
{
    use ReadsZohoDocuments;
    use ResolvesZohoBankAccounts;

    public const NUMBER_PREFIX = 'ZEXP-';

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

        return [
            'zoho_id' => $get('expense reference id'),
            'entry_number' => $get('entry number'),
            'date' => $this->date($get('expense date'), (string) $batch->source_date_format),
            'date_source' => $get('expense date'),
            'description' => $get('expense description'),
            'reference' => $get('reference#') ?: null,
            'expense_account_name' => $get('expense account'),
            'expense_account_code' => $get('expense account code'),
            // Named for the shared bank-account matching.
            'account_name' => $get('paid through'),
            'account_code' => $get('paid through account code'),
            'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'amount' => $amount('expense amount'),
            'total' => $amount('total'),
            'tax_amount' => $amount('tax amount'),
            'billable' => mb_strtolower($get('is billable')) === 'true',
            'customer' => $get('customer name'),
        ];
    }

    /** Per-expense facts: first row, line numbers and lines, summed line amounts and the shared header. */
    public function context(iterable $preparedRows): array
    {
        $expenses = [];
        $firstRow = null;
        foreach ($preparedRows as $rowNumber => $row) {
            $key = $row['zoho_id'];
            $firstRow ??= $rowNumber;
            $expenses[$key] ??= ['first_row' => $rowNumber, 'rows' => [], 'lines' => [], 'amount' => 0.0, 'header' => $this->header($row)];
            $expenses[$key]['rows'][] = $rowNumber;
            $expenses[$key]['lines'][] = $row;
            $expenses[$key]['amount'] = round($expenses[$key]['amount'] + $row['amount'], 2);
        }

        return ['expenses' => $expenses, 'first_row' => $firstRow];
    }

    /** A line imported by an earlier run of this step; issues() has already checked it still matches the export. */
    public function alreadyImported(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): bool
    {
        $expense = $context['expenses'][$row['zoho_id']] ?? null;
        $index = $expense ? array_search($sourceRowNumber, $expense['rows'], true) : false;

        return $index !== false && $this->importedExpense($row, (int) $index, count($expense['rows']), $batch->tenant_id) !== null;
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $errors = [];
        $expense = $context['expenses'][$row['zoho_id']] ?? null;

        if ($row['zoho_id'] === '') {
            $errors[] = 'Expense Reference ID is required.';
        }
        if ($row['entry_number'] === '') {
            $errors[] = 'Entry Number is required.';
        }
        if (!$row['date']) {
            $errors[] = "Expense Date '{$row['date_source']}' is not a valid date.";
        }
        if ($row['amount'] <= 0) {
            $errors[] = 'Expense Amount must be greater than zero.';
        }
        if ($row['tax_amount'] != 0.0) {
            $errors[] = 'Expenses with tax are not supported by this import yet.';
        }
        if ($row['billable']) {
            $errors[] = 'Billable expenses (re-charged to a customer) are not supported by this import yet.';
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = 'Exchange Rate is required for a foreign-currency expense.';
        }
        if (!$this->expenseAccount($row, $batch->tenant_id)) {
            $errors[] = "Expense Account '{$row['expense_account_name']}' ({$row['expense_account_code']}) was not found in ERP. Complete the Chart of Accounts step first.";
        }
        if (!$this->bankAccount($row, $batch->tenant_id) && !$this->ledgerAccount($row, $batch->tenant_id)) {
            $errors[] = "Paid Through account '{$row['account_name']}' ({$row['account_code']}) is neither a bank account nor a chart account in ERP. Complete the Bank accounts step first.";
        }

        // Expense-level checks are reported once, on the expense's first row.
        if ($expense && $expense['first_row'] === $sourceRowNumber) {
            if (abs($expense['amount'] - $row['total']) > 0.005) {
                $errors[] = 'Line amounts add up to '.number_format($expense['amount'], 2).' but the Zoho expense total is '.number_format($row['total'], 2).'.';
            }
            foreach ($expense['rows'] as $index => $lineRow) {
                $number = $this->number($row, $index, count($expense['rows']));
                if ($imported = $this->importedExpense($row, $index, count($expense['rows']), $batch->tenant_id)) {
                    if ($changes = $this->changes($imported, $expense['lines'][$index], $batch->tenant_id)) {
                        $errors[] = "Expense {$number} was imported earlier, but its ".implode(', ', $changes).' changed in Zoho since. Imported expenses are posted and are not changed by a new upload; correct the ERP expense to match Zoho, then recheck.';
                    }
                    continue;
                }
                $existing = Expense::where('created_by', $batch->tenant_id)->where('expense_number', $number)->exists();
                if ($existing && $batch->duplicate_strategy === 'block') {
                    $errors[] = "Expense {$number} already exists in ERP and this migration is configured to block duplicates.";
                } elseif ($existing && $batch->duplicate_strategy === 'update') {
                    $errors[] = "Expense {$number} already exists in ERP. Imported expenses are not updated in place because they are already posted; remove it or use the link duplicate setting.";
                } elseif (!$existing && JournalEntry::where('journal_number', $this->journalNumber($row, $index, count($expense['rows']), $batch->tenant_id))->exists()) {
                    $errors[] = 'Journal number '.$this->journalNumber($row, $index, count($expense['rows']), $batch->tenant_id).' is already used; this expense may have been imported before.';
                }
            }
        } elseif ($expense && $this->header($row) !== $expense['header']) {
            $errors[] = "This row's expense details (entry number, date, paid through or total) differ from row {$expense['first_row']} of the same expense.";
        }

        // A newer export must still hold every expense imported from an earlier one; reported once, on the first row.
        if ($sourceRowNumber === ($context['first_row'] ?? null)) {
            foreach ($this->missingImportedEntries($batch, $context) as $entry) {
                $errors[] = "Expense entry {$entry} was imported from an earlier export but is not in this file. Upload a full export of all expenses; if it was deleted in Zoho, delete the ERP expense too.";
            }
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_expense',
            'field' => 'entry_number',
            'source_value' => $row['entry_number'],
            'message' => $message,
            'resolution_url' => Route::has('account.expenses.index') ? route('account.expenses.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'income_expenses')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing expenses.');
        }

        $files = $batch->files()->where('stage_key', 'income_expenses')->where('status', 'staged')->get();
        $rows = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        $earlier = ZohoMigrationStagedRow::whereIn('file_id', $files->pluck('id'))->where('status', 'skipped')->count();
        if ($rows->isEmpty()) {
            if ($earlier === 0) {
                throw new RuntimeException('No validated expense rows are ready to import.');
            }

            // A newer export with no new expenses: nothing to write, the step is complete again.
            $counts = ['expenses' => 0, 'erp_expenses' => 0, 'amount' => 0.0, 'skipped_existing' => 0, 'imported_earlier' => $earlier, 'categories_created' => 0, 'clearing_accounts_created' => 0, 'categories_created_names' => []];
            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $counts]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'income_expenses',
                'event_type' => 'stage_imported',
                'message' => "Expenses import completed: no new expenses; {$earlier} expense lines were imported earlier and skipped.",
                'metadata' => $counts,
            ]);

            return $counts;
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written. Rows imported
        // earlier stay in the context, so the checks see the whole export as validation did.
        $prepared = [];
        foreach ($files->flatMap(fn ($file) => $file->stagedRows()->whereIn('status', ['validated', 'skipped'])->orderBy('source_row_number')->get()) as $row) {
            $prepared[(int) $row->source_row_number] = $this->prepare($row->original_values, $batch);
        }
        $context = $this->context($prepared);
        $expenses = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $expenses[$data['zoho_id']][] = [$row, $data];
        }
        // Oldest first, so bank running balances read in date order.
        uasort($expenses, fn (array $a, array $b) => [$a[0][1]['date'], $a[0][0]->source_row_number] <=> [$b[0][1]['date'], $b[0][0]->source_row_number]);

        return DB::transaction(function () use ($expenses, $batch, $actorId, $stage, $earlier): array {
            $counts = ['expenses' => 0, 'erp_expenses' => 0, 'amount' => 0.0, 'skipped_existing' => 0, 'imported_earlier' => $earlier, 'categories_created' => 0, 'clearing_accounts_created' => 0];
            $created = [];
            // Clearing accounts first: an expense booked to one (e.g. Salary Payable) adds its register line too,
            // even when it comes before the first expense paid through it.
            foreach ($expenses as $lines) {
                if (!$this->bankAccount($lines[0][1], $batch->tenant_id)) {
                    $this->clearingAccount($lines[0][1], $batch->tenant_id, $actorId);
                    $counts['clearing_accounts_created']++;
                }
            }
            $banksByLedger = BankAccount::where('created_by', $batch->tenant_id)->whereNotNull('gl_account_id')->get()->keyBy('gl_account_id');

            foreach ($expenses as $lines) {
                $header = $lines[0][1];
                $bank = $this->bankAccount($header, $batch->tenant_id);

                foreach ($lines as $index => [$row, $data]) {
                    $number = $this->number($data, $index, count($lines));
                    if (Expense::where('created_by', $batch->tenant_id)->where('expense_number', $number)->exists()) {
                        // Only reachable with the "link" duplicate setting (expenses imported earlier are skipped during
                        // validation): the existing ERP expense is kept as is.
                        $counts['skipped_existing']++;
                        $row->update(['status' => 'skipped']);
                        continue;
                    }

                    $account = $this->expenseAccount($data, $batch->tenant_id);
                    $category = $this->category($account, $batch->tenant_id);
                    if (!$category) {
                        $category = ExpenseCategories::create([
                            'category_name' => $account->account_name,
                            'category_code' => $account->account_code,
                            'description' => 'Created by the Zoho migration for expenses booked to this account.',
                            'is_active' => true,
                            'gl_account_id' => $account->id,
                            'creator_id' => $actorId,
                            'created_by' => $batch->tenant_id,
                        ]);
                        $counts['categories_created']++;
                        $created[] = $account->account_name;
                    }

                    $rate = (float) $data['exchange_rate'] ?: 1.0;
                    $base = round($data['amount'] * $rate, 2);
                    $expense = Expense::create([
                        'expense_number' => $number,
                        'expense_date' => $data['date'],
                        'category_id' => $category->id,
                        'bank_account_id' => $bank->id,
                        'chart_of_account_id' => $account->id,
                        'amount' => $data['amount'],
                        'currency_code' => $data['currency_code'],
                        'exchange_rate' => $rate,
                        'base_amount' => $base,
                        'description' => $data['description'] !== '' ? $data['description'] : null,
                        'reference_number' => $data['reference'],
                        'status' => 'posted',
                        'needs_bank_verification' => true,
                        'approved_by' => $actorId,
                        'creator_id' => $actorId,
                        'created_by' => $batch->tenant_id,
                    ]);

                    $this->journals->post([
                        'journal_number' => $this->journalNumber($data, $index, count($lines), $batch->tenant_id),
                        'journal_date' => $data['date'],
                        'reference_type' => 'expense',
                        'reference_id' => $expense->id,
                        'description' => "Expense Entry - #{$number} (imported from Zoho)",
                        'currency_code' => $data['currency_code'],
                        'exchange_rate' => $rate,
                        'foreign_amount' => $data['amount'],
                    ], [
                        ['account' => $account, 'debit' => $base, 'credit' => 0.0, 'description' => 'Expense incurred'],
                        ['account' => $bank->glAccount, 'debit' => 0.0, 'credit' => $base, 'description' => 'Payment made'],
                    ], $actorId, $batch->tenant_id);
                    $this->recordBankMovement($bank, 'debit', $data['date'], $number, 'Expense Posted: '.($data['description'] !== '' ? $data['description'] : 'Expense transaction'), $data['amount'], $batch->tenant_id);
                    if ($booked = $banksByLedger->get($account->id)) {
                        $this->recordBankMovement($booked, 'credit', $data['date'], $number, 'Expense booked to this account: '.($data['description'] !== '' ? $data['description'] : 'Expense transaction'), $data['amount'], $batch->tenant_id);
                    }

                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                    $counts['erp_expenses']++;
                    $counts['amount'] = round($counts['amount'] + $data['amount'], 2);
                }
                $counts['expenses']++;
            }

            $expected = round(collect($expenses)->sum(fn (array $lines) => $lines[0][1]['total']), 2);
            $skipped = round(collect($expenses)->flatten(1)->filter(fn (array $line) => $line[0]->status === 'skipped')->sum(fn (array $line) => $line[1]['amount']), 2);
            if (abs($counts['amount'] + $skipped - $expected) > 0.005) {
                throw new RuntimeException('Imported expenses total '.number_format($counts['amount'], 2).' but the Zoho expenses total '.number_format($expected, 2).'. Nothing was imported.');
            }

            $counts['categories_created_names'] = $created;
            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $counts]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'income_expenses',
                'event_type' => 'stage_imported',
                'message' => "Expenses import completed: {$counts['expenses']} Zoho expenses (".number_format($counts['amount'], 2).") as {$counts['erp_expenses']} posted ERP expenses"
                    .($counts['categories_created'] ? "; {$counts['categories_created']} expense categories created for non-expense accounts (".implode(', ', $created).')' : '')
                    .($counts['clearing_accounts_created'] ? "; {$counts['clearing_accounts_created']} internal clearing account(s) created" : '')
                    .($counts['skipped_existing'] ? "; {$counts['skipped_existing']} already in ERP" : '')
                    .($earlier ? "; {$earlier} expense lines were imported earlier and skipped" : '').'.',
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    /**
     * The ERP expense this step posted for the line: same number, and its journal carries the Zoho expense id
     * (matched without the date part of the journal number, so an expense whose date changed in Zoho is still found).
     */
    private function importedExpense(array $row, int $index, int $lines, int $tenantId): ?Expense
    {
        $expense = Expense::where('created_by', $tenantId)->where('expense_number', $this->number($row, $index, $lines))->first();
        $prefix = ZohoJournalWriter::number($tenantId, $row['zoho_id'].($lines > 1 ? '-'.($index + 1) : ''), 'EXP-'.$row['entry_number'], null);

        return $expense && JournalEntry::where('created_by', $tenantId)
            ->where('journal_number', 'like', addcslashes($prefix, '%_\\').'%')
            ->where('reference_type', 'expense')
            ->where('reference_id', $expense->id)
            ->exists() ? $expense : null;
    }

    /** @return string[] What differs between an imported ERP expense and its line in the export. */
    private function changes(Expense $expense, array $line, int $tenantId): array
    {
        $changes = [];
        if (abs((float) $expense->amount - $line['amount']) > 0.005) {
            $changes[] = 'amount ('.number_format((float) $expense->amount, 2).' in ERP, '.number_format($line['amount'], 2).' in Zoho)';
        }
        if (substr((string) $expense->getRawOriginal('expense_date'), 0, 10) !== $line['date']) {
            $changes[] = 'date';
        }
        if ((int) $expense->chart_of_account_id !== (int) $this->expenseAccount($line, $tenantId)?->id) {
            $changes[] = 'expense account';
        }
        if ((int) $expense->bank_account_id !== (int) $this->bankAccount($line, $tenantId)?->id) {
            $changes[] = 'paid through account';
        }

        return $changes;
    }

    /** @return string[] Entry numbers imported from an earlier export of this step that the current one lacks. */
    private function missingImportedEntries(ZohoMigrationBatch $batch, array $context): array
    {
        $earlierFiles = $batch->files()->where('stage_key', 'income_expenses')->where('status', 'superseded')->pluck('id');
        if ($earlierFiles->isEmpty()) {
            return [];
        }

        return ZohoMigrationStagedRow::whereIn('file_id', $earlierFiles)->where('status', 'imported')->get(['normalized_values'])
            ->map(fn ($row) => $row->normalized_values)
            ->reject(fn (?array $values) => !$values || isset($context['expenses'][$values['zoho_id'] ?? '']))
            ->map(fn (array $values) => (string) $values['entry_number'])
            ->unique()->sort(SORT_NATURAL)->values()->all();
    }

    private function expenseAccount(array $row, int $tenantId): ?ChartOfAccount
    {
        return $this->ledgerAccount(['account_code' => $row['expense_account_code'], 'account_name' => $row['expense_account_name']], $tenantId);
    }

    /** ERP expenses find their category by name; the Zoho account name is the category name. */
    private function category(ChartOfAccount $account, int $tenantId): ?ExpenseCategories
    {
        return ExpenseCategories::where('created_by', $tenantId)->whereRaw('LOWER(category_name) = ?', [mb_strtolower($account->account_name)])->first();
    }

    private function number(array $row, int $index, int $lines): string
    {
        return self::NUMBER_PREFIX.$row['entry_number'].($lines > 1 ? '-'.($index + 1) : '');
    }

    private function journalNumber(array $row, int $index, int $lines, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $row['zoho_id'].($lines > 1 ? '-'.($index + 1) : ''), 'EXP-'.$row['entry_number'], $row['date']);
    }

    private function header(array $row): array
    {
        return [$row['entry_number'], $row['date'], $row['account_code'], $row['account_name'], $row['total'], $row['currency_code']];
    }
}

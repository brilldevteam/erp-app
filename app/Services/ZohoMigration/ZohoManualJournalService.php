<?php

namespace App\Services\ZohoMigration;

use App\Models\PurchaseInvoice;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use App\Services\ZohoMigration\Concerns\ResolvesZohoBankAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

/**
 * Zoho manual journals (BJV…) as posted ERP manual journal entries, listed on the Journal Entries page with the
 * Zoho journal number as their reference.
 *
 * - Cash-basis-only journals are skipped: the ERP keeps accrual books and Zoho's accrual ledger leaves them out.
 * - Lines on Zoho's Accounts Receivable / Accounts Payable post to the ERP control accounts the invoices and bills
 *   use (1100 / 2000), with the Zoho contact; Zoho's built-in Bad Debt account posts to ERP 5700 Bad Debt Expense.
 * - Lines on a bank account's ledger account add the bank register line, as the other money steps do.
 * - A bill Zoho shows as paid that is still open in ERP is settled by the vendor's journal that paid it in Zoho
 *   (a debit to Accounts Payable for that vendor of exactly the open amount, dated on or after the bill).
 */
class ZohoManualJournalService
{
    use ReadsZohoDocuments;
    use ResolvesZohoBankAccounts;

    /** Zoho built-in accounts that have no code in the Chart of Accounts export. */
    public const SYSTEM_ACCOUNTS = ['bad debt' => '5700'];

    private const CONTROL_TYPES = ['accounts receivable' => ZohoSalesInvoiceService::RECEIVABLE_ACCOUNT_CODE, 'accounts payable' => ZohoBillService::PAYABLE_ACCOUNT_CODE];

    /** @var array<int, array> Bill settlements found while validating, per batch. */
    private array $settlementCache = [];

    /** @var array<int, array<string, string>> Zoho account code → Zoho account type, per batch. */
    private array $zohoTypes = [];

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
        $type = mb_strtolower($get('journal type'));
        $status = mb_strtolower($get('status'));
        $code = $get('account code');

        return [
            'include' => $status === 'published' && $type !== 'cash',
            'skip_reason' => $status !== 'published' ? ucfirst($status ?: 'no status').' in Zoho' : ($type === 'cash' ? 'cash basis only' : null),
            'journal_id' => $get('journal id'),
            'number' => $get('journal number'),
            'date' => $this->date($get('journal date'), (string) $batch->source_date_format),
            'date_source' => $get('journal date'),
            'journal_type' => $type,
            'status' => $status,
            'notes' => $get('notes'),
            'reference' => $get('reference number'),
            'currency_code' => strtoupper($get('currency') ?: (string) $batch->base_currency),
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'total' => $amount('total'),
            'account_code' => $code,
            'account_name' => $get('account'),
            'zoho_account_type' => $this->zohoTypes($batch)[$code] ?? '',
            'debit' => $amount('debit'),
            'credit' => $amount('credit'),
            'description' => $get('description'),
            'contact_name' => $get('contact name'),
            'contact_type' => mb_strtolower($get('contact type')),
        ];
    }

    /** Per-journal lines and totals, and the bills the file's journals settle. */
    public function context(iterable $preparedRows, ?ZohoMigrationBatch $batch = null): array
    {
        $journals = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $key = $row['journal_id'];
            $journals[$key] ??= ['first_row' => $rowNumber, 'debit' => 0.0, 'credit' => 0.0, 'header' => $this->header($row), 'lines' => []];
            $journals[$key]['debit'] = round($journals[$key]['debit'] + $row['debit'], 2);
            $journals[$key]['credit'] = round($journals[$key]['credit'] + $row['credit'], 2);
            $journals[$key]['lines'][$rowNumber] = $row;
        }

        return ['journals' => $journals] + ($batch ? ['settlements' => $this->settlements($journals, $batch)] : []);
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $errors = [];
        $journal = $context['journals'][$row['journal_id']] ?? null;
        $settlements = $context['settlements'] ?? ($this->settlementCache[$batch->id] ??= $this->settlements($context['journals'], $batch));

        if ($row['journal_id'] === '' || $row['number'] === '') {
            $errors[] = 'Journal ID and Journal Number are required.';
        }
        if (!$row['date']) {
            $errors[] = "Journal Date '{$row['date_source']}' is not a valid date.";
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = 'Exchange Rate is required for a foreign-currency journal.';
        }
        if (($row['debit'] > 0) === ($row['credit'] > 0) || $row['debit'] < 0 || $row['credit'] < 0) {
            $errors[] = 'Each journal line needs either a debit or a credit amount.';
        }
        if (!$this->account($row, $batch->tenant_id)) {
            $target = $this->targetCode($row);
            $errors[] = $row['account_code'] === '' && $target === null
                ? "Account '{$row['account_name']}' has no account code and is not a known Zoho built-in account."
                : 'Account '.($target ?? $row['account_code'])." ({$row['account_name']}) was not found in ERP. Complete the Chart of Accounts step first.";
        }
        if ($row['contact_name'] !== '' && !$this->contact($row, $batch->tenant_id)) {
            $errors[] = ucfirst($row['contact_type'] ?: 'contact')." '{$row['contact_name']}' was not found in ERP. Complete the Customers and Vendors steps first.";
        }
        if (isset(self::CONTROL_TYPES[$row['zoho_account_type']]) && $row['contact_name'] === '') {
            $errors[] = "A line on {$row['account_name']} needs its customer or vendor.";
        }

        // Journal-level checks are reported once, on the journal's first line.
        if ($journal && $journal['first_row'] === $sourceRowNumber) {
            if (abs($journal['debit'] - $journal['credit']) > 0.005) {
                $errors[] = 'Debits ('.number_format($journal['debit'], 2).') and credits ('.number_format($journal['credit'], 2).') of this journal differ.';
            }
            if (abs($journal['debit'] - $row['total']) > 0.005) {
                $errors[] = 'Line debits add up to '.number_format($journal['debit'], 2).' but the Zoho journal total is '.number_format($row['total'], 2).'.';
            }
            $existing = JournalEntry::where('journal_number', $this->journalNumber($row, $batch->tenant_id))->exists();
            if ($existing && $batch->duplicate_strategy === 'block') {
                $errors[] = "Journal {$row['number']} is already in ERP and this migration is configured to block duplicates.";
            } elseif ($existing && $batch->duplicate_strategy === 'update') {
                $errors[] = "Journal {$row['number']} is already in ERP. Imported journals are not updated in place because they are already posted; remove it or use the link duplicate setting.";
            }
            // Bills Zoho shows as paid that no step has settled are reported on the file's first journal.
            if ($sourceRowNumber === min(array_column($context['journals'], 'first_row'))) {
                foreach ($settlements['unmatched'] as $bill) {
                    $errors[] = "Bill {$bill['number']} of {$bill['vendor']} is paid in Zoho but has ".number_format($bill['open'], 2).' open in ERP, and no Zoho journal to Accounts Payable for this vendor pays it. Complete the Credit applications step first.';
                }
            }
        } elseif ($journal && $this->header($row) !== $journal['header']) {
            $errors[] = "This line's journal details (number, date, type or total) differ from row {$journal['first_row']} of the same journal.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_manual_journal',
            'field' => 'journal_number',
            'source_value' => $row['number'],
            'message' => $message,
            'resolution_url' => Route::has('account.journal-entries.index') ? route('account.journal-entries.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'accounting')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing journals.');
        }

        $files = $batch->files()->where('stage_key', 'accounting')->where('status', 'staged')->get();
        $rows = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException('No validated journal rows are ready to import.');
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $data = $this->prepare($row->original_values, $batch);
            if (!$data['include']) {
                throw new RuntimeException('Row '.$row->source_row_number.' ('.$data['number'].') is now '.$data['skip_reason'].' in the file. Upload the file again to re-check it.');
            }
            $prepared[(int) $row->source_row_number] = $data;
        }
        $context = $this->context($prepared, $batch);
        $journals = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $journals[$data['journal_id']][] = [$row, $data];
        }
        // Oldest first, so bank running balances read in date order.
        uasort($journals, fn (array $a, array $b) => [$a[0][1]['date'], $a[0][0]->source_row_number] <=> [$b[0][1]['date'], $b[0][0]->source_row_number]);
        $skipped = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'skipped')->get())
            ->map(fn ($row) => $this->prepare($row->original_values, $batch))
            ->unique('journal_id')
            ->map(fn (array $data) => "{$data['number']} ({$data['skip_reason']})")
            ->values()->all();

        return DB::transaction(function () use ($journals, $batch, $actorId, $stage, $context, $skipped): array {
            $counts = ['journals' => 0, 'lines' => 0, 'amount' => 0.0, 'bank_lines' => 0, 'skipped_existing' => 0, 'skipped_in_zoho' => count($skipped), 'bills_settled' => 0];
            $banks = BankAccount::where('created_by', $batch->tenant_id)->whereNotNull('gl_account_id')->get()->keyBy('gl_account_id');

            foreach ($journals as $lines) {
                $header = $lines[0][1];
                $number = $this->journalNumber($header, $batch->tenant_id);
                if (JournalEntry::where('journal_number', $number)->exists()) {
                    // Only reachable with the "link" duplicate setting: the existing ERP journal is kept as is.
                    $counts['skipped_existing']++;
                    foreach ($lines as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $rate = (float) $header['exchange_rate'] ?: 1.0;
                $entries = [];
                foreach ($lines as [, $data]) {
                    $entries[] = [
                        'account' => $this->account($data, $batch->tenant_id),
                        'debit' => round($data['debit'] * $rate, 2),
                        'credit' => round($data['credit'] * $rate, 2),
                        'description' => $data['description'] !== '' ? $data['description'] : ($header['notes'] !== '' ? $header['notes'] : 'Zoho journal '.$header['number']),
                        'contact_id' => $data['contact_name'] !== '' ? $this->contact($data, $batch->tenant_id)?->id : null,
                    ];
                }
                $this->journals->post([
                    'journal_number' => $number,
                    'journal_date' => $header['date'],
                    'entry_type' => 'manual',
                    'reference_type' => $header['number'],
                    'reference_id' => null,
                    'description' => ($header['notes'] !== '' ? $header['notes'] : 'Zoho journal '.$header['number']).($header['reference'] !== '' ? " (Ref: {$header['reference']})" : ''),
                    'currency_code' => $header['currency_code'],
                    'exchange_rate' => $rate,
                    'foreign_amount' => array_sum(array_column(array_column($lines, 1), 'debit')),
                ], $entries, $actorId, $batch->tenant_id);

                foreach ($entries as $index => $entry) {
                    if ($bank = $banks->get($entry['account']->id)) {
                        $amount = $entry['debit'] > 0 ? $entry['debit'] : $entry['credit'];
                        $this->recordBankMovement($bank, $entry['debit'] > 0 ? 'credit' : 'debit', $header['date'], $header['number'], "Journal {$header['number']}: ".$entry['description'], $amount, $batch->tenant_id);
                        $counts['bank_lines']++;
                    }
                }
                foreach ($lines as [$row, $data]) {
                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                }
                $counts['journals']++;
                $counts['lines'] += count($lines);
                $counts['amount'] = round($counts['amount'] + array_sum(array_column($entries, 'debit')), 2);
            }

            $settled = [];
            foreach ($context['settlements']['matched'] as $settlement) {
                $bill = PurchaseInvoice::lockForUpdate()->findOrFail($settlement['bill_id']);
                $bill->paid_amount = round((float) $bill->paid_amount + $settlement['amount'], 2);
                $bill->balance_amount = max(0, round((float) $bill->balance_amount - $settlement['amount'], 2));
                $bill->status = $bill->balance_amount <= 0 ? 'paid' : 'partial';
                $bill->notes = trim(($bill->notes ? $bill->notes."\n" : '').'Paid in Zoho by journal '.$settlement['journal'].' on '.$settlement['date'].' ('.number_format($settlement['amount'], 2).'), not by a vendor payment.');
                $bill->save();
                $settled[] = "{$bill->invoice_number} by {$settlement['journal']}";
            }
            $counts['bills_settled'] = count($settled);
            $counts['bills_settled_list'] = $settled;
            $counts['skipped_in_zoho_journals'] = $skipped;

            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $counts]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'accounting',
                'event_type' => 'stage_imported',
                'message' => "Journals import completed: {$counts['journals']} Zoho journals ({$counts['lines']} lines, ".number_format($counts['amount'], 2).') posted'
                    .($counts['bank_lines'] ? "; {$counts['bank_lines']} bank register lines" : '')
                    .($settled ? '; bills paid by journal: '.implode(', ', $settled) : '')
                    .($skipped ? '; skipped: '.implode(', ', $skipped) : '')
                    .($counts['skipped_existing'] ? "; {$counts['skipped_existing']} already in ERP" : '').'.',
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    /**
     * Matches bills Zoho shows as (more) paid than ERP to the vendor's Accounts Payable journal debits that paid
     * them: exact amount, journal dated on or after the bill, oldest bill and oldest journal first.
     */
    private function settlements(array $journals, ZohoMigrationBatch $batch): array
    {
        $debits = [];
        foreach ($journals as $journal) {
            foreach ($journal['lines'] as $line) {
                if ($line['zoho_account_type'] === 'accounts payable' && $line['debit'] > 0 && $line['contact_name'] !== '') {
                    $debits[] = ['vendor' => mb_strtolower($line['contact_name']), 'amount' => $line['debit'], 'date' => $line['date'], 'journal' => $line['number'], 'used' => false];
                }
            }
        }
        usort($debits, fn (array $a, array $b) => [$a['date'], $a['journal']] <=> [$b['date'], $b['journal']]);

        $zohoBalances = [];
        foreach ($batch->files()->where('stage_key', 'purchase_invoices')->where('status', 'staged')->get() as $file) {
            foreach ($file->stagedRows()->get(['original_values']) as $row) {
                $values = [];
                foreach ((array) $row->original_values as $key => $value) {
                    $values[$this->profiles->normalize($key)] = trim((string) $value);
                }
                if (($values['bill number'] ?? '') !== '' && isset($values['balance'])) {
                    $zohoBalances[mb_strtolower($values['vendor name'] ?? '').'|'.mb_strtolower($values['bill number'])] = round((float) $values['balance'], 2);
                }
            }
        }

        $matched = [];
        $unmatched = [];
        $bills = PurchaseInvoice::where('created_by', $batch->tenant_id)->where('balance_amount', '>', 0)->where('status', '!=', 'draft')
            ->with('vendor')->orderBy('invoice_date')->orderBy('id')->get();
        foreach ($bills as $bill) {
            $vendor = mb_strtolower((string) $bill->vendor?->name);
            $zoho = $zohoBalances[$vendor.'|'.mb_strtolower($bill->invoice_number)] ?? null;
            $open = round((float) $bill->balance_amount - (float) $zoho, 2);
            if ($zoho === null || $open <= 0) {
                continue;
            }
            foreach ($debits as &$debit) {
                if (!$debit['used'] && $debit['vendor'] === $vendor && abs($debit['amount'] - $open) < 0.005 && $debit['date'] >= substr((string) $bill->getRawOriginal('invoice_date'), 0, 10)) {
                    $debit['used'] = true;
                    $matched[] = ['bill_id' => $bill->id, 'amount' => $open, 'journal' => $debit['journal'], 'date' => $debit['date']];
                    continue 2;
                }
            }
            unset($debit);
            $unmatched[] = ['number' => $bill->invoice_number, 'vendor' => $bill->vendor?->name, 'open' => $open];
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    private function account(array $row, int $tenantId): ?ChartOfAccount
    {
        $code = $this->targetCode($row);

        return $code !== null ? ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->first() : null;
    }

    /** The ERP account code a Zoho journal line posts to. */
    private function targetCode(array $row): ?string
    {
        if (isset(self::CONTROL_TYPES[$row['zoho_account_type']])) {
            return self::CONTROL_TYPES[$row['zoho_account_type']];
        }
        if ($row['account_code'] === '') {
            return self::SYSTEM_ACCOUNTS[mb_strtolower($row['account_name'])] ?? null;
        }

        return $row['account_code'];
    }

    private function contact(array $row, int $tenantId)
    {
        return $row['contact_type'] === 'vendor' ? $this->vendorUser($row['contact_name'], $tenantId) : $this->customerUser($row['contact_name'], $tenantId);
    }

    /** Zoho account code → Zoho account type, from the migration's Chart of Accounts export (step 2). */
    private function zohoTypes(ZohoMigrationBatch $batch): array
    {
        return $this->zohoTypes[$batch->id] ??= $batch->files()->where('stage_key', 'chart_of_accounts')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->get(['original_values']))
            ->mapWithKeys(function ($row) {
                $values = [];
                foreach ((array) $row->original_values as $key => $value) {
                    $values[$this->profiles->normalize($key)] = trim((string) $value);
                }

                return [($values['account code'] ?? '') => mb_strtolower($values['account type'] ?? '')];
            })
            ->filter(fn (string $type, $code) => (string) $code !== '')
            ->all();
    }

    private function journalNumber(array $row, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $row['journal_id'], $row['number'], $row['date']);
    }

    private function header(array $row): array
    {
        return [$row['number'], $row['date'], $row['journal_type'], $row['total'], $row['currency_code']];
    }
}

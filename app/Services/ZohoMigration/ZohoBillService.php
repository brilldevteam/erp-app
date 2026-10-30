<?php

namespace App\Services\ZohoMigration;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

/**
 * Imports a Zoho Bills export (one row per bill line) as ERP purchase invoices.
 *
 * Bill lines are free-text lines in the ERP (no product): each keeps the Zoho item name and description and is
 * charged to its own Zoho account, as in Zoho. Line amounts carry any Zoho discount as a fixed discount so every
 * line and bill matches Zoho to the cent. Live bills are imported posted with their own balanced journal entry —
 * debit each line's Zoho account, credit the ERP payables account 2000 — and stay unpaid: Zoho's typed balances
 * are not trusted, payments come from the Vendor payments stage. Draft and pending bills import as drafts with no
 * journal; void bills are skipped and listed in the import result.
 */
class ZohoBillService
{
    use ReadsZohoDocuments;

    public const PAYABLE_ACCOUNT_CODE = '2000';

    /** Zoho bill status => post | draft | skip. */
    private const ACTIONS = [
        'paid' => 'post', 'overdue' => 'post', 'open' => 'post', 'unpaid' => 'post',
        'partially paid' => 'post', 'partiallypaid' => 'post',
        'draft' => 'draft', 'pending' => 'draft', 'pending approval' => 'draft', 'rejected' => 'draft',
        'void' => 'skip',
    ];

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoJournalWriter $journals,
    ) {
    }

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);

        $zohoStatus = mb_strtolower($get('bill status'));
        $action = self::ACTIONS[$zohoStatus] ?? null;
        $billDate = $this->date($get('bill date'), (string) $batch->source_date_format);
        $dueSource = $get('due date');
        $quantity = round((float) str_replace(',', '', $get('quantity')), 4);
        $rate = $amount('rate');
        $lineTotal = $amount('item total');

        return [
            'include' => $action !== 'skip',
            'action' => $action,
            'skip_reason' => $action === 'skip' ? 'Void in Zoho' : null,
            'bill_number' => $get('bill number'),
            'zoho_bill_id' => $get('bill id'),
            'bill_date' => $billDate,
            'bill_date_source' => $get('bill date'),
            'due_date' => $dueSource === '' ? $billDate : $this->date($dueSource, (string) $batch->source_date_format),
            'due_date_source' => $dueSource,
            'vendor' => $get('vendor name'),
            'zoho_status' => $get('bill status'),
            'payment_terms' => $get('payment terms label') ?: null,
            'purchase_order' => $get('purchaseorder') ?: ($get('purchase order number') ?: null),
            'notes' => $this->notes($get('vendor notes'), $get('terms & conditions'), $get('bill status'), $action, $dueSource === ''),
            'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'bill_total' => $amount('total'),
            'tax_amount' => $amount('tax amount'),
            'description' => trim(implode("\n", array_filter([$get('item name'), $get('description')]))) ?: null,
            'unit' => $get('usage unit') ?: null,
            'quantity' => $quantity,
            'unit_price' => $rate,
            'line_total' => $lineTotal,
            // Zoho's line total already includes its line discount and its share of any bill-level discount.
            'discount_value' => round(round($quantity * $rate, 2) - $lineTotal, 2),
            'account_code' => $get('account code'),
            'account_name' => $get('account'),
        ];
    }

    /** Per-bill facts the row checks need: first row, line-total sum, Zoho total and header. */
    public function context(iterable $preparedRows): array
    {
        $bills = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $key = $this->key($row);
            $bills[$key] ??= ['first_row' => $rowNumber, 'line_sum' => 0.0, 'total' => $row['bill_total'], 'header' => $this->header($row)];
            $bills[$key]['line_sum'] = round($bills[$key]['line_sum'] + $row['line_total'], 2);
        }

        return $bills;
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $errors = [];
        $bill = $context[$this->key($row)] ?? null;

        if ($row['bill_number'] === '') {
            $errors[] = 'Bill Number is required.';
        }
        if ($row['action'] === null) {
            $errors[] = "Bill Status '{$row['zoho_status']}' is not a known Zoho status.";
        }
        if (!$row['bill_date']) {
            $errors[] = "Bill Date '{$row['bill_date_source']}' is not a valid date.";
        }
        if (!$row['due_date']) {
            $errors[] = "Due Date '{$row['due_date_source']}' is not a valid date.";
        } elseif ($row['bill_date'] && $row['due_date'] < $row['bill_date']) {
            $errors[] = 'Due Date is before the Bill Date.';
        }
        if ($row['vendor'] === '') {
            $errors[] = 'Vendor Name is required.';
        } elseif (!$this->vendorUser($row['vendor'], $batch->tenant_id)) {
            $errors[] = "Vendor '{$row['vendor']}' was not found in ERP. Complete the Vendors step first.";
        }
        if ($row['quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        }
        if ($row['unit_price'] < 0) {
            $errors[] = 'Rate must be zero or greater.';
        }
        if ($row['discount_value'] < 0 || $row['discount_value'] > round($row['quantity'] * $row['unit_price'], 2)) {
            $errors[] = 'Item Total '.number_format($row['line_total'], 2).' does not fit Quantity × Rate ('.number_format($row['quantity'] * $row['unit_price'], 2).').';
        }
        if ($row['tax_amount'] != 0.0) {
            $errors[] = 'This bill line has Zoho tax ('.number_format($row['tax_amount'], 2).'); taxed bills are not supported by this import yet.';
        }
        if (!$this->lineAccount($row, $batch->tenant_id)) {
            $errors[] = "Account {$row['account_code']} ({$row['account_name']}) was not found in ERP. Complete the Chart of Accounts step first.";
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = 'Exchange Rate is required for a foreign-currency bill.';
        }
        if (mb_strlen((string) $row['payment_terms']) > 255 || mb_strlen((string) $row['purchase_order']) > 255) {
            $errors[] = 'Payment Terms Label or PurchaseOrder is longer than 255 characters.';
        }

        // Bill-level checks are reported once, on the bill's first row.
        if ($bill && $bill['first_row'] === $sourceRowNumber) {
            if (abs($bill['line_sum'] - $bill['total']) > 0.01) {
                $errors[] = 'Line totals add up to '.number_format($bill['line_sum'], 2).' but the Zoho bill total is '.number_format($bill['total'], 2).'.';
            }
            $vendor = $this->vendorUser($row['vendor'], $batch->tenant_id);
            if ($vendor && ($existing = $this->existing($row['bill_number'], $vendor->id, $batch->tenant_id))) {
                if ($batch->duplicate_strategy === 'block') {
                    $errors[] = 'A bill with this number from this vendor already exists in ERP and this migration is configured to block duplicates.';
                } elseif ($batch->duplicate_strategy === 'update') {
                    $errors[] = 'A bill with this number from this vendor already exists in ERP. Imported bills are not updated in place because they may already be posted; remove it or use the link duplicate setting.';
                }
            }
            if ($row['action'] === 'post') {
                if (!$this->payableAccount($batch->tenant_id)) {
                    $errors[] = 'Payables account '.self::PAYABLE_ACCOUNT_CODE.' (Accounts Payable) was not found in ERP.';
                }
                if (empty($existing) && JournalEntry::where('journal_number', $this->journalNumber($row, $batch->tenant_id))->exists()) {
                    $errors[] = 'Journal number '.$this->journalNumber($row, $batch->tenant_id).' is already used; this bill may have been imported before.';
                }
            }
        } elseif ($bill && $this->header($row) !== $bill['header']) {
            $errors[] = "This line's bill details (date, vendor, status or currency) differ from row {$bill['first_row']} of the same bill.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_bill',
            'field' => 'bill_number',
            'source_value' => $row['bill_number'],
            'message' => $message,
            'resolution_url' => Route::has('purchase-invoices.index') ? route('purchase-invoices.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'purchase_invoices')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing bills.');
        }

        $files = $batch->files()->where('stage_key', 'purchase_invoices')->where('status', 'staged')->get();
        $rows = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException('No validated bill rows are ready to import.');
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[(int) $row->source_row_number] = $this->prepare($row->original_values, $batch);
        }
        $context = $this->context($prepared);
        $bills = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if (!$data['include']) {
                throw new RuntimeException('Row '.$row->source_row_number.' ('.$data['bill_number'].') is now '.$data['zoho_status'].' in the file. Upload the file again to re-check it.');
            }
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $bills[$this->key($data)][] = [$row, $data];
        }

        // Bills Zoho never counted (void) are reported by number, not imported.
        $skippedInZoho = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'skipped')->get())
            ->map(fn ($row) => $this->prepare($row->original_values, $batch))
            ->unique(fn (array $data) => $this->key($data))
            ->map(fn (array $data) => "{$data['bill_number']} – {$data['vendor']} ({$data['skip_reason']})")
            ->values()->all();

        return DB::transaction(function () use ($bills, $batch, $actorId, $stage, $skippedInZoho): array {
            $counts = ['posted' => 0, 'drafts' => 0, 'skipped_existing' => 0, 'lines' => 0, 'journal_entries' => 0, 'skipped_in_zoho' => count($skippedInZoho)];
            $payable = $this->payableAccount($batch->tenant_id);

            foreach ($bills as $lines) {
                $header = $lines[0][1];
                $vendor = $this->vendorUser($header['vendor'], $batch->tenant_id);
                if ($this->existing($header['bill_number'], $vendor->id, $batch->tenant_id)) {
                    // Only reachable with the "link" duplicate setting: the existing ERP bill is kept as is.
                    $counts['skipped_existing']++;
                    foreach ($lines as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $bill = PurchaseInvoice::create([
                    'invoice_number' => $header['bill_number'],
                    'purchase_order_reference' => $header['purchase_order'],
                    'invoice_date' => $header['bill_date'],
                    'due_date' => $header['due_date'],
                    'vendor_id' => $vendor->id,
                    'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0,
                    'paid_amount' => 0, 'debit_note_applied' => 0, 'balance_amount' => 0,
                    'status' => $header['action'] === 'post' ? 'posted' : 'draft',
                    'payment_terms' => $header['payment_terms'],
                    'notes' => $header['notes'],
                    'currency_code' => $header['currency_code'],
                    'base_currency_code' => strtoupper((string) $batch->base_currency),
                    'exchange_rate' => $header['exchange_rate'],
                    'creator_id' => $actorId,
                    'created_by' => $batch->tenant_id,
                ]);

                $accountTotals = [];
                foreach ($lines as [$row, $data]) {
                    $account = $this->lineAccount($data, $batch->tenant_id);
                    PurchaseInvoiceItem::create([
                        'invoice_id' => $bill->id,
                        'product_id' => null,
                        'account_id' => $account->id,
                        'unit' => $data['unit'],
                        'description' => $data['description'],
                        'quantity' => $data['quantity'],
                        'unit_price' => $data['unit_price'],
                        'discount_type' => 'fixed',
                        'discount_value' => $data['discount_value'],
                        'tax_percentage' => 0,
                    ]);
                    $accountTotals[$account->id] = round(($accountTotals[$account->id] ?? 0) + $data['line_total'], 2);
                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                    $counts['lines']++;
                }
                arsort($accountTotals);

                $items = $bill->items()->get();
                $total = round((float) $items->sum('total_amount'), 2);
                // Guard: the ERP's own line maths must land exactly on the Zoho total, or nothing is kept.
                if (abs($total - $header['bill_total']) > 0.01) {
                    throw new RuntimeException("Bill {$header['bill_number']}: ERP total {$total} does not match the Zoho total {$header['bill_total']}. Nothing was imported.");
                }
                $bill->update([
                    'subtotal' => round((float) $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price), 2),
                    'discount_amount' => round((float) $items->sum('discount_amount'), 2),
                    'tax_amount' => round((float) $items->sum('tax_amount'), 2),
                    'total_amount' => $total,
                    'balance_amount' => $total,
                    'base_amount' => round($total * $header['exchange_rate'], 2),
                ]);

                if ($header['action'] === 'post') {
                    $this->postJournal($bill, $header, $accountTotals, $payable, $vendor->id, $actorId, $batch->tenant_id);
                    $counts['journal_entries']++;
                    $counts['posted']++;
                } else {
                    $counts['drafts']++;
                }
            }

            $summary = $counts + ['skipped_in_zoho_bills' => $skippedInZoho];
            $stage->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $actorId,
                'summary' => $summary,
            ]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'purchase_invoices',
                'event_type' => 'stage_imported',
                'message' => "Bills import completed: {$counts['posted']} posted with journal entries, {$counts['drafts']} drafts, {$counts['lines']} lines"
                    .($counts['skipped_existing'] ? ", {$counts['skipped_existing']} already in ERP" : '')
                    .($skippedInZoho ? '. Not imported: '.implode(', ', $skippedInZoho) : '').'.',
                'metadata' => $summary,
            ]);

            return $summary;
        });
    }

    /** Debit each Zoho line account and credit Accounts Payable for the bill total, in the base currency. */
    private function postJournal(PurchaseInvoice $bill, array $header, array $accountTotals, ChartOfAccount $payable, int $vendorId, int $actorId, int $tenantId): void
    {
        $rate = (float) $header['exchange_rate'] ?: 1.0;
        $debits = ZohoJournalWriter::toBase($accountTotals, $rate, (float) $bill->total_amount);

        $lines = [];
        foreach ($debits as $accountId => $amount) {
            $account = ChartOfAccount::findOrFail($accountId);
            $lines[] = ['account' => $account, 'debit' => $amount, 'credit' => 0.0, 'description' => 'Purchase from '.$header['vendor'].' - '.$account->account_name];
        }
        $lines[] = ['account' => $payable, 'debit' => 0.0, 'credit' => round(array_sum($debits), 2), 'description' => 'Bill '.$bill->invoice_number, 'contact_id' => $vendorId];

        $this->journals->post([
            'journal_number' => $this->journalNumber($header, $tenantId),
            'journal_date' => $header['bill_date'],
            'reference_type' => 'purchase_invoice',
            'reference_id' => $bill->id,
            'description' => 'Purchase Invoice #'.$bill->invoice_number.' (imported from Zoho)',
            'currency_code' => $header['currency_code'],
            'exchange_rate' => $rate,
            'foreign_amount' => (float) $bill->total_amount,
        ], $lines, $actorId, $tenantId);
    }

    private function journalNumber(array $row, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $row['zoho_bill_id'], 'BILL-'.$row['bill_number'], $row['bill_date']);
    }

    /** Vendors number their own bills, so a bill is identified by vendor and number together. */
    private function key(array $row): string
    {
        return mb_strtolower($row['vendor']).'|'.mb_strtolower($row['bill_number']);
    }

    private function lineAccount(array $row, int $tenantId): ?ChartOfAccount
    {
        $query = ChartOfAccount::where('created_by', $tenantId);

        return $row['account_code'] !== ''
            ? (clone $query)->where('account_code', $row['account_code'])->first()
            : ($row['account_name'] !== '' ? (clone $query)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($row['account_name'])])->first() : null);
    }

    private function payableAccount(int $tenantId): ?ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', self::PAYABLE_ACCOUNT_CODE)->first();
    }

    private function header(array $row): array
    {
        return [$row['bill_date'], $row['due_date'], $row['action'], $row['currency_code'], $row['exchange_rate'], $row['bill_total']];
    }

    private function notes(string $notes, string $terms, string $zohoStatus, ?string $action, bool $noDueDate): ?string
    {
        $parts = array_filter([
            $notes,
            $terms !== '' ? "Terms & Conditions:\n{$terms}" : '',
            $action !== null ? 'Zoho status: '.ucfirst(mb_strtolower($zohoStatus)) : '',
            $noDueDate ? 'No due date in Zoho; due date set to the bill date.' : '',
        ]);

        return $parts ? implode("\n\n", $parts) : null;
    }

    private function existing(string $number, int $vendorId, int $tenantId): ?PurchaseInvoice
    {
        return PurchaseInvoice::where('created_by', $tenantId)->where('vendor_id', $vendorId)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($number)])->first();
    }
}

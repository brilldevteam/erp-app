<?php

namespace App\Services\ZohoMigration;

use App\Models\DocumentTemplate;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\DocumentTemplates\DocumentTemplateService;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Quotation\Models\SalesQuotation;

/**
 * Imports a Zoho Invoices export (one row per invoice line) as ERP sales invoices.
 *
 * Lines use the "Zoho free-text line" service with the Zoho item name and description kept, and carry Zoho's
 * item and invoice-level discounts as fixed discounts so every line and invoice total matches Zoho to the cent.
 * Live invoices are imported posted with their own journal entry — debit the ERP receivable account 1100,
 * credit each line's Zoho revenue account — and stay unpaid: Zoho's typed balances are not trusted, payments
 * come from the Customer payments stage. Draft, rejected and pending invoices import as drafts with no journal;
 * void invoices and internally "Approved" (never posted) invoices are skipped and listed in the import result.
 */
class ZohoSalesInvoiceService
{
    use ReadsZohoDocuments;

    public const RECEIVABLE_ACCOUNT_CODE = '1100';

    /** Zoho invoice status => post | draft | skip. */
    private const ACTIONS = [
        'closed' => 'post', 'paid' => 'post', 'overdue' => 'post', 'open' => 'post', 'sent' => 'post',
        'unpaid' => 'post', 'viewed' => 'post', 'partially paid' => 'post', 'partiallypaid' => 'post',
        'draft' => 'draft', 'rejected' => 'draft', 'pending' => 'draft', 'pending approval' => 'draft',
        'void' => 'skip', 'approved' => 'skip',
    ];

    private const SKIP_REASONS = [
        'void' => 'Void in Zoho',
        'approved' => 'Approved internally but never posted in Zoho',
    ];

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoFreeTextLineProduct $lineProduct,
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

        $zohoStatus = mb_strtolower($get('invoice status'));
        $action = self::ACTIONS[$zohoStatus] ?? null;
        $invoiceDate = $this->date($get('invoice date'), (string) $batch->source_date_format);
        $dueSource = $get('due date');
        $quantity = (float) str_replace(',', '', $get('quantity'));
        $unitPrice = $amount('item price');
        $lineTotal = $amount('item total');

        return [
            'include' => $action !== 'skip',
            'action' => $action,
            'skip_reason' => self::SKIP_REASONS[$zohoStatus] ?? null,
            'invoice_number' => $get('invoice number'),
            'zoho_invoice_id' => $get('invoice id'),
            'invoice_date' => $invoiceDate,
            'invoice_date_source' => $get('invoice date'),
            'due_date' => $dueSource === '' ? $invoiceDate : $this->date($dueSource, (string) $batch->source_date_format),
            'due_date_source' => $dueSource,
            'customer' => $get('customer name'),
            'zoho_status' => $get('invoice status'),
            'payment_terms' => $get('payment terms label') ?: null,
            'subject' => $get('subject') ?: null,
            'notes' => $this->notes($get('notes'), $get('terms & conditions'), $get('invoice status'), $action, $dueSource === ''),
            'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'invoice_total' => $amount('total'),
            'estimate_number' => $get('estimate number') ?: null,
            'description' => trim(implode("\n", array_filter([$get('item name'), $get('item desc')]))) ?: null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            // Zoho's line total already includes its item discount and its share of any invoice-level discount.
            'discount_value' => round(round($quantity * $unitPrice, 2) - $lineTotal, 2),
            'revenue_account_code' => $get('account code'),
            'revenue_account_name' => $get('account'),
        ];
    }

    /** Per-invoice facts the row checks need: first row, line-total sum, Zoho total and header. */
    public function context(iterable $preparedRows): array
    {
        $invoices = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $key = mb_strtolower($row['invoice_number']);
            $invoices[$key] ??= ['first_row' => $rowNumber, 'line_sum' => 0.0, 'total' => $row['invoice_total'], 'header' => $this->header($row)];
            $invoices[$key]['line_sum'] = round($invoices[$key]['line_sum'] + $row['line_total'], 2);
        }

        return $invoices;
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $errors = [];
        $invoice = $context[mb_strtolower($row['invoice_number'])] ?? null;

        if ($row['invoice_number'] === '') {
            $errors[] = 'Invoice Number is required.';
        }
        if ($row['action'] === null) {
            $errors[] = "Invoice Status '{$row['zoho_status']}' is not a known Zoho status.";
        }
        if (!$row['invoice_date']) {
            $errors[] = "Invoice Date '{$row['invoice_date_source']}' is not a valid date.";
        }
        if (!$row['due_date']) {
            $errors[] = "Due Date '{$row['due_date_source']}' is not a valid date.";
        } elseif ($row['invoice_date'] && $row['due_date'] < $row['invoice_date']) {
            $errors[] = 'Due Date is before the Invoice Date.';
        }
        if ($row['customer'] === '') {
            $errors[] = 'Customer Name is required.';
        } elseif (!$this->customerUser($row['customer'], $batch->tenant_id)) {
            $errors[] = "Customer '{$row['customer']}' was not found in ERP. Complete the Customers step first.";
        }
        if ($row['quantity'] <= 0) {
            $errors[] = 'Quantity must be greater than zero.';
        } elseif (floor($row['quantity']) !== $row['quantity']) {
            $errors[] = "Quantity {$row['quantity']} is not a whole number; ERP invoice lines store whole quantities only.";
        }
        if ($row['unit_price'] < 0) {
            $errors[] = 'Item Price must be zero or greater.';
        }
        if ($row['discount_value'] < 0 || $row['discount_value'] > round($row['quantity'] * $row['unit_price'], 2)) {
            $errors[] = 'Item Total '.number_format($row['line_total'], 2).' does not fit Quantity × Item Price ('.number_format($row['quantity'] * $row['unit_price'], 2).').';
        }
        if (!$this->revenueAccount($row, $batch->tenant_id)) {
            $errors[] = "Revenue account {$row['revenue_account_code']} ({$row['revenue_account_name']}) was not found in ERP. Complete the Chart of Accounts step first.";
        }
        if ($row['discount_value'] > 0 && !$this->discountAccount($batch->tenant_id)) {
            $errors[] = 'Zoho posts item discounts to its Discount account, and ERP has no single account named Discount. Complete the Chart of Accounts step first.';
        }
        if (mb_strlen((string) $row['subject']) > 500) {
            $errors[] = 'Subject is longer than 500 characters.';
        }
        if (mb_strlen((string) $row['payment_terms']) > 255) {
            $errors[] = 'Payment Terms Label is longer than 255 characters.';
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = 'Exchange Rate is required for a foreign-currency invoice.';
        }

        // Invoice-level checks are reported once, on the invoice's first row.
        if ($invoice && $invoice['first_row'] === $sourceRowNumber) {
            if (abs($invoice['line_sum'] - $invoice['total']) > 0.01) {
                $errors[] = 'Line totals add up to '.number_format($invoice['line_sum'], 2).' but the Zoho invoice total is '.number_format($invoice['total'], 2).'.';
            }
            if ($existing = $this->existing($row['invoice_number'], $batch->tenant_id)) {
                if ($batch->duplicate_strategy === 'block') {
                    $errors[] = 'An invoice with this number already exists in ERP and this migration is configured to block duplicates.';
                } elseif ($batch->duplicate_strategy === 'update') {
                    $errors[] = 'An invoice with this number already exists in ERP. Imported invoices are not updated in place because they may already be posted; remove it or use the link duplicate setting.';
                }
            }
            if ($row['action'] === 'post') {
                if (!$this->receivableAccount($batch->tenant_id)) {
                    $errors[] = 'Receivable account '.self::RECEIVABLE_ACCOUNT_CODE.' (Accounts Receivable) was not found in ERP.';
                }
                if (!$existing && JournalEntry::where('journal_number', $this->journalNumber($row, $batch->tenant_id))->exists()) {
                    $errors[] = 'Journal number '.$this->journalNumber($row, $batch->tenant_id).' is already used; this invoice may have been imported before.';
                }
            }
        } elseif ($invoice && $this->header($row) !== $invoice['header']) {
            $errors[] = "This line's invoice details (date, customer, status or currency) differ from row {$invoice['first_row']} of the same invoice.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_sales_invoice',
            'field' => 'invoice_number',
            'source_value' => $row['invoice_number'],
            'message' => $message,
            'resolution_url' => Route::has('sales-invoices.index') ? route('sales-invoices.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'sales_invoices')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing sales invoices.');
        }

        $files = $batch->files()->where('stage_key', 'sales_invoices')->where('status', 'staged')->get();
        $rows = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException('No validated invoice rows are ready to import.');
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[(int) $row->source_row_number] = $this->prepare($row->original_values, $batch);
        }
        $context = $this->context($prepared);
        $invoices = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if (!$data['include']) {
                throw new RuntimeException('Row '.$row->source_row_number.' ('.$data['invoice_number'].') is now '.$data['zoho_status'].' in the file. Upload the file again to re-check it.');
            }
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $invoices[mb_strtolower($data['invoice_number'])][] = [$row, $data];
        }

        // Invoices Zoho never counted (void, internally approved) are reported by number, not imported.
        $skippedInZoho = $files->flatMap(fn ($file) => $file->stagedRows()->where('status', 'skipped')->get())
            ->map(fn ($row) => $this->prepare($row->original_values, $batch))
            ->unique(fn (array $data) => mb_strtolower($data['invoice_number']))
            ->map(fn (array $data) => "{$data['invoice_number']} ({$data['skip_reason']})")
            ->values()->all();

        return DB::transaction(function () use ($invoices, $batch, $actorId, $stage, $skippedInZoho): array {
            $counts = ['posted' => 0, 'drafts' => 0, 'skipped_existing' => 0, 'lines' => 0, 'journal_entries' => 0, 'linked_quotations' => 0, 'skipped_in_zoho' => count($skippedInZoho)];
            $product = $this->lineProduct->for($batch->tenant_id, $actorId);
            $templateId = app(DocumentTemplateService::class)->resolveForDocument(DocumentTemplate::TYPE_INVOICE, $batch->tenant_id, null)->id;
            $receivable = $this->receivableAccount($batch->tenant_id);

            foreach ($invoices as $lines) {
                $header = $lines[0][1];
                if ($this->existing($header['invoice_number'], $batch->tenant_id)) {
                    // Only reachable with the "link" duplicate setting: the existing ERP invoice is kept as is.
                    $counts['skipped_existing']++;
                    foreach ($lines as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $customer = $this->customerUser($header['customer'], $batch->tenant_id);
                $quotation = $header['estimate_number']
                    ? SalesQuotation::where('created_by', $batch->tenant_id)->whereRaw('LOWER(quotation_number) = ?', [mb_strtolower($header['estimate_number'])])->first()
                    : null;
                // The ERP links a quotation to one invoice only; a second Zoho invoice from the same estimate keeps the link in its notes.
                $notes = $header['notes'];
                if ($quotation && ($linked = SalesInvoice::where('created_by', $batch->tenant_id)->where('quotation_id', $quotation->id)->first())) {
                    $notes = trim(($notes ?? '')."\n\nAlso created from quotation {$quotation->quotation_number}, which is linked to invoice {$linked->invoice_number}.");
                    $quotation = null;
                }
                // Revenue at full price per account; the item discounts go to Zoho's Discount account.
                $accountTotals = [];
                $discount = 0.0;
                foreach ($lines as [, $data]) {
                    $accountId = $this->revenueAccount($data, $batch->tenant_id)->id;
                    $accountTotals[$accountId] = round(($accountTotals[$accountId] ?? 0) + $data['line_total'] + $data['discount_value'], 2);
                    $discount = round($discount + $data['discount_value'], 2);
                }
                arsort($accountTotals);
                $mainAccountId = array_key_first($accountTotals);

                $invoice = SalesInvoice::create([
                    'invoice_number' => $header['invoice_number'],
                    'invoice_date' => $header['invoice_date'],
                    'due_date' => $header['due_date'],
                    'customer_id' => $customer->id,
                    'quotation_id' => $quotation?->id,
                    'document_template_id' => $templateId,
                    'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0,
                    'paid_amount' => 0, 'balance_amount' => 0,
                    'status' => $header['action'] === 'post' ? 'posted' : 'draft',
                    'type' => 'service',
                    // The invoice form only offers sales revenue accounts; other accounts still get their journal credit.
                    'revenue_account_id' => ChartOfAccount::salesRevenueOptions($batch->tenant_id)->whereKey($mainAccountId)->exists() ? $mainAccountId : null,
                    'payment_terms' => $header['payment_terms'],
                    'subject' => $header['subject'],
                    'notes' => $notes,
                    'currency_code' => $header['currency_code'],
                    'exchange_rate' => $header['exchange_rate'],
                    'creator_id' => $actorId,
                    'created_by' => $batch->tenant_id,
                ]);

                foreach ($lines as [$row, $data]) {
                    SalesInvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'product_id' => $product->id,
                        'description' => $data['description'],
                        'quantity' => $data['quantity'],
                        'unit_price' => $data['unit_price'],
                        'discount_type' => 'fixed',
                        'discount_value' => $data['discount_value'],
                        'tax_percentage' => 0,
                        'creator_id' => $actorId,
                        'created_by' => $batch->tenant_id,
                    ]);
                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                    $counts['lines']++;
                }

                $items = $invoice->items()->get();
                $total = round((float) $items->sum('total_amount'), 2);
                // Guard: the ERP's own line maths must land exactly on the Zoho total, or nothing is kept.
                if (abs($total - $header['invoice_total']) > 0.01) {
                    throw new RuntimeException("Invoice {$header['invoice_number']}: ERP total {$total} does not match the Zoho total {$header['invoice_total']}. Nothing was imported.");
                }
                $invoice->update([
                    'subtotal' => round((float) $items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price), 2),
                    'discount_amount' => round((float) $items->sum('discount_amount'), 2),
                    'tax_amount' => round((float) $items->sum('tax_amount'), 2),
                    'total_amount' => $total,
                    'balance_amount' => $total,
                    'base_amount' => round($total * $header['exchange_rate'], 2),
                ]);

                if ($header['action'] === 'post') {
                    $this->postJournal($invoice, $header, $accountTotals, $discount, $receivable, $customer->id, $actorId, $batch->tenant_id);
                    $counts['journal_entries']++;
                    $counts['posted']++;
                } else {
                    $counts['drafts']++;
                }

                if ($quotation && !$quotation->invoice_id) {
                    $quotation->update(['invoice_id' => $invoice->id, 'converted_to_invoice' => true]);
                    $counts['linked_quotations']++;
                }
            }

            $summary = $counts + ['skipped_in_zoho_invoices' => $skippedInZoho];
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
                'stage_key' => 'sales_invoices',
                'event_type' => 'stage_imported',
                'message' => "Sales invoices import completed: {$counts['posted']} posted with journal entries, {$counts['drafts']} drafts, {$counts['lines']} lines, {$counts['linked_quotations']} quotations linked"
                    .($counts['skipped_existing'] ? ", {$counts['skipped_existing']} already in ERP" : '')
                    .($skippedInZoho ? '. Not imported: '.implode(', ', $skippedInZoho) : '').'.',
                'metadata' => $summary,
            ]);

            return $summary;
        });
    }

    /**
     * Debit the receivable account for the invoice total and Zoho's Discount account for the item discounts, and
     * credit each Zoho revenue account for its lines at full price, in the base currency, as Zoho posts invoices.
     * Per-account rounding differences (foreign currency only) go on the largest credit so the entry always balances.
     */
    private function postJournal(SalesInvoice $invoice, array $header, array $accountTotals, float $discount, ChartOfAccount $receivable, int $customerId, int $actorId, int $tenantId): void
    {
        $rate = (float) $header['exchange_rate'] ?: 1.0;
        $credits = ZohoJournalWriter::toBase($accountTotals, $rate, (float) $invoice->total_amount + $discount);
        $receivableBase = round((float) $invoice->total_amount * $rate, 2);
        $discountBase = round(array_sum($credits) - $receivableBase, 2);

        $lines = [['account' => $receivable, 'debit' => $receivableBase, 'credit' => 0.0, 'description' => 'Invoice '.$invoice->invoice_number, 'contact_id' => $customerId]];
        if ($discountBase > 0) {
            $lines[] = ['account' => $this->discountAccount($tenantId), 'debit' => $discountBase, 'credit' => 0.0, 'description' => 'Item discounts on invoice '.$invoice->invoice_number];
        }
        foreach ($credits as $accountId => $amount) {
            $lines[] = ['account' => ChartOfAccount::findOrFail($accountId), 'debit' => 0.0, 'credit' => $amount, 'description' => 'Service revenue'];
        }

        $this->journals->post([
            'journal_number' => $this->journalNumber($header, $tenantId),
            'journal_date' => $header['invoice_date'],
            'reference_type' => 'service_invoice',
            'reference_id' => $invoice->id,
            'description' => 'Service Invoice #'.$invoice->invoice_number.' (imported from Zoho)',
            'currency_code' => $header['currency_code'],
            'exchange_rate' => $rate,
            'foreign_amount' => (float) $invoice->total_amount,
        ], $lines, $actorId, $tenantId);
    }

    private function journalNumber(array $row, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $row['zoho_invoice_id'], 'INV-'.$row['invoice_number'], $row['invoice_date']);
    }

    private function revenueAccount(array $row, int $tenantId): ?ChartOfAccount
    {
        $query = ChartOfAccount::where('created_by', $tenantId);

        return $row['revenue_account_code'] !== ''
            ? (clone $query)->where('account_code', $row['revenue_account_code'])->first()
            : ($row['revenue_account_name'] !== '' ? (clone $query)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($row['revenue_account_name'])])->first() : null);
    }

    private function receivableAccount(int $tenantId): ?ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', self::RECEIVABLE_ACCOUNT_CODE)->first();
    }

    private function header(array $row): array
    {
        return [$row['invoice_date'], $row['due_date'], mb_strtolower($row['customer']), $row['action'], $row['currency_code'], $row['exchange_rate'], $row['invoice_total']];
    }

    private function notes(string $notes, string $terms, string $zohoStatus, ?string $action, bool $noDueDate): ?string
    {
        $parts = array_filter([
            $notes,
            $terms !== '' ? "Terms & Conditions:\n{$terms}" : '',
            $action !== null ? 'Zoho status: '.ucfirst(mb_strtolower($zohoStatus)) : '',
            $noDueDate ? 'No due date in Zoho; due date set to the invoice date.' : '',
        ]);

        return $parts ? implode("\n\n", $parts) : null;
    }

    private function existing(string $number, int $tenantId): ?SalesInvoice
    {
        return SalesInvoice::where('created_by', $tenantId)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($number)])->first();
    }
}

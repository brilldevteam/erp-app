<?php

namespace App\Services\ZohoMigration;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use App\Services\ZohoMigration\Concerns\ResolvesZohoBankAccounts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteApplication;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\DebitNoteApplication;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\VendorPaymentAllocation;

/**
 * Closes the credit notes and vendor credits imported open in steps 14/15 the way Zoho used them.
 *
 * Sources: the Zoho Refund History report and, when needed, a credit split sheet (uploaded here); the credit
 * note, vendor credit, invoice and bill exports already uploaded in steps 12–15 (their Zoho balances and
 * "Applied Invoice Number" / "Associated Bill Number"); and the ERP documents.
 *
 * - Refunds post Dr receivables 1100 / Cr the refund bank account, with the bank register line.
 * - What each credit gave each invoice: rows of the split sheet first, then worked out — a credit with one open
 *   invoice left gives it what it has left, an invoice with one credit left takes what it still needs from Zoho —
 *   until nothing changes. A credit that cannot be worked out is reported, never guessed.
 * - Applications are recorded as the ERP records them: a zero-cash payment (CNA-<credit note> / DNA-<debit note>)
 *   allocating the credit to the invoice or bill, with the credit's application row. No journal: the credit's own
 *   journal already reduced receivables or payables.
 */
class ZohoCreditApplicationService
{
    use ReadsZohoDocuments;
    use ResolvesZohoBankAccounts;

    /** @var array<int, array> */
    private array $planCache = [];

    /** @var array<int, array<string, string>> Zoho account id → account code, per batch. */
    private array $zohoCodes = [];

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoJournalWriter $journals,
        private readonly ZohoVendorCreditService $vendorCredits,
    ) {
    }

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $source = $this->normalized($values);
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);

        if (array_key_exists('refund id', $source)) {
            $zohoAccountId = $get('refund from account id');

            return [
                'include' => true,
                'kind' => 'refund',
                'refund_id' => $get('refund id'),
                'entity_type' => mb_strtolower($get('entity type')),
                'credit_number' => $get('entity number'),
                'partner' => $get('customer name'),
                'date' => $this->date($get('date'), (string) $batch->source_date_format),
                'date_source' => $get('date'),
                'mode' => $get('refund mode'),
                'reference' => $get('reference number'),
                'description' => $get('description'),
                'account_name' => $get('refund from account name'),
                'account_code' => $this->zohoCodes($batch)[$zohoAccountId] ?? '',
                'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
                'amount' => $amount('bcy amount'),
            ];
        }

        return [
            'include' => true,
            'kind' => 'split',
            'credit_number' => $get('credit note number') ?: $get('credit note id'),
            'invoice_number' => $get('invoice number') ?: $get('invoice id'),
            'amount' => $amount(array_key_exists('amount credited', $source) ? 'amount credited' : (array_key_exists('amount applied', $source) ? 'amount applied' : 'applied amount')),
            'date' => $get('applied date') !== '' ? $this->date($get('applied date'), (string) $batch->source_date_format) : null,
            'date_source' => $get('applied date'),
        ];
    }

    public function context(iterable $preparedRows): array
    {
        $rows = [];
        // Keys are "<file id>:<row>" when the stage has several files; refund and split rows come from different files.
        foreach ($preparedRows as $key => $row) {
            $rows[] = ['row' => (int) (str_contains((string) $key, ':') ? substr((string) $key, strrpos((string) $key, ':') + 1) : $key)] + $row;
        }

        return ['rows' => $rows, 'first' => $rows ? [$rows[0]['kind'], $rows[0]['row']] : null];
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $plan = $this->planCache[$batch->id] ??= $this->plan($batch, $context['rows']);
        $issue = fn (string $message, ?string $value) => [
            'type' => 'invalid_credit_application',
            'field' => $value === null ? null : 'credit_note_number',
            'source_value' => $value,
            'message' => $message,
            'resolution_url' => Route::has('account.credit-notes.index') ? route('account.credit-notes.index') : null,
        ];
        $issues = array_map(fn (string $message) => $issue($message, $row['credit_number']), array_values(array_unique($plan['row_problems'][$row['kind'].'|'.$sourceRowNumber] ?? [])));
        // Problems of the whole set of credits are reported once, on the first row, without that row's number.
        if ($context['first'] === [$row['kind'], $sourceRowNumber]) {
            $issues = array_merge($issues, array_map(fn (string $message) => $issue($message, null), array_values(array_unique($plan['problems']))));
        }

        return $issues;
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'credit_applications')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before applying credits.');
        }
        $rows = $batch->files()->where('stage_key', 'credit_applications')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException('Upload the Zoho Refund History report (and the credit split sheet if needed) first.');
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = $rows->map(fn ($row) => ['row' => (int) $row->source_row_number] + $this->prepare($row->original_values, $batch))->all();
        $plan = $this->plan($batch, $prepared);
        $problems = array_merge($plan['problems'], ...array_values($plan['row_problems']));
        if ($problems) {
            throw new RuntimeException(implode(' ', array_unique($problems)).' Upload the files again to re-check them.');
        }

        return DB::transaction(function () use ($plan, $rows, $batch, $actorId, $stage): array {
            $counts = ['credit_notes_applied' => 0, 'invoice_applications' => 0, 'credited_to_invoices' => 0.0, 'refunds' => 0, 'refunded' => 0.0, 'vendor_credits_applied' => 0, 'credited_to_bills' => 0.0, 'already_applied' => count($plan['existing'])];
            $bank = $this->applicationBank($batch->tenant_id);

            foreach ($plan['refunds'] as $refund) {
                $this->refund($refund, $actorId, $batch->tenant_id);
                $counts['refunds']++;
                $counts['refunded'] = round($counts['refunded'] + $refund['amount'], 2);
            }
            foreach ($plan['customer'] as $application) {
                $this->applyCreditNote($application, $bank, $actorId, $batch->tenant_id);
                $counts['credit_notes_applied']++;
                $counts['invoice_applications'] += count($application['invoices']);
                $counts['credited_to_invoices'] = round($counts['credited_to_invoices'] + array_sum(array_column($application['invoices'], 'amount')), 2);
            }
            foreach ($plan['vendor'] as $application) {
                $this->applyDebitNote($application, $bank, $actorId, $batch->tenant_id);
                $counts['vendor_credits_applied']++;
                $counts['credited_to_bills'] = round($counts['credited_to_bills'] + $application['amount'], 2);
            }

            // Every credit, invoice and bill touched must now carry exactly its Zoho balance.
            foreach ($plan['expected'] as [$model, $id, $zohoBalance, $label]) {
                $balance = (float) $model::findOrFail($id)->balance_amount;
                if (abs($balance - $zohoBalance) > 0.005) {
                    throw new RuntimeException("{$label} would have ".number_format($balance, 2).' open in ERP but '.number_format($zohoBalance, 2).' in Zoho. Nothing was imported.');
                }
            }

            foreach ($rows as $row) {
                $row->update(['status' => 'imported']);
            }
            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $counts]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'credit_applications',
                'event_type' => 'stage_imported',
                'message' => "Credit applications completed: {$counts['credit_notes_applied']} credit notes applied to {$counts['invoice_applications']} invoices (".number_format($counts['credited_to_invoices'], 2).")"
                    ."; {$counts['refunds']} refunds (".number_format($counts['refunded'], 2).')'
                    ."; {$counts['vendor_credits_applied']} vendor credits applied to bills (".number_format($counts['credited_to_bills'], 2).')'
                    .($counts['already_applied'] ? "; {$counts['already_applied']} credits already applied in ERP" : '').'.',
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    /**
     * Works out every refund and application, and every problem, without writing anything.
     */
    private function plan(ZohoMigrationBatch $batch, array $rows): array
    {
        $tenantId = $batch->tenant_id;
        $plan = ['refunds' => [], 'customer' => [], 'vendor' => [], 'expected' => [], 'existing' => [], 'problems' => [], 'row_problems' => []];
        $rowProblem = function (array $row, string $message) use (&$plan): void {
            $plan['row_problems'][$row['kind'].'|'.$row['row']][] = $message;
        };

        $zohoCredits = $this->zohoDocuments($batch, 'credit_notes', 'credit note number');
        $zohoInvoices = $this->zohoDocuments($batch, 'sales_invoices', 'invoice number');
        $zohoVendorCredits = $this->zohoDocuments($batch, 'vendor_credits', 'vendor credit number');
        $zohoBills = $this->zohoDocuments($batch, 'purchase_invoices', 'bill number', 'vendor name');
        if (!$zohoCredits && !$zohoVendorCredits) {
            $plan['problems'][] = 'No credit note or vendor credit export is uploaded in the Credit notes and Vendor credits steps of this migration.';
        }

        // Customer credit notes: what is left of each to apply, after refunds and what Zoho still shows open.
        $credits = [];
        foreach ($zohoCredits as $key => $zoho) {
            if (in_array(mb_strtolower($zoho['credit note status'] ?? ''), ['draft', 'void'], true)) {
                continue;
            }
            $note = CreditNote::where('created_by', $tenantId)->whereRaw('LOWER(credit_note_number) = ?', [$key])->first();
            if (!$note) {
                $plan['problems'][] = "Credit note {$zoho['credit note number']} is not in ERP. Complete the Credit notes step first.";
                continue;
            }
            if ((float) $note->applied_amount > 0) {
                $plan['existing'][] = $note->credit_note_number;
                continue;
            }
            $credits[$key] = [
                'note' => $note,
                'zoho_balance' => round((float) ($zoho['balance'] ?? 0), 2),
                'targets' => array_values(array_filter(array_map('trim', explode(',', $zoho['applied invoice number'] ?? '')))),
                'refunded' => 0.0,
                'given' => [],
                'date' => $this->date($zoho['credit note date'] ?? '', (string) $batch->source_date_format) ?? $note->getRawOriginal('credit_note_date'),
            ];
        }

        foreach ($rows as $row) {
            if ($row['kind'] !== 'refund') {
                continue;
            }
            if ($row['entity_type'] !== 'creditnote') {
                $rowProblem($row, "Refunds of {$row['entity_type']} records are not supported by this import yet.");
                continue;
            }
            if (!$row['date']) {
                $rowProblem($row, "Date '{$row['date_source']}' is not a valid date.");
            }
            if ($row['currency_code'] !== strtoupper((string) $batch->base_currency)) {
                $rowProblem($row, 'Foreign-currency refunds are not supported by this import yet.');
            }
            $credit = $credits[mb_strtolower($row['credit_number'])] ?? null;
            $bank = $this->bankAccount($row, $tenantId);
            if (!$credit) {
                $rowProblem($row, in_array($row['credit_number'], $plan['existing'], true)
                    ? "Credit note {$row['credit_number']} is already applied in ERP; its refund was imported before."
                    : "Credit note {$row['credit_number']} is not an open imported credit note in ERP. Complete the Credit notes step first.");
                continue;
            }
            if (mb_strtolower($credit['note']->customer?->name ?? '') !== mb_strtolower($row['partner'])) {
                $rowProblem($row, "Credit note {$row['credit_number']} belongs to ".($credit['note']->customer?->name ?? 'another customer')." in ERP, not {$row['partner']}.");
            }
            if (!$bank) {
                $rowProblem($row, "Refund account '{$row['account_name']}' is not a bank account in ERP. Complete the Bank accounts step first.");
                continue;
            }
            if (JournalEntry::where('journal_number', $this->refundJournalNumber($row, $tenantId))->exists()) {
                $rowProblem($row, "This refund of {$row['credit_number']} is already in ERP.");
                continue;
            }
            $credits[mb_strtolower($row['credit_number'])]['refunded'] = round($credits[mb_strtolower($row['credit_number'])]['refunded'] + $row['amount'], 2);
            $plan['refunds'][] = $row + ['note_id' => $credit['note']->id, 'customer_id' => $credit['note']->customer_id, 'bank' => $bank];
        }

        // What each credit has left to give invoices, and what each named invoice still needs from credits.
        $remaining = [];
        $needs = [];
        $invoices = [];
        foreach ($credits as $key => $credit) {
            $remaining[$key] = round((float) $credit['note']->balance_amount - $credit['zoho_balance'] - $credit['refunded'], 2);
            if ($remaining[$key] < -0.005) {
                $plan['problems'][] = "Credit note {$credit['note']->credit_note_number} is refunded more than it has open.";
            }
            foreach ($credit['targets'] as $number) {
                $invoiceKey = mb_strtolower($number);
                if (isset($needs[$invoiceKey])) {
                    continue;
                }
                $invoice = SalesInvoice::where('created_by', $tenantId)->where('customer_id', $credit['note']->customer_id)->whereRaw('LOWER(invoice_number) = ?', [$invoiceKey])->first();
                if (!$invoice || !isset($zohoInvoices[$invoiceKey])) {
                    $plan['problems'][] = "Invoice {$number} of credit note {$credit['note']->credit_note_number} is not in ERP for the same customer, or not in the uploaded Zoho invoice export.";
                    continue;
                }
                $invoices[$invoiceKey] = $invoice;
                $needs[$invoiceKey] = round((float) $invoice->balance_amount - (float) ($zohoInvoices[$invoiceKey]['balance'] ?? 0), 2);
            }
        }
        $give = function (string $creditKey, string $invoiceKey, float $amount, ?string $date = null) use (&$remaining, &$needs, &$credits): void {
            $credits[$creditKey]['given'][$invoiceKey] = round(($credits[$creditKey]['given'][$invoiceKey] ?? 0) + $amount, 2);
            $credits[$creditKey]['split_date'] ??= $date;
            $remaining[$creditKey] = round($remaining[$creditKey] - $amount, 2);
            $needs[$invoiceKey] = round($needs[$invoiceKey] - $amount, 2);
        };

        // 1. The split sheet.
        foreach ($rows as $row) {
            if ($row['kind'] !== 'split') {
                continue;
            }
            $creditKey = mb_strtolower($row['credit_number']);
            $invoiceKey = mb_strtolower($row['invoice_number']);
            if (!isset($credits[$creditKey])) {
                $rowProblem($row, "Credit note {$row['credit_number']} is not an open imported credit note in ERP.");
            } elseif (!in_array($invoiceKey, array_map('mb_strtolower', $credits[$creditKey]['targets']), true) || !isset($needs[$invoiceKey])) {
                $rowProblem($row, "Zoho does not list invoice {$row['invoice_number']} as credited by {$row['credit_number']}.");
            } elseif ($row['amount'] <= 0) {
                $rowProblem($row, 'Amount Credited must be greater than zero.');
            } elseif ($row['amount'] - $remaining[$creditKey] > 0.005 || $row['amount'] - $needs[$invoiceKey] > 0.005) {
                $rowProblem($row, 'Amount Credited '.number_format($row['amount'], 2)." is more than {$row['credit_number']} has left (".number_format($remaining[$creditKey], 2).") or {$row['invoice_number']} still needs (".number_format($needs[$invoiceKey], 2).').');
            } elseif ($row['date_source'] !== '' && !$row['date']) {
                $rowProblem($row, "Applied Date '{$row['date_source']}' is not a valid date.");
            } else {
                $give($creditKey, $invoiceKey, $row['amount'], $row['date']);
            }
        }

        // 2. Work out the rest while exactly one answer exists.
        do {
            $changed = false;
            foreach ($credits as $creditKey => $credit) {
                if ($remaining[$creditKey] <= 0.005) {
                    continue;
                }
                $open = array_values(array_filter(array_map('mb_strtolower', $credit['targets']), fn ($key) => ($needs[$key] ?? 0) > 0.005));
                if (count($open) === 1) {
                    $give($creditKey, $open[0], min($remaining[$creditKey], $needs[$open[0]]));
                    $changed = true;
                }
            }
            foreach ($needs as $invoiceKey => $need) {
                if ($need <= 0.005) {
                    continue;
                }
                $givers = array_keys(array_filter($credits, fn ($credit, $creditKey) => $remaining[$creditKey] > 0.005 && in_array($invoiceKey, array_map('mb_strtolower', $credit['targets']), true), ARRAY_FILTER_USE_BOTH));
                if (count($givers) === 1) {
                    $give($givers[0], $invoiceKey, min($remaining[$givers[0]], $need));
                    $changed = true;
                }
            }
        } while ($changed);

        foreach ($credits as $creditKey => $credit) {
            $number = $credit['note']->credit_note_number;
            if ($remaining[$creditKey] > 0.005) {
                $plan['problems'][] = $credit['targets']
                    ? "Credit note {$number} has ".number_format($remaining[$creditKey], 2).' to apply to '.implode(', ', $credit['targets']).' and the split cannot be worked out. Add its Credit Applied Invoices rows (Credit Note Number, Invoice Number, Amount Credited) to the credit split sheet.'
                    : "Credit note {$number} has ".number_format($remaining[$creditKey], 2).' left that Zoho neither applied to an invoice nor refunded. Upload the Zoho Refund History report.';
            }
            if ($credit['given']) {
                $plan['customer'][] = [
                    'note_id' => $credit['note']->id, 'number' => $number, 'customer_id' => $credit['note']->customer_id,
                    'date' => $credit['split_date'] ?? $credit['date'],
                    'invoices' => array_map(fn ($invoiceKey, $amount) => ['invoice_id' => $invoices[$invoiceKey]->id, 'amount' => $amount], array_keys($credit['given']), $credit['given']),
                ];
            }
            if ($credit['given'] || $credit['refunded'] > 0) {
                $plan['expected'][] = [CreditNote::class, $credit['note']->id, $credit['zoho_balance'], "Credit note {$number}"];
            }
            if (CustomerPayment::where('created_by', $tenantId)->where('payment_number', 'CNA-'.$number)->exists()) {
                $plan['problems'][] = "Payment CNA-{$number} already exists in ERP; this credit note may have been applied before.";
            }
        }
        foreach ($needs as $invoiceKey => $need) {
            if ($need > 0.005) {
                $plan['problems'][] = "Invoice {$invoices[$invoiceKey]->invoice_number} still has ".number_format($need, 2).' open in ERP that Zoho closed with credit notes.';
            }
            $plan['expected'][] = [SalesInvoice::class, $invoices[$invoiceKey]->id, round((float) ($zohoInvoices[$invoiceKey]['balance'] ?? 0), 2), "Invoice {$invoices[$invoiceKey]->invoice_number}"];
        }

        // Vendor credits: each closes against its one associated bill.
        foreach ($zohoVendorCredits as $zoho) {
            if (in_array(mb_strtolower($zoho['vendor credit status'] ?? ''), ['draft', 'void'], true)) {
                continue;
            }
            $note = $this->vendorCredits->findImported($zoho['vendor credit number'], $tenantId);
            if (!$note) {
                $plan['problems'][] = "Vendor credit {$zoho['vendor credit number']} is not in ERP. Complete the Vendor credits step first.";
                continue;
            }
            if ((float) $note->applied_amount > 0) {
                $plan['existing'][] = $note->debit_note_number;
                continue;
            }
            $amount = round((float) $note->balance_amount - (float) ($zoho['balance'] ?? 0), 2);
            if ($amount <= 0.005) {
                continue;
            }
            $billNumber = trim((string) ($zoho['associated bill number'] ?? ''));
            $vendorName = mb_strtolower((string) $note->vendor?->name);
            $bill = $billNumber !== '' ? PurchaseInvoice::where('created_by', $tenantId)->where('vendor_id', $note->vendor_id)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($billNumber)])->first() : null;
            $zohoBill = $zohoBills[$vendorName.'|'.mb_strtolower($billNumber)] ?? null;
            if (!$bill || !$zohoBill) {
                $plan['problems'][] = "Vendor credit {$zoho['vendor credit number']} has ".number_format($amount, 2).' to apply but its bill'.($billNumber !== '' ? " {$billNumber}" : '').' is not in ERP and the Zoho bills export for the same vendor.';
                continue;
            }
            $billNeeds = round((float) $bill->balance_amount - (float) ($zohoBill['balance'] ?? 0), 2);
            if ($amount - $billNeeds > 0.005) {
                $plan['problems'][] = "Vendor credit {$zoho['vendor credit number']} gives ".number_format($amount, 2)." but bill {$billNumber} needs only ".number_format($billNeeds, 2).' to match Zoho.';
                continue;
            }
            if (VendorPayment::where('created_by', $tenantId)->where('payment_number', 'DNA-'.$note->debit_note_number)->exists()) {
                $plan['problems'][] = "Payment DNA-{$note->debit_note_number} already exists in ERP; this vendor credit may have been applied before.";
            }
            $plan['vendor'][] = ['note_id' => $note->id, 'number' => $note->debit_note_number, 'vendor_id' => $note->vendor_id, 'bill_id' => $bill->id, 'bill_number' => $bill->invoice_number, 'amount' => $amount, 'date' => $note->getRawOriginal('debit_note_date')];
            $plan['expected'][] = [DebitNote::class, $note->id, round((float) ($zoho['balance'] ?? 0), 2), "Vendor credit {$note->debit_note_number}"];
            $plan['expected'][] = [PurchaseInvoice::class, $bill->id, round((float) ($zohoBill['balance'] ?? 0), 2), "Bill {$bill->invoice_number}"];
        }

        if (($plan['customer'] || $plan['vendor']) && !$this->applicationBank($tenantId)) {
            $plan['problems'][] = 'No ERP bank account exists to hold the zero-cash credit application payments. Complete the Bank accounts step first.';
        }

        return $plan;
    }

    private function refund(array $refund, int $actorId, int $tenantId): void
    {
        $note = CreditNote::lockForUpdate()->findOrFail($refund['note_id']);
        $receivable = ChartOfAccount::where('created_by', $tenantId)->where('account_code', ZohoSalesInvoiceService::RECEIVABLE_ACCOUNT_CODE)->firstOrFail();
        $this->journals->post([
            'journal_number' => $this->refundJournalNumber($refund, $tenantId),
            'journal_date' => $refund['date'],
            'reference_type' => 'credit_note_refund',
            'reference_id' => $note->id,
            'description' => "Credit Note {$note->credit_note_number} refund (imported from Zoho)",
            'currency_code' => $refund['currency_code'],
            'exchange_rate' => 1,
            'foreign_amount' => $refund['amount'],
        ], [
            ['account' => $receivable, 'debit' => $refund['amount'], 'credit' => 0.0, 'description' => "Refund of {$note->credit_note_number} - {$refund['partner']}", 'contact_id' => $note->customer_id],
            ['account' => $refund['bank']->glAccount, 'debit' => 0.0, 'credit' => $refund['amount'], 'description' => "Refund paid - {$refund['partner']}"],
        ], $actorId, $tenantId);
        $this->recordBankMovement($refund['bank'], 'debit', $refund['date'], $note->credit_note_number, "Credit Note Refund {$note->credit_note_number} - {$refund['partner']}", $refund['amount'], $tenantId);

        $note->applied_amount = round((float) $note->applied_amount + $refund['amount'], 2);
        $note->balance_amount = round((float) $note->total_amount - (float) $note->applied_amount, 2);
        $note->status = $note->balance_amount <= 0 ? 'applied' : 'partial';
        $note->notes = trim(($note->notes ? $note->notes."\n" : '')."Refunded in Zoho on {$refund['date']}: ".number_format($refund['amount'], 2)." from {$refund['account_name']}"
            .($refund['mode'] !== '' ? " ({$refund['mode']})" : '').($refund['description'] !== '' ? " - {$refund['description']}" : '').($refund['reference'] !== '' ? " (Ref: {$refund['reference']})" : '').'.');
        $note->save();
    }

    private function applyCreditNote(array $application, BankAccount $bank, int $actorId, int $tenantId): void
    {
        $total = round(array_sum(array_column($application['invoices'], 'amount')), 2);
        $payment = CustomerPayment::create([
            'payment_number' => 'CNA-'.$application['number'],
            'payment_date' => $application['date'],
            'customer_id' => $application['customer_id'],
            'bank_account_id' => $bank->id,
            'payment_amount' => 0,
            'currency_code' => $bank->currency_code ?: null,
            'exchange_rate' => 1,
            'base_amount' => 0,
            'status' => 'cleared',
            'needs_bank_verification' => false,
            'notes' => "Credit note {$application['number']} applied to invoices as in Zoho; no money moved.",
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        foreach ($application['invoices'] as $line) {
            CustomerPaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $line['invoice_id'], 'allocated_amount' => $line['amount'], 'creator_id' => $actorId, 'created_by' => $tenantId]);
            $this->settle(SalesInvoice::lockForUpdate()->findOrFail($line['invoice_id']), $line['amount']);
        }
        CreditNoteApplication::create(['credit_note_id' => $application['note_id'], 'payment_id' => $payment->id, 'applied_amount' => $total, 'application_date' => $application['date'], 'creator_id' => $actorId, 'created_by' => $tenantId]);

        $note = CreditNote::lockForUpdate()->findOrFail($application['note_id']);
        $note->applied_amount = round((float) $note->applied_amount + $total, 2);
        $note->balance_amount = round((float) $note->total_amount - (float) $note->applied_amount, 2);
        $note->status = $note->balance_amount <= 0 ? 'applied' : 'partial';
        $note->save();
    }

    private function applyDebitNote(array $application, BankAccount $bank, int $actorId, int $tenantId): void
    {
        $payment = VendorPayment::create([
            'payment_number' => 'DNA-'.$application['number'],
            'payment_date' => $application['date'],
            'vendor_id' => $application['vendor_id'],
            'bank_account_id' => $bank->id,
            'payment_amount' => 0,
            'currency_code' => $bank->currency_code ?: null,
            'exchange_rate' => 1,
            'base_amount' => 0,
            'status' => 'cleared',
            'needs_bank_verification' => false,
            'notes' => "Vendor credit {$application['number']} applied to bill {$application['bill_number']} as in Zoho; no money moved.",
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        VendorPaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $application['bill_id'], 'allocated_amount' => $application['amount']]);
        $this->settle(PurchaseInvoice::lockForUpdate()->findOrFail($application['bill_id']), $application['amount']);
        DebitNoteApplication::create(['debit_note_id' => $application['note_id'], 'payment_id' => $payment->id, 'applied_amount' => $application['amount'], 'application_date' => $application['date'], 'creator_id' => $actorId, 'created_by' => $tenantId]);

        $note = DebitNote::lockForUpdate()->findOrFail($application['note_id']);
        $note->applied_amount = round((float) $note->applied_amount + $application['amount'], 2);
        $note->balance_amount = round((float) $note->total_amount - (float) $note->applied_amount, 2);
        $note->status = $note->balance_amount <= 0 ? 'applied' : 'partial';
        $note->save();
    }

    /** As the ERP payment screens settle an invoice or bill. */
    private function settle($document, float $amount): void
    {
        $document->paid_amount = round((float) $document->paid_amount + $amount, 2);
        $document->balance_amount = max(0, round((float) $document->total_amount - (float) $document->paid_amount, 2));
        $document->status = $document->balance_amount <= 0 ? 'paid' : 'partial';
        $document->save();
    }

    /** Zero-cash application payments need a bank account; the main bank account holds them. */
    private function applicationBank(int $tenantId): ?BankAccount
    {
        return BankAccount::where('created_by', $tenantId)->where('account_type', '!=', 'clearing_account')
            ->orderByRaw("account_type = 'checking' DESC")->orderBy('id')->first();
    }

    private function refundJournalNumber(array $refund, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $refund['refund_id'], 'REFUND-'.$refund['credit_number'], $refund['date']);
    }

    /**
     * The Zoho values of an earlier step's export, one per document, keyed by its (lower-case) number —
     * prefixed by the partner name when given, since vendors number their own bills.
     */
    private function zohoDocuments(ZohoMigrationBatch $batch, string $stageKey, string $numberColumn, ?string $partnerColumn = null): array
    {
        $documents = [];
        foreach ($batch->files()->where('stage_key', $stageKey)->where('status', 'staged')->get() as $file) {
            foreach ($file->stagedRows()->orderBy('source_row_number')->get(['original_values']) as $row) {
                $values = $this->normalized((array) $row->original_values);
                $number = trim((string) ($values[$numberColumn] ?? ''));
                if ($number === '') {
                    continue;
                }
                $key = ($partnerColumn ? mb_strtolower(trim((string) ($values[$partnerColumn] ?? ''))).'|' : '').mb_strtolower($number);
                $documents[$key] ??= $values;
            }
        }

        return $documents;
    }

    private function normalized(array $values): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] ??= is_scalar($value) ? trim((string) $value) : '';
        }

        return $source;
    }

    /** Zoho account id → account code, from the migration's Chart of Accounts export (step 2). */
    private function zohoCodes(ZohoMigrationBatch $batch): array
    {
        return $this->zohoCodes[$batch->id] ??= $batch->files()->where('stage_key', 'chart_of_accounts')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->get(['original_values']))
            ->mapWithKeys(function ($row) {
                $values = $this->normalized((array) $row->original_values);

                return [($values['account id'] ?? '') => ($values['account code'] ?? '')];
            })
            ->filter(fn (string $code, $id) => (string) $id !== '' && $code !== '')
            ->all();
    }
}

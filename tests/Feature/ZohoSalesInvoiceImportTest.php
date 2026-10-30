<?php

namespace Tests\Feature;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoSalesInvoiceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Quotation\Models\SalesQuotation;

class ZohoSalesInvoiceImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_live_invoices_post_balanced_journals_and_stay_unpaid_drafts_post_nothing_and_void_is_skipped(): void
    {
        [$company, $batch] = $this->invoicesBatch(['Novera Interiors', 'Takeaway']);
        $quote = SalesQuotation::create(['quotation_number' => 'EST-000070', 'quotation_date' => '2022-07-01', 'due_date' => '2022-07-01', 'customer_id' => User::where('created_by', $company->id)->where('name', 'Takeaway')->value('id'), 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'status' => 'accepted', 'converted_to_invoice' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            // Invoice-level discount of 550 spread by Zoho over lines on two revenue accounts (6840 + 5520 - 550 = 11810).
            $this->line('INV-1', 'Novera Interiors', 'Closed', ['Item Name' => 'Kiosk Branding', 'Item Price' => '6840', 'Item Total' => '6535.63', 'Total' => '11810', 'Balance' => '0', 'Account' => 'Sales - Branding', 'Account Code' => '4010102']),
            $this->line('INV-1', 'Novera Interiors', 'Closed', ['Item Name' => '', 'Item Desc' => 'Installation', 'Item Price' => '5520', 'Item Total' => '5274.37', 'Total' => '11810', 'Balance' => '0', 'Account' => 'Other Income', 'Account Code' => '6010101']),
            $this->line('INV-2', 'Takeaway', 'Overdue', ['Estimate Number' => 'EST-000070']),
            // A second invoice from the same Zoho estimate (the ERP links a quotation to one invoice only).
            $this->line('INV-2B', 'Takeaway', 'Closed', ['Estimate Number' => 'EST-000070']),
            $this->line('INV-3', 'Takeaway', 'Draft'),
            $this->line('INV-4', 'Takeaway', 'Void'),
            $this->line('INV-5', 'Takeaway', 'Approved'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'sales_invoices', $company->id);
        $this->assertSame(['accepted' => 5, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $this->assertSame(['skipped', 'skipped'], ZohoMigrationStagedRow::where('batch_id', $batch->id)->whereIn('source_row_number', [7, 8])->pluck('status')->all());

        $summary = app(ZohoSalesInvoiceService::class)->import($batch, $company->id);

        $this->assertSame(['posted' => 3, 'drafts' => 1, 'skipped_existing' => 0, 'lines' => 5, 'journal_entries' => 3, 'linked_quotations' => 1, 'skipped_in_zoho' => 2], array_diff_key($summary, ['skipped_in_zoho_invoices' => 1]));
        $this->assertSame(['INV-4 (Void in Zoho)', 'INV-5 (Approved internally but never posted in Zoho)'], $summary['skipped_in_zoho_invoices']);

        $first = SalesInvoice::where('created_by', $company->id)->where('invoice_number', 'INV-1')->firstOrFail();
        $this->assertSame(['11810.00', '0.00', '11810.00', 'posted', 'service'], [(string) $first->total_amount, (string) $first->paid_amount, (string) $first->balance_amount, $first->status, $first->type]);
        $this->assertSame($this->account($company->id, '4010102')->id, $first->revenue_account_id, 'The largest sales revenue account is the invoice revenue account.');
        $journal = JournalEntry::where('reference_type', 'service_invoice')->where('reference_id', $first->id)->with('items')->sole();
        // As Zoho posts it: revenue at full price, the discount on Zoho's Discount account.
        $this->assertSame(["ZOHO-{$company->id}-ID-INV-1-20220705", 'posted', '12360.00', '12360.00'], [$journal->journal_number, $journal->status, (string) $journal->total_debit, (string) $journal->total_credit]);
        $byAccount = $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount]])->all();
        $this->assertSame([
            $this->account($company->id, '1100')->id => ['11810.00', '0.00'],
            $this->account($company->id, '4020101')->id => ['550.00', '0.00'],
            $this->account($company->id, '4010102')->id => ['0.00', '6840.00'],
            $this->account($company->id, '6010101')->id => ['0.00', '5520.00'],
        ], $byAccount);
        $this->assertSame('-550.00', (string) $this->account($company->id, '4020101')->current_balance);
        $this->assertSame('13810.00', (string) $this->account($company->id, '1100')->current_balance, 'Receivable 1100 holds all three posted invoices (11810 + 1000 + 1000).');

        $second = SalesInvoice::where('created_by', $company->id)->where('invoice_number', 'INV-2')->firstOrFail();
        $this->assertSame([$quote->id, $second->id, true], [$second->quotation_id, $quote->fresh()->invoice_id, $quote->fresh()->converted_to_invoice]);
        $this->assertStringContainsString('Zoho status: Overdue', $second->notes);
        $secondFromSameQuote = SalesInvoice::where('created_by', $company->id)->where('invoice_number', 'INV-2B')->firstOrFail();
        $this->assertNull($secondFromSameQuote->quotation_id);
        $this->assertStringContainsString('Also created from quotation EST-000070, which is linked to invoice INV-2.', $secondFromSameQuote->notes);

        $draft = SalesInvoice::where('created_by', $company->id)->where('invoice_number', 'INV-3')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, JournalEntry::where('reference_type', 'service_invoice')->where('reference_id', $draft->id)->count());
        $this->assertSame(0, SalesInvoice::where('created_by', $company->id)->whereIn('invoice_number', ['INV-4', 'INV-5'])->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'sales_invoices')->value('status'));
    }

    public function test_foreign_currency_invoices_post_in_base_currency_and_still_balance(): void
    {
        [$company, $batch] = $this->invoicesBatch(['Feelix Solution PVT Ltd']);
        $this->stage($batch, [
            $this->line('INV-USD', 'Feelix Solution PVT Ltd', 'Closed', ['Currency Code' => 'USD', 'Exchange Rate' => '3.640998', 'Item Price' => '333.33', 'Item Total' => '333.33', 'Total' => '666.66']),
            $this->line('INV-USD', 'Feelix Solution PVT Ltd', 'Closed', ['Currency Code' => 'USD', 'Exchange Rate' => '3.640998', 'Item Price' => '333.33', 'Item Total' => '333.33', 'Total' => '666.66', 'Account' => 'Sales - Branding', 'Account Code' => '4010102']),
        ]);

        app(ZohoMigrationValidator::class)->validate($batch, 'sales_invoices', $company->id);
        app(ZohoSalesInvoiceService::class)->import($batch, $company->id);

        $invoice = SalesInvoice::where('created_by', $company->id)->where('invoice_number', 'INV-USD')->firstOrFail();
        $this->assertSame(['666.66', 'USD', '2427.31'], [(string) $invoice->total_amount, $invoice->currency_code, (string) $invoice->base_amount]);
        $journal = JournalEntry::where('reference_id', $invoice->id)->where('reference_type', 'service_invoice')->with('items')->sole();
        $this->assertSame(round((float) $journal->items->sum('debit_amount'), 2), round((float) $journal->items->sum('credit_amount'), 2));
        $this->assertSame('2427.31', (string) $journal->total_debit);
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->invoicesBatch(['Takeaway']);
        SalesInvoice::create(['invoice_number' => 'INV-OLD', 'invoice_date' => '2022-01-01', 'due_date' => '2022-01-01', 'customer_id' => User::where('created_by', $company->id)->value('id'), 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'balance_amount' => 0, 'status' => 'draft', 'type' => 'service', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->line('INV-10', 'Unknown Customer', 'Closed'),
            $this->line('INV-11', 'Takeaway', 'On Hold'),
            $this->line('INV-12', 'Takeaway', 'Closed', ['Account' => 'Missing Revenue', 'Account Code' => '4999999']),
            $this->line('INV-13', 'Takeaway', 'Closed', ['Total' => '999']),
            $this->line('INV-OLD', 'Takeaway', 'Closed'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'sales_invoices', $company->id);

        $this->assertSame(5, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'sales_invoices')->pluck('message')->all();
        $this->assertContains("Customer 'Unknown Customer' was not found in ERP. Complete the Customers step first.", $messages);
        $this->assertContains("Invoice Status 'On Hold' is not a known Zoho status.", $messages);
        $this->assertContains('Revenue account 4999999 (Missing Revenue) was not found in ERP. Complete the Chart of Accounts step first.', $messages);
        $this->assertContains('Line totals add up to 1,000.00 but the Zoho invoice total is 999.00.', $messages);
        $this->assertContains('An invoice with this number already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->count());
    }

    private function invoicesBatch(array $customers): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        foreach ($customers as $name) {
            User::factory()->create(['type' => 'client', 'name' => $name, 'created_by' => $company->id]);
        }
        $otherTypeId = DB::table('account_types')->value('id');
        // The invoice form only offers accounts of the company's own Sales Revenue (SR) type.
        $salesRevenueTypeId = DB::table('account_types')->insertGetId(['category_id' => DB::table('account_types')->where('code', 'SR')->value('category_id'), 'name' => 'Sales Revenue', 'code' => 'SR', 'normal_balance' => 'credit', 'is_active' => true, 'is_system_type' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        foreach ([['1100', 'Accounts Receivable', 'debit', $otherTypeId], ['4010101', 'Sales - Social Media Management', 'credit', $salesRevenueTypeId], ['4010102', 'Sales - Branding', 'credit', $salesRevenueTypeId], ['6010101', 'Other Income', 'credit', $otherTypeId], ['4020101', 'Discount', 'credit', $otherTypeId]] as [$code, $name, $balance, $typeId]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Sales invoice import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'sales_invoices', 'position' => 12, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function line(string $number, string $customer, string $status, array $extra = []): array
    {
        return $extra + [
            'Invoice Date' => '2022-07-05', 'Invoice ID' => 'ID-'.$number, 'Invoice Number' => $number, 'Invoice Status' => $status,
            'Customer Name' => $customer, 'Due Date' => '2022-07-05', 'Currency Code' => 'QAR', 'Exchange Rate' => '1',
            'Discount Type' => 'item_level', 'Total' => '1000', 'Balance' => '1000', 'Notes' => '', 'Terms & Conditions' => '',
            'Payment Terms Label' => 'Due on Receipt', 'Subject' => '', 'Estimate Number' => '', 'Item Name' => 'Videography',
            'Item Desc' => '', 'Quantity' => '1', 'Item Total' => '1000', 'Item Price' => '1000',
            'Account' => 'Sales - Social Media Management', 'Account Code' => '4010101',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, array $lines): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'sales_invoices',
            'uploaded_by' => $batch->created_by,
            'original_filename' => 'Revenue Invoice.xlsx',
            'disk' => 'local',
            'path' => 'zoho-migrations/test/invoice.xlsx',
            'sha256' => hash('sha256', (string) microtime(true)),
            'size_bytes' => 1,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => 'staged',
            'dataset_type' => 'sales_invoices',
            'profile_key' => 'invoices.v1',
            'detection_confidence' => 100,
            'headers' => array_keys($lines[0]),
            'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'tenant_id' => $batch->tenant_id,
                'sheet_name' => 'Invoice',
                'source_row_number' => $index + 2,
                'source_external_id' => $line['Invoice ID'],
                'original_values' => $line,
                'status' => 'staged',
            ]);
        }
    }
}

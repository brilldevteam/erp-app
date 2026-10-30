<?php

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoCustomerCreditService;
use App\Services\ZohoMigration\ZohoFreeTextLineProduct;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use App\Services\ZohoMigration\ZohoVendorCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\JournalEntry;

class ZohoCreditImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoho_credit_note_export_is_recognised_by_its_creditnotes_id_column(): void
    {
        $detected = app(ZohoProfileCatalog::class)->detect(['Credit Note Date', 'CreditNotes ID', 'Credit Note Number', 'Customer Name', 'Applied Invoice Number']);

        $this->assertSame('credit_notes.v1', $detected['profile_key']);
    }

    public function test_credit_notes_import_approved_with_balanced_journals_and_full_open_balance(): void
    {
        [$company, $batch] = $this->batch('credit_notes', ['client' => ['Waypoint', 'BeneFit']]);
        $customerId = User::where('created_by', $company->id)->where('name', 'Waypoint')->value('id');
        $invoice = SalesInvoice::create(['invoice_number' => 'INV-000061', 'invoice_date' => '2022-08-01', 'due_date' => '2022-08-01', 'customer_id' => $customerId, 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'balance_amount' => 0, 'status' => 'posted', 'type' => 'service', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, 'credit_notes', [
            // Two discounted lines on two revenue accounts (10 × 150 → 1000, 1 × 1200 → 1000).
            $this->creditLine('CN-00031', 'Waypoint', 'Closed', ['Item Name' => 'Reels', 'Item Desc' => 'Discount', 'Quantity' => '10', 'Item Price' => '150', 'Item Total' => '1000', 'Total' => '2000', 'Applied Invoice Number' => 'INV-000061', 'Notes' => "As request for the client's discount."]),
            $this->creditLine('CN-00031', 'Waypoint', 'Closed', ['Item Name' => '', 'Item Desc' => 'Branding refund', 'Quantity' => '1', 'Item Price' => '1200', 'Item Total' => '1000', 'Total' => '2000', 'Applied Invoice Number' => 'INV-000061', 'Account' => 'Sales - Branding', 'Account Code' => '4010102', 'Notes' => "As request for the client's discount."]),
            $this->creditLine('CN-00004', 'BeneFit', 'Open', ['Applied Invoice Number' => 'INV-000070,INV-000071,INV-000073']),
            $this->creditLine('CN-00005', 'BeneFit', 'Draft'),
            $this->creditLine('CN-00006', 'BeneFit', 'Void'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'credit_notes', $company->id);
        $this->assertSame(['accepted' => 4, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));

        $summary = app(ZohoCustomerCreditService::class)->import($batch, $company->id);

        $this->assertSame(['posted' => 2, 'drafts' => 1, 'skipped_existing' => 0, 'lines' => 4, 'journal_entries' => 2, 'linked' => 1, 'skipped_in_zoho' => 1], array_diff_key($summary, ['skipped_in_zoho_documents' => 1]));
        $this->assertSame(['CN-00006 (Void in Zoho)'], $summary['skipped_in_zoho_documents']);

        $note = CreditNote::where('created_by', $company->id)->where('credit_note_number', 'CN-00031')->with('items')->firstOrFail();
        $this->assertSame(['2000.00', '2700.00', '700.00', '0.00', '2000.00', 'approved', $invoice->id, 'QAR', '2000.00'], [(string) $note->total_amount, (string) $note->subtotal, (string) $note->discount_amount, (string) $note->applied_amount, (string) $note->balance_amount, $note->status, $note->invoice_id, $note->currency_code, (string) $note->base_amount]);
        $this->assertSame("As request for the client's discount.", $note->reason);
        $this->assertSame([app(ZohoFreeTextLineProduct::class)->for($company->id, $company->id)->id], $note->items->pluck('product_id')->unique()->values()->all());
        $this->assertSame(['500.00', '200.00'], $note->items->sortBy('id')->pluck('discount_amount')->map(fn ($v) => (string) $v)->values()->all());
        $this->assertStringContainsString("Zoho lines:\n- Reels: Discount — 1,000.00\n- Branding refund — 1,000.00", $note->notes);
        $this->assertStringContainsString('Applied in Zoho to: INV-000061', $note->notes);

        $journal = JournalEntry::where('reference_type', 'credit_note')->where('reference_id', $note->id)->with('items')->sole();
        $this->assertSame([
            // As Zoho posts it: revenue reversed at full price, the item discounts credited back to Discount.
            $this->account($company->id, '4010101')->id => ['1500.00', '0.00', null],
            $this->account($company->id, '4010102')->id => ['1200.00', '0.00', null],
            $this->account($company->id, '4020101')->id => ['0.00', '700.00', null],
            $this->account($company->id, '1100')->id => ['0.00', '2000.00', $customerId],
        ], $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount, $item->contact_id]])->all());

        $multi = CreditNote::where('created_by', $company->id)->where('credit_note_number', 'CN-00004')->firstOrFail();
        $this->assertNull($multi->invoice_id, 'A credit applied to several invoices is not linked to one of them.');
        $this->assertStringContainsString('Applied in Zoho to: INV-000070, INV-000071, INV-000073', $multi->notes);
        $draft = CreditNote::where('created_by', $company->id)->where('credit_note_number', 'CN-00005')->firstOrFail();
        $this->assertSame([ 'draft', 0], [$draft->status, JournalEntry::where('reference_type', 'credit_note')->where('reference_id', $draft->id)->count()]);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'credit_notes')->value('status'));
    }

    public function test_vendor_credits_post_against_payables_and_are_renumbered_only_when_another_company_owns_the_number(): void
    {
        [$company, $batch] = $this->batch('vendor_credits', ['vendor' => ['Telet Technologies', 'Print Media']]);
        $otherCompany = User::factory()->create(['type' => 'company', 'created_by' => null]);
        DebitNote::create(['debit_note_number' => 'VC-901', 'debit_note_date' => '2023-01-01', 'vendor_id' => $otherCompany->id, 'reason' => 'Other company', 'status' => 'approved', 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'applied_amount' => 0, 'balance_amount' => 0, 'creator_id' => $otherCompany->id, 'created_by' => $otherCompany->id]);
        $teletId = User::where('created_by', $company->id)->where('name', 'Telet Technologies')->value('id');
        $bill = PurchaseInvoice::create(['invoice_number' => 'Assump', 'invoice_date' => '2023-01-01', 'due_date' => '2023-01-01', 'vendor_id' => $teletId, 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'debit_note_applied' => 0, 'balance_amount' => 0, 'status' => 'posted', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, 'vendor_credits', [
            $this->vendorLine('901', 'Telet Technologies', ['Item Price' => '41600', 'Item Total' => '41600', 'Total' => '41600', 'Associated Bill Number' => 'Assump']),
            $this->vendorLine('902', 'Print Media', ['Item Price' => '2100', 'Item Total' => '2100', 'Total' => '2100']),
        ]);

        app(ZohoMigrationValidator::class)->validate($batch, 'vendor_credits', $company->id);
        $summary = app(ZohoVendorCreditService::class)->import($batch, $company->id);

        $this->assertSame([2, 1], [$summary['posted'], $summary['linked']]);
        $first = DebitNote::where('created_by', $company->id)->where('debit_note_number', "VC-901-{$company->id}")->firstOrFail();
        $this->assertSame(['41600.00', '41600.00', 'approved', $bill->id], [(string) $first->total_amount, (string) $first->balance_amount, $first->status, $first->invoice_id]);
        $this->assertStringContainsString("Numbered VC-901-{$company->id} because VC-901 is used by another company.", $first->notes);
        $this->assertSame(1, DebitNote::where('created_by', $company->id)->where('debit_note_number', 'VC-902')->count(), 'A free number keeps the plain VC-<number> form.');

        $journal = JournalEntry::where('reference_type', 'debit_note')->where('reference_id', $first->id)->with('items')->sole();
        $this->assertSame([
            $this->account($company->id, '2000')->id => ['41600.00', '0.00', $teletId],
            $this->account($company->id, '5010102')->id => ['0.00', '41600.00', null],
        ], $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount, $item->contact_id]])->all());

        // Re-checking after import treats the renumbered credit as the same credit, not a new one.
        $this->assertSame(1, DebitNote::where('created_by', $company->id)->whereIn('debit_note_number', ['VC-901', "VC-901-{$company->id}"])->count());
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->batch('credit_notes', ['client' => ['Waypoint']]);
        CreditNote::create(['credit_note_number' => 'CN-OLD', 'credit_note_date' => '2022-01-01', 'customer_id' => User::where('created_by', $company->id)->where('type', 'client')->value('id'), 'reason' => 'x', 'status' => 'draft', 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'applied_amount' => 0, 'balance_amount' => 0, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, 'credit_notes', [
            $this->creditLine('CN-10', 'Unknown Customer', 'Closed'),
            $this->creditLine('CN-11', 'Waypoint', 'Closed', ['Account' => 'Missing', 'Account Code' => '4999999']),
            $this->creditLine('CN-12', 'Waypoint', 'Closed', ['Total' => '999']),
            $this->creditLine('CN-13', 'Waypoint', 'Refunded'),
            $this->creditLine('CN-OLD', 'Waypoint', 'Closed'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'credit_notes', $company->id);

        $this->assertSame(5, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'credit_notes')->pluck('message')->all();
        $this->assertContains("Customer 'Unknown Customer' was not found in ERP. Complete the Customers step first.", $messages);
        $this->assertContains('Account 4999999 (Missing) was not found in ERP. Complete the Chart of Accounts step first.', $messages);
        $this->assertContains('Line totals add up to 1,000.00 but the Zoho credit note total is 999.00.', $messages);
        $this->assertContains("credit note status 'Refunded' is not a known Zoho status.", $messages);
        $this->assertContains('A credit note with this number already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->count());
    }

    private function batch(string $stageKey, array $partners): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        foreach ($partners as $type => $names) {
            foreach ($names as $name) {
                User::factory()->create(['type' => $type, 'name' => $name, 'created_by' => $company->id]);
            }
        }
        $typeId = DB::table('account_types')->value('id');
        foreach ([['1100', 'Accounts Receivable', 'debit'], ['2000', 'Accounts Payable', 'credit'], ['4010101', 'Sales - Social Media Management', 'credit'], ['4010102', 'Sales - Branding', 'credit'], ['5010102', 'Salaries and other related cost', 'debit'], ['4020101', 'Discount', 'credit']] as [$code, $name, $balance]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Credit import test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'rebuild', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => $stageKey, 'position' => 14, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function creditLine(string $number, string $customer, string $status, array $extra = []): array
    {
        return $extra + [
            'Credit Note Date' => '2022-08-10', 'CreditNotes ID' => 'ID-'.$number, 'Credit Note Number' => $number, 'Credit Note Status' => $status,
            'Customer Name' => $customer, 'Currency Code' => 'QAR', 'Exchange Rate' => '1', 'Total' => '1000', 'Balance' => '0', 'Notes' => '',
            'Terms & Conditions' => '', 'Subject' => '', 'Item Name' => 'Social Media Management', 'Item Desc' => '', 'Quantity' => '1',
            'Item Price' => '1000', 'Item Total' => '1000', 'Applied Invoice Number' => '', 'Account' => 'Sales - Social Media Management', 'Account Code' => '4010101',
        ];
    }

    private function vendorLine(string $number, string $vendor, array $extra = []): array
    {
        return $extra + [
            'Vendor Credit Date' => '2023-01-01', 'Vendor Credit ID' => 'ID-VC-'.$number, 'Vendor Credit Number' => $number, 'Vendor Credit Status' => 'Closed',
            'Vendor Name' => $vendor, 'Associated Bill Number' => '', 'Currency Code' => 'QAR', 'Exchange Rate' => '1', 'Account' => 'Salaries and other related cost',
            'Account Code' => '5010102', 'Item Name' => 'Credit', 'Item Desc' => '', 'Quantity' => '1', 'Item Price' => '1000', 'Item Tax Amount' => '0',
            'Item Total' => '1000', 'Total' => '1000', 'Balance' => '0', 'Notes' => '',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, string $stageKey, array $lines): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => $stageKey, 'uploaded_by' => $batch->created_by,
            'original_filename' => 'credits.xlsx', 'disk' => 'local', 'path' => 'zoho-migrations/test/credits.xlsx',
            'sha256' => hash('sha256', (string) microtime(true)), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'staged',
            'dataset_type' => $stageKey, 'profile_key' => $stageKey.'.v1', 'detection_confidence' => 100, 'headers' => array_keys($lines[0]), 'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $batch->tenant_id, 'sheet_name' => 'Sheet1',
                'source_row_number' => $index + 2, 'original_values' => $line, 'status' => 'staged',
            ]);
        }
    }
}

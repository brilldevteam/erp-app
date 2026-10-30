<?php

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoBillService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

class ZohoBillImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_live_bills_post_each_line_to_its_zoho_account_drafts_post_nothing_and_void_is_skipped(): void
    {
        [$company, $batch] = $this->billsBatch(['Telet Technologies', 'Sign Plus Advertising']);
        $this->stage($batch, [
            $this->line('BL-1', 'Telet Technologies', 'Paid', ['Item Name' => 'Laptop', 'Description' => 'Dell 14"', 'Rate' => '4000', 'Item Total' => '4000', 'Total' => '6500', 'Account' => 'Cost of Computer Equipment', 'Account Code' => '1010201']),
            $this->line('BL-1', 'Telet Technologies', 'Paid', ['Item Name' => '', 'Description' => 'Setup', 'Quantity' => '2.5', 'Rate' => '1000', 'Item Total' => '2500', 'Total' => '6500']),
            $this->line('BL-2', 'Sign Plus Advertising', 'Overdue', ['Due Date' => '2026-07-15']),
            $this->line('BL-3', 'Sign Plus Advertising', 'Draft'),
            $this->line('BL-4', 'Sign Plus Advertising', 'Void'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'purchase_invoices', $company->id);
        $this->assertSame(['accepted' => 4, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));

        $summary = app(ZohoBillService::class)->import($batch, $company->id);

        $this->assertSame(['posted' => 2, 'drafts' => 1, 'skipped_existing' => 0, 'lines' => 4, 'journal_entries' => 2, 'skipped_in_zoho' => 1], array_diff_key($summary, ['skipped_in_zoho_bills' => 1]));
        $this->assertSame(['BL-4 – Sign Plus Advertising (Void in Zoho)'], $summary['skipped_in_zoho_bills']);

        $bill = PurchaseInvoice::where('created_by', $company->id)->where('invoice_number', 'BL-1')->with('items')->firstOrFail();
        $this->assertSame(['6500.00', '0.00', '6500.00', 'posted'], [(string) $bill->total_amount, (string) $bill->paid_amount, (string) $bill->balance_amount, $bill->status]);
        $this->assertSame([null], $bill->items->pluck('product_id')->unique()->values()->all(), 'Bill lines are free-text lines with no product.');
        $this->assertSame("Laptop\nDell 14\"", $bill->items->sortBy('id')->first()->description);
        $this->assertSame('2.5000', (string) $bill->items->sortBy('id')->last()->quantity);
        $this->assertStringContainsString('Zoho status: Paid', $bill->notes);

        $journal = JournalEntry::where('reference_type', 'purchase_invoice')->where('reference_id', $bill->id)->with('items')->sole();
        $this->assertSame(["ZOHO-{$company->id}-ID-BL-1-20240110", '6500.00'], [$journal->journal_number, (string) $journal->total_debit]);
        $vendorId = User::where('created_by', $company->id)->where('name', 'Telet Technologies')->value('id');
        $this->assertSame([
            $this->account($company->id, '1010201')->id => ['4000.00', '0.00', null],
            $this->account($company->id, '5010102')->id => ['2500.00', '0.00', null],
            $this->account($company->id, '2000')->id => ['0.00', '6500.00', $vendorId],
        ], $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount, $item->contact_id]])->all());
        $this->assertSame('7500.00', (string) $this->account($company->id, '2000')->current_balance, 'Payables 2000 holds both posted bills (6500 + 1000).');
        $this->assertSame('4000.00', (string) $this->account($company->id, '1010201')->current_balance);

        $draft = PurchaseInvoice::where('created_by', $company->id)->where('invoice_number', 'BL-3')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame(0, JournalEntry::where('reference_type', 'purchase_invoice')->where('reference_id', $draft->id)->count());
        $this->assertSame(0, PurchaseInvoice::where('created_by', $company->id)->where('invoice_number', 'BL-4')->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'purchase_invoices')->value('status'));
    }

    public function test_two_vendors_may_use_the_same_bill_number(): void
    {
        [$company, $batch] = $this->billsBatch(['Telet Technologies', 'Print Media']);
        $this->stage($batch, [
            $this->line('1001', 'Telet Technologies', 'Paid', ['Bill ID' => 'A']),
            $this->line('1001', 'Print Media', 'Paid', ['Bill ID' => 'B', 'Rate' => '200', 'Item Total' => '200', 'Total' => '200']),
        ]);

        app(ZohoMigrationValidator::class)->validate($batch, 'purchase_invoices', $company->id);
        $summary = app(ZohoBillService::class)->import($batch, $company->id);

        $this->assertSame(2, $summary['posted']);
        $this->assertSame(['1000.00', '200.00'], PurchaseInvoice::where('created_by', $company->id)->where('invoice_number', '1001')->orderBy('id')->pluck('total_amount')->map(fn ($v) => (string) $v)->all());
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->billsBatch(['Telet Technologies']);
        PurchaseInvoice::create(['invoice_number' => 'BL-OLD', 'invoice_date' => '2024-01-01', 'due_date' => '2024-01-01', 'vendor_id' => User::where('created_by', $company->id)->where('type', 'vendor')->value('id'), 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'debit_note_applied' => 0, 'balance_amount' => 0, 'status' => 'draft', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->line('BL-10', 'Unknown Vendor', 'Paid'),
            $this->line('BL-11', 'Telet Technologies', 'Approved'),
            $this->line('BL-12', 'Telet Technologies', 'Paid', ['Account' => 'Missing', 'Account Code' => '5999999']),
            $this->line('BL-13', 'Telet Technologies', 'Paid', ['Tax Amount' => '50']),
            $this->line('BL-14', 'Telet Technologies', 'Paid', ['Total' => '999']),
            $this->line('BL-OLD', 'Telet Technologies', 'Paid'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'purchase_invoices', $company->id);

        $this->assertSame(6, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'purchase_invoices')->pluck('message')->all();
        $this->assertContains("Vendor 'Unknown Vendor' was not found in ERP. Complete the Vendors step first.", $messages);
        $this->assertContains("Bill Status 'Approved' is not a known Zoho status.", $messages);
        $this->assertContains('Account 5999999 (Missing) was not found in ERP. Complete the Chart of Accounts step first.', $messages);
        $this->assertContains('This bill line has Zoho tax (50.00); taxed bills are not supported by this import yet.', $messages);
        $this->assertContains('Line totals add up to 1,000.00 but the Zoho bill total is 999.00.', $messages);
        $this->assertContains('A bill with this number from this vendor already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->count());
    }

    private function billsBatch(array $vendors): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        foreach ($vendors as $name) {
            User::factory()->create(['type' => 'vendor', 'name' => $name, 'created_by' => $company->id]);
        }
        $typeId = DB::table('account_types')->value('id');
        foreach ([['2000', 'Accounts Payable', 'credit'], ['5010102', 'Salaries and other related cost', 'debit'], ['1010201', 'Cost of Computer Equipment', 'debit']] as [$code, $name, $balance]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Bill import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'purchase_invoices', 'position' => 13, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function line(string $number, string $vendor, string $status, array $extra = []): array
    {
        return $extra + [
            'Bill Date' => '2024-01-10', 'Due Date' => '2024-01-10', 'Bill ID' => 'ID-'.$number, 'Vendor Name' => $vendor,
            'Payment Terms Label' => 'Due on Receipt', 'Bill Number' => $number, 'PurchaseOrder' => '', 'Currency Code' => 'QAR',
            'Exchange Rate' => '1', 'Total' => '1000', 'Balance' => '0', 'Vendor Notes' => '', 'Terms & Conditions' => '',
            'Bill Status' => $status, 'Item Name' => 'Freelance work', 'Account' => 'Salaries and other related cost',
            'Account Code' => '5010102', 'Description' => '', 'Quantity' => '1', 'Usage unit' => '', 'Tax Amount' => '0',
            'Item Total' => '1000', 'Rate' => '1000',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, array $lines): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'purchase_invoices',
            'uploaded_by' => $batch->created_by,
            'original_filename' => '1 - Bills list.xlsx',
            'disk' => 'local',
            'path' => 'zoho-migrations/test/bills.xlsx',
            'sha256' => hash('sha256', (string) microtime(true)),
            'size_bytes' => 1,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => 'staged',
            'dataset_type' => 'purchase_invoices',
            'profile_key' => 'bills.v1',
            'detection_confidence' => 100,
            'headers' => array_keys($lines[0]),
            'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'tenant_id' => $batch->tenant_id,
                'sheet_name' => 'Bills',
                'source_row_number' => $index + 2,
                'source_external_id' => $line['Bill ID'],
                'original_values' => $line,
                'status' => 'staged',
            ]);
        }
    }
}

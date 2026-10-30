<?php

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoCreditApplicationService;
use App\Services\ZohoMigration\ZohoMigrationFileInspector;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\CreditNoteApplication;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\DebitNote;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\VendorPayment;

class ZohoCreditApplicationImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_stage_taking_several_files_keeps_one_of_each_kind(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('zoho-migrations/test');
        [$company, $batch] = $this->batch();
        $refunds = $this->upload($batch, 'refunds-1.xlsx', [['Refund History'], array_keys($this->refund('R1', 'CN-00001', 100))], [array_values($this->refund('R1', 'CN-00001', 100))]);
        $split = $this->upload($batch, 'split.xlsx', [array_keys($this->split('CN-00004', 'INV-000070', 1))], [array_values($this->split('CN-00004', 'INV-000070', 1))]);
        $newerRefunds = $this->upload($batch, 'refunds-2.xlsx', [['Refund History'], array_keys($this->refund('R1', 'CN-00001', 100))], [array_values($this->refund('R1', 'CN-00001', 100))]);

        $this->assertSame(['refund_history.v1', 'credit_note_applications.v1'], [$newerRefunds->profile_key, $split->profile_key]);
        $this->assertSame(['superseded', 'staged', 'staged'], [$refunds->fresh()->status, $split->fresh()->status, $newerRefunds->fresh()->status]);
    }

    public function test_credits_are_applied_split_refunded_and_closed_as_in_zoho(): void
    {
        [$company, $batch] = $this->batch();
        $ceramics = $this->user($company, 'Qatar Ceramics');
        $signplus = $this->user($company, 'signplus');
        $telet = $this->user($company, 'Telet');
        $invoices = [];
        foreach (['INV-000070' => [9500, $ceramics], 'INV-000071' => [7905, $ceramics], 'INV-000073' => [5000, $ceramics], 'INV-000113' => [2100, $ceramics], 'BRINV-000027' => [4500, $signplus]] as $number => [$total, $customer]) {
            $invoices[$number] = $this->invoice($company, $number, $customer, $total);
        }
        // signplus paid half of its invoice before the credit note.
        $invoices['BRINV-000027']->update(['paid_amount' => 2250, 'balance_amount' => 2250, 'status' => 'partial']);
        $cn4 = $this->creditNote($company, 'CN-00004', $ceramics, 13703, '2022-12-01');
        $cn10 = $this->creditNote($company, 'CN-00010', $ceramics, 10802, '2023-11-01');
        $cn1 = $this->creditNote($company, 'CN-00001', $signplus, 4500, '2022-03-28');
        $bill = PurchaseInvoice::create(['invoice_number' => 'Assump', 'invoice_date' => '2023-01-01', 'due_date' => '2023-01-01', 'vendor_id' => $telet, 'subtotal' => 41600, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 41600, 'paid_amount' => 0, 'debit_note_applied' => 0, 'balance_amount' => 41600, 'status' => 'posted', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $vc = DebitNote::create(['debit_note_number' => 'VC-901', 'debit_note_date' => '2023-01-01', 'vendor_id' => $telet, 'invoice_id' => $bill->id, 'reason' => 'Credit', 'status' => 'approved', 'subtotal' => 41600, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 41600, 'applied_amount' => 0, 'balance_amount' => 41600, 'creator_id' => $company->id, 'created_by' => $company->id]);

        $this->earlierExports($batch, [
            'INV-000070' => 0, 'INV-000071' => 0, 'INV-000073' => 0, 'INV-000113' => 0, 'BRINV-000027' => 0,
        ], [
            ['CN-00004', 'Qatar Ceramics', '2022-12-01', 'INV-000070,INV-000071,INV-000073'],
            ['CN-00010', 'Qatar Ceramics', '2023-11-01', 'INV-000070,INV-000071,INV-000113'],
            ['CN-00001', 'signplus', '2022-03-28', 'BRINV-000027'],
        ]);
        $this->stageRows($batch, 'credit_applications', 'credit_refunds', 'refund_history.v1', [$this->refund('R1', 'CN-00001', 2250)]);
        // Sithari's Credit Applied Invoices list for CN-00004; CN-00010 is worked out from it.
        $this->stageRows($batch, 'credit_applications', 'credit_note_applications', 'credit_note_applications.v1', [
            $this->split('CN-00004', 'INV-000070', 4750), $this->split('CN-00004', 'INV-000071', 3953), $this->split('CN-00004', 'INV-000073', 5000),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'credit_applications', $company->id);
        $this->assertSame(['accepted' => 4, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $summary = app(ZohoCreditApplicationService::class)->import($batch, $company->id);

        $this->assertSame(['credit_notes_applied' => 3, 'invoice_applications' => 7, 'credited_to_invoices' => 26755.0, 'refunds' => 1, 'refunded' => 2250.0, 'vendor_credits_applied' => 1, 'credited_to_bills' => 41600.0, 'already_applied' => 0], $summary);
        $this->assertSame(['INV-000070' => '4750.00', 'INV-000071' => '3952.00', 'INV-000113' => '2100.00'], $this->allocations($company, 'CNA-CN-00010'));
        $this->assertSame(['INV-000070' => '4750.00', 'INV-000071' => '3953.00', 'INV-000073' => '5000.00'], $this->allocations($company, 'CNA-CN-00004'));
        foreach ($invoices as $invoice) {
            $this->assertSame(['paid', '0.00'], [$invoice->fresh()->status, (string) $invoice->fresh()->balance_amount], $invoice->invoice_number);
        }
        foreach ([$cn4, $cn10, $cn1] as $note) {
            $this->assertSame(['applied', '0.00'], [$note->fresh()->status, (string) $note->fresh()->balance_amount], $note->credit_note_number);
        }
        $payment = CustomerPayment::where('created_by', $company->id)->where('payment_number', 'CNA-CN-00004')->sole();
        $this->assertSame(['0.00', 'cleared', '2022-12-01'], [(string) $payment->payment_amount, $payment->status, $payment->payment_date->format('Y-m-d')]);
        $this->assertSame('13703.00', (string) CreditNoteApplication::where('credit_note_id', $cn4->id)->sole()->applied_amount);

        $refund = JournalEntry::where('created_by', $company->id)->where('reference_type', 'credit_note_refund')->with('items')->sole();
        $this->assertSame([
            ChartOfAccount::where('created_by', $company->id)->where('account_code', '1100')->value('id') => ['2250.00', '0.00', $signplus],
            ChartOfAccount::where('created_by', $company->id)->where('account_code', '1020102')->value('id') => ['0.00', '2250.00', null],
        ], $refund->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount, $item->contact_id]])->all());
        $this->assertSame('-2250.00', (string) BankAccount::where('created_by', $company->id)->sole()->current_balance);
        $this->assertStringContainsString('Refunded in Zoho on 2022-04-10: 2,250.00 from QIB - 0152752070013 (Bank Transfer) - Cancelled Order.', $cn1->fresh()->notes);

        $this->assertSame(['applied', 'paid'], [$vc->fresh()->status, $bill->fresh()->status]);
        $this->assertSame(1, VendorPayment::where('created_by', $company->id)->where('payment_number', 'DNA-VC-901')->where('payment_amount', 0)->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'credit_applications')->value('status'));
    }

    public function test_a_split_that_cannot_be_worked_out_is_reported_not_guessed(): void
    {
        [$company, $batch] = $this->batch();
        $ceramics = $this->user($company, 'Qatar Ceramics');
        foreach (['INV-000070' => 9500, 'INV-000071' => 7905, 'INV-000073' => 5000, 'INV-000113' => 2100] as $number => $total) {
            $this->invoice($company, $number, $ceramics, $total);
        }
        $this->creditNote($company, 'CN-00004', $ceramics, 13703, '2022-12-01');
        $this->creditNote($company, 'CN-00010', $ceramics, 10802, '2023-11-01');
        $this->earlierExports($batch, ['INV-000070' => 0, 'INV-000071' => 0, 'INV-000073' => 0, 'INV-000113' => 0], [
            ['CN-00004', 'Qatar Ceramics', '2022-12-01', 'INV-000070,INV-000071,INV-000073'],
            ['CN-00010', 'Qatar Ceramics', '2023-11-01', 'INV-000070,INV-000071,INV-000113'],
        ]);
        $this->stageRows($batch, 'credit_applications', 'credit_refunds', 'refund_history.v1', [$this->refund('R9', 'CN-00099', 10)]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'credit_applications', $company->id);

        $this->assertSame(1, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'credit_applications')->pluck('message')->all();
        $this->assertContains('Credit note CN-00004 has 8,703.00 to apply to INV-000070, INV-000071, INV-000073 and the split cannot be worked out. Add its Credit Applied Invoices rows (Credit Note Number, Invoice Number, Amount Credited) to the credit split sheet.', $messages);
        $this->assertContains('Credit note CN-00099 is not an open imported credit note in ERP. Complete the Credit notes step first.', $messages);
        $this->assertSame(0, CustomerPayment::where('created_by', $company->id)->count());
    }

    private function batch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        foreach (['client' => ['Qatar Ceramics', 'signplus'], 'vendor' => ['Telet']] as $type => $names) {
            foreach ($names as $name) {
                User::factory()->create(['type' => $type, 'name' => $name, 'created_by' => $company->id]);
            }
        }
        $typeId = DB::table('account_types')->value('id');
        foreach ([['1100', 'Accounts Receivable', 'debit'], ['2000', 'Accounts Payable', 'credit'], ['1020102', 'QIB - 0152752070013', 'debit']] as [$code, $name, $balance]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        BankAccount::create(['account_name' => 'QIB - 0152752070013', 'account_number' => null, 'bank_name' => 'QIB', 'account_type' => 'checking', 'zoho_gl_code' => '1020102', 'gl_account_id' => ChartOfAccount::where('created_by', $company->id)->where('account_code', '1020102')->value('id'), 'currency_code' => 'QAR', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Credit application test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'full_history', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'credit_applications', 'position' => 18, 'status' => 'uploaded']);
        $this->stageRows($batch, 'chart_of_accounts', 'chart_of_accounts', 'chart_of_accounts.v1', [['Account ID' => 'Z-QIB', 'Account Code' => '1020102', 'Account Name' => 'QIB - 0152752070013', 'Account Type' => 'Bank']]);

        return [$company, $batch];
    }

    /** The invoice, bill, credit note and vendor credit exports uploaded in steps 12–15. */
    private function earlierExports(ZohoMigrationBatch $batch, array $invoiceBalances, array $creditNotes): void
    {
        $this->stageRows($batch, 'sales_invoices', 'sales_invoices', 'invoices.v1', array_map(fn ($number, $balance) => ['Invoice Number' => $number, 'Invoice Status' => 'Closed', 'Balance' => (string) $balance], array_keys($invoiceBalances), $invoiceBalances));
        $this->stageRows($batch, 'credit_notes', 'credit_notes', 'credit_notes.v1', array_map(fn (array $note) => ['CreditNotes ID' => 'ID-'.$note[0], 'Credit Note Number' => $note[0], 'Credit Note Status' => 'Closed', 'Customer Name' => $note[1], 'Credit Note Date' => $note[2], 'Applied Invoice Number' => $note[3], 'Balance' => '0'], $creditNotes));
        $this->stageRows($batch, 'purchase_invoices', 'purchase_invoices', 'bills.v1', [['Bill Number' => 'Assump', 'Vendor Name' => 'Telet', 'Bill Status' => 'Paid', 'Balance' => '0']]);
        $this->stageRows($batch, 'vendor_credits', 'vendor_credits', 'vendor_credits.v1', [['Vendor Credit ID' => 'ID-VC', 'Vendor Credit Number' => '901', 'Vendor Credit Status' => 'Closed', 'Vendor Name' => 'Telet', 'Vendor Credit Date' => '2023-01-01', 'Associated Bill Number' => 'Assump', 'Balance' => '0']]);
    }

    private function user(User $company, string $name): int
    {
        return User::where('created_by', $company->id)->where('name', $name)->value('id');
    }

    private function invoice(User $company, string $number, int $customer, float $total): SalesInvoice
    {
        return SalesInvoice::create(['invoice_number' => $number, 'invoice_date' => '2022-08-01', 'due_date' => '2022-08-01', 'customer_id' => $customer, 'subtotal' => $total, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $total, 'paid_amount' => 0, 'balance_amount' => $total, 'status' => 'posted', 'type' => 'service', 'creator_id' => $company->id, 'created_by' => $company->id]);
    }

    private function creditNote(User $company, string $number, int $customer, float $total, string $date): CreditNote
    {
        return CreditNote::create(['credit_note_number' => $number, 'credit_note_date' => $date, 'customer_id' => $customer, 'reason' => 'Credit', 'status' => 'approved', 'subtotal' => $total, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $total, 'applied_amount' => 0, 'balance_amount' => $total, 'creator_id' => $company->id, 'created_by' => $company->id]);
    }

    private function allocations(User $company, string $payment): array
    {
        return CustomerPayment::where('created_by', $company->id)->where('payment_number', $payment)->sole()
            ->allocations()->with('invoice')->get()->sortBy(fn ($a) => $a->invoice->invoice_number)
            ->mapWithKeys(fn ($a) => [$a->invoice->invoice_number => (string) $a->allocated_amount])->all();
    }

    private function refund(string $id, string $creditNote, float $amount): array
    {
        return [
            'refund_id' => $id, 'entity_id' => 'E'.$id, 'date' => '2022-04-10', 'refund_mode' => 'Bank Transfer', 'reference_number' => '', 'entity_number' => $creditNote,
            'customer_name' => 'signplus', 'refund_from_account_id' => 'Z-QIB', 'refund_from_account_name' => 'QIB - 0152752070013', 'entity_type' => 'creditnote',
            'description' => 'Cancelled Order', 'bcy_amount' => (string) $amount, 'fcy_amount' => (string) $amount, 'customer_id' => 'C1', 'currency_code' => 'QAR',
        ];
    }

    private function split(string $creditNote, string $invoice, float $amount): array
    {
        return ['Credit Note Number' => $creditNote, 'Applied Date' => '2022-12-01', 'Invoice Number' => $invoice, 'Amount Credited' => (string) $amount];
    }

    private function upload(ZohoMigrationBatch $batch, string $name, array $headerRows, array $dataRows): ZohoMigrationFile
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray(array_merge($headerRows, $dataRows), null, 'A1', true);
        $path = 'zoho-migrations/test/'.$name;
        (new Xlsx($sheet->getParent()))->save(Storage::disk('local')->path($path));
        $file = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => 'credit_applications', 'uploaded_by' => $batch->created_by, 'original_filename' => $name, 'disk' => 'local', 'path' => $path, 'sha256' => hash('sha256', $name), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'uploaded']);
        app(ZohoMigrationFileInspector::class)->inspectAndStage($file);

        return $file->fresh();
    }

    private function stageRows(ZohoMigrationBatch $batch, string $stageKey, string $dataset, string $profile, array $lines): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => $stageKey, 'uploaded_by' => $batch->created_by,
            'original_filename' => $dataset.'.xlsx', 'disk' => 'local', 'path' => 'zoho-migrations/test/'.$dataset.'.xlsx',
            'sha256' => hash('sha256', $dataset.microtime(true)), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'staged',
            'dataset_type' => $dataset, 'profile_key' => $profile, 'detection_confidence' => 100, 'headers' => array_keys($lines[0]), 'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $batch->tenant_id, 'sheet_name' => 'Sheet1',
                'source_row_number' => $index + 3, 'original_values' => $line, 'status' => 'staged',
            ]);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoCustomerPaymentService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use App\Services\ZohoMigration\ZohoVendorPaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\VendorPayment;

class ZohoPaymentImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoho_payment_exports_are_recognised_by_their_payment_id_columns(): void
    {
        $catalog = app(ZohoProfileCatalog::class);

        $this->assertSame('payments_received.v1', $catalog->detect(array_keys($this->customerLine('1', 'Waypoint')))['profile_key']);
        // The vendor export also carries Bill ID, Bill Number and Vendor Name, which alone would read as a bills export.
        $this->assertSame('vendor_payments.v1', $catalog->detect(array_keys($this->vendorLine('1', 'Telet') + ['Bill ID' => '1']))['profile_key']);
    }

    public function test_customer_payments_are_applied_to_invoices_and_posted_against_receivables(): void
    {
        [$company, $batch] = $this->batch('customer_payments', ['client' => ['Waypoint', 'BeneFit']]);
        $waypoint = $this->userId($company, 'Waypoint');
        $benefit = $this->userId($company, 'BeneFit');
        $first = $this->invoice($company, 'INV-000061', $waypoint, 4000);
        $second = $this->invoice($company, 'INV-000062', $waypoint, 1500);
        $third = $this->invoice($company, 'INV-000070', $benefit, 9500);
        $this->stage($batch, 'customer_payments', [
            // One payment applied to two invoices, a second one partly paying a third.
            $this->customerLine('30', 'Waypoint', ['Amount' => '5500', 'Invoice Number' => 'INV-000061', 'Amount Applied to Invoice' => '4000', 'Reference Number' => 'CHQ 1']),
            $this->customerLine('30', 'Waypoint', ['Amount' => '5500', 'Invoice Number' => 'INV-000062', 'Amount Applied to Invoice' => '1500', 'Reference Number' => 'CHQ 1']),
            $this->customerLine('31', 'BeneFit', ['Amount' => '2000', 'Invoice Number' => 'INV-000070', 'Amount Applied to Invoice' => '2000', 'Deposit To' => 'Cash In Hand', 'Deposit To Account Code' => '1020101', 'Mode' => 'Cash']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'customer_payments', $company->id);
        $this->assertSame(['accepted' => 3, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));

        $summary = app(ZohoCustomerPaymentService::class)->import($batch, $company->id);

        $this->assertSame(['payments' => 2, 'allocations' => 3, 'amount' => 7500.0, 'unapplied_amount' => 0.0, 'documents_paid' => 2, 'documents_partial' => 1, 'skipped_existing' => 0, 'clearing_accounts_created' => 0], $summary);
        $payment = CustomerPayment::where('created_by', $company->id)->where('payment_number', '30')->with('allocations')->sole();
        $qib = BankAccount::where('created_by', $company->id)->where('zoho_gl_code', '1020102')->sole();
        $this->assertSame(['2022-08-10', $waypoint, $qib->id, 'CHQ 1', '5500.00', 'cleared', 'QAR', '5500.00'], [$payment->payment_date->format('Y-m-d'), $payment->customer_id, $payment->bank_account_id, $payment->reference_number, (string) $payment->payment_amount, $payment->status, $payment->currency_code, (string) $payment->base_amount]);
        $this->assertSame([$first->id => '4000.00', $second->id => '1500.00'], $payment->allocations->mapWithKeys(fn ($a) => [$a->invoice_id => (string) $a->allocated_amount])->all());
        $this->assertSame(['paid', '4000.00', '0.00'], [$first->fresh()->status, (string) $first->fresh()->paid_amount, (string) $first->fresh()->balance_amount]);
        $this->assertSame(['partial', '2000.00', '7500.00'], [$third->fresh()->status, (string) $third->fresh()->paid_amount, (string) $third->fresh()->balance_amount]);

        $journal = JournalEntry::where('reference_type', 'customer_payment')->where('reference_id', $payment->id)->with('items')->sole();
        $this->assertSame([
            $this->account($company->id, '1020102')->id => ['5500.00', '0.00', null],
            $this->account($company->id, '1100')->id => ['0.00', '5500.00', $waypoint],
        ], $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount, $item->contact_id]])->all());
        $this->assertSame('-7500.00', (string) $this->account($company->id, '1100')->current_balance);

        $this->assertSame('5500.00', (string) $qib->fresh()->current_balance);
        $transaction = BankTransaction::where('bank_account_id', $qib->id)->sole();
        $this->assertSame(['credit', '30', '5500.00', 'cleared', 'unreconciled'], [$transaction->transaction_type, $transaction->reference_number, (string) $transaction->amount, $transaction->transaction_status, $transaction->reconciliation_status]);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'customer_payments')->value('status'));
    }

    public function test_vendor_payments_keep_unapplied_amounts_as_advances_and_use_a_clearing_account_for_non_bank_accounts(): void
    {
        [$company, $batch] = $this->batch('vendor_payments', ['vendor' => ['Raybeam', 'Mobi Commerce']]);
        $raybeam = $this->userId($company, 'Raybeam');
        $mobi = $this->userId($company, 'Mobi Commerce');
        $bill = $this->bill($company, 'RB-1', $raybeam, 1000);
        $mobiBill = $this->bill($company, 'MC-7', $mobi, 11330.81);
        $this->stage($batch, 'vendor_payments', [
            $this->vendorLine('18', 'Raybeam', ['Amount' => '4630', 'Unused Amount' => '3630', 'Bill Number' => 'RB-1', 'Bill Amount' => '1000']),
            $this->vendorLine('16', 'Mobi Commerce', ['Amount' => '11330.81', 'Bill Number' => 'MC-7', 'Bill Amount' => '11330.81', 'Paid Through' => 'Projects on Process', 'Paid Through Account Code' => '1060101']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'vendor_payments', $company->id);
        $this->assertSame(0, $validation['rejected']);
        $summary = app(ZohoVendorPaymentService::class)->import($batch, $company->id);

        $this->assertSame([2, 3630.0, 1, 2], [$summary['payments'], $summary['unapplied_amount'], $summary['clearing_accounts_created'], $summary['documents_paid']]);
        $payment = VendorPayment::where('created_by', $company->id)->where('payment_number', '18')->sole();
        $this->assertStringContainsString('Unapplied in Zoho: 3,630.00 held as an advance to the vendor.', $payment->notes);
        $this->assertSame(['paid', '0.00'], [$bill->fresh()->status, (string) $bill->fresh()->balance_amount]);
        $journal = JournalEntry::where('reference_type', 'vendor_payment')->where('reference_id', $payment->id)->with('items')->sole();
        $this->assertSame([
            $this->account($company->id, '2000')->id => ['1000.00', '0.00', $raybeam],
            $this->account($company->id, '1030101')->id => ['3630.00', '0.00', $raybeam],
            $this->account($company->id, '1020102')->id => ['0.00', '4630.00', null],
        ], $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount, $item->contact_id]])->all());

        $clearing = BankAccount::where('created_by', $company->id)->where('account_type', 'clearing_account')->sole();
        $this->assertSame(['Projects on Process', '1060101', $this->account($company->id, '1060101')->id, '-11330.81'], [$clearing->account_name, $clearing->zoho_gl_code, $clearing->gl_account_id, (string) $clearing->current_balance]);
        $this->assertSame($clearing->id, VendorPayment::where('created_by', $company->id)->where('payment_number', '16')->value('bank_account_id'));
        $this->assertSame(['paid', '0.00'], [$mobiBill->fresh()->status, (string) $mobiBill->fresh()->balance_amount]);
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->batch('customer_payments', ['client' => ['Waypoint']]);
        $waypoint = $this->userId($company, 'Waypoint');
        $this->invoice($company, 'INV-1', $waypoint, 1000);
        $this->invoice($company, 'INV-DRAFT', $waypoint, 1000, 'draft');
        CustomerPayment::create(['payment_number' => '99', 'payment_date' => '2022-01-01', 'customer_id' => $waypoint, 'bank_account_id' => BankAccount::where('created_by', $company->id)->value('id'), 'payment_amount' => 1, 'status' => 'cleared', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, 'customer_payments', [
            $this->customerLine('40', 'Unknown Customer'),
            $this->customerLine('41', 'Waypoint', ['Amount' => '1500', 'Invoice Number' => 'INV-1', 'Amount Applied to Invoice' => '1500']),
            $this->customerLine('42', 'Waypoint', ['Amount' => '600', 'Unused Amount' => '100', 'Invoice Number' => 'INV-1', 'Amount Applied to Invoice' => '400']),
            $this->customerLine('43', 'Waypoint', ['Invoice Number' => 'INV-DRAFT']),
            $this->customerLine('44', 'Waypoint', ['Amount' => '900', 'Invoice Number' => '', 'Amount Applied to Invoice' => '0', 'Deposit To' => 'Nowhere', 'Deposit To Account Code' => '9999999']),
            $this->customerLine('99', 'Waypoint', ['Invoice Number' => '', 'Amount Applied to Invoice' => '0', 'Unused Amount' => '1000']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'customer_payments', $company->id);

        $this->assertSame(6, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'customer_payments')->pluck('message')->all();
        $this->assertContains("Customer 'Unknown Customer' was not found in ERP. Complete the Customers step first.", $messages);
        $this->assertContains('Payments in this file apply 1,900.00 to invoice INV-1, more than its open balance of 1,000.00.', $messages);
        $this->assertContains('This payment has an unused amount of 100.00; unapplied customer payments are not supported by this import yet.', $messages);
        $this->assertContains('invoice INV-DRAFT is a draft in ERP; a payment cannot be applied to it.', $messages);
        $this->assertContains("Deposit To account 'Nowhere' (9999999) is neither a bank account nor a chart account in ERP. Complete the Bank accounts step first.", $messages);
        $this->assertContains('Applied amounts (0.00) plus the unused amount (0.00) do not equal the payment amount (900.00).', $messages);
        $this->assertContains('A customer payment with this number from this Customer already exists in ERP and this migration is configured to block duplicates.', $messages);
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
        foreach ([['1100', 'Accounts Receivable', 'debit'], ['2000', 'Accounts Payable', 'credit'], ['1020102', 'QIB - 0152752070013', 'debit'], ['1020101', 'Cash In Hand', 'debit'], ['1030101', 'Advances paid to vendors', 'debit'], ['1060101', 'Projects on Process', 'debit']] as [$code, $name, $balance]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        foreach ([['1020102', 'QIB - 0152752070013', 'checking'], ['1020101', 'Cash In Hand', 'cash']] as [$code, $name, $type]) {
            BankAccount::create(['account_name' => $name, 'account_number' => $code, 'bank_name' => 'Bank', 'account_type' => $type, 'zoho_gl_code' => $code, 'gl_account_id' => $this->account($company->id, $code)->id, 'currency_code' => 'QAR', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Payment import test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'rebuild', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => $stageKey, 'position' => 16, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function userId(User $company, string $name): int
    {
        return User::where('created_by', $company->id)->where('name', $name)->value('id');
    }

    private function invoice(User $company, string $number, int $customerId, float $total, string $status = 'posted'): SalesInvoice
    {
        return SalesInvoice::create(['invoice_number' => $number, 'invoice_date' => '2022-08-01', 'due_date' => '2022-08-01', 'customer_id' => $customerId, 'subtotal' => $total, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $total, 'paid_amount' => 0, 'balance_amount' => $total, 'status' => $status, 'type' => 'service', 'creator_id' => $company->id, 'created_by' => $company->id]);
    }

    private function bill(User $company, string $number, int $vendorId, float $total): PurchaseInvoice
    {
        return PurchaseInvoice::create(['invoice_number' => $number, 'invoice_date' => '2023-01-01', 'due_date' => '2023-01-01', 'vendor_id' => $vendorId, 'subtotal' => $total, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => $total, 'paid_amount' => 0, 'debit_note_applied' => 0, 'balance_amount' => $total, 'status' => 'posted', 'creator_id' => $company->id, 'created_by' => $company->id]);
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function customerLine(string $number, string $customer, array $extra = []): array
    {
        return $extra + [
            'Payment Number' => $number, 'CustomerPayment ID' => 'ID-'.$number, 'Mode' => 'Bank Transfer', 'CustomerID' => '1', 'Description' => '',
            'Exchange Rate' => '1', 'Amount' => '1000', 'Unused Amount' => '0', 'Bank Charges' => '0', 'Reference Number' => '', 'Currency Code' => 'QAR',
            'Customer Name' => $customer, 'Payment Type' => 'Invoice Payment', 'Date' => '2022-08-10', 'Deposit To' => 'QIB - 0152752070013',
            'Deposit To Account Code' => '1020102', 'Payment Status' => 'Paid', 'InvoicePayment ID' => '1', 'Amount Applied to Invoice' => '1000',
            'Invoice Payment Applied Date' => '2022-08-10', 'Invoice Number' => 'INV-1', 'Invoice Date' => '2022-08-01',
        ];
    }

    private function vendorLine(string $number, string $vendor, array $extra = []): array
    {
        return $extra + [
            'Payment Number' => $number, 'VendorPayment ID' => 'ID-'.$number, 'Mode' => 'Bank Transfer', 'Description' => '', 'Exchange Rate' => '1',
            'Amount' => '1000', 'Unused Amount' => '0', 'Reference Number' => '', 'Currency Code' => 'QAR', 'Bank Charges' => '0', 'Payment Status' => 'Paid',
            'Date' => '2023-02-01', 'Vendor Name' => $vendor, 'EmailID' => '', 'Paid Through' => 'QIB - 0152752070013', 'Paid Through Account Code' => '1020102',
            'Tax Account' => '', 'Bank Reference Number' => '', 'PIPayment ID' => '1', 'Bill Amount' => '1000', 'Bill Payment Applied Date' => '2023-02-01',
            'Bill Date' => '2023-01-01', 'Bill Number' => 'B-1',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, string $stageKey, array $lines): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => $stageKey, 'uploaded_by' => $batch->created_by,
            'original_filename' => 'payments.xlsx', 'disk' => 'local', 'path' => 'zoho-migrations/test/payments.xlsx',
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

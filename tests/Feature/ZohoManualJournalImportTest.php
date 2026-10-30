<?php

namespace Tests\Feature;

use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoManualJournalService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

class ZohoManualJournalImportTest extends TestCase
{
    use DatabaseTransactions;

    private const ACCOUNTS = [
        // code => [name, Zoho account type, normal balance]
        '5030101' => ['Salaries and wages', 'Expense', 'debit'],
        '3030104' => ['Salary Payable', 'Other Current Liability', 'credit'],
        '1020102' => ['QIB - 0152752070013', 'Bank', 'debit'],
        '1020201' => ['Accounts Receivable', 'Accounts Receivable', 'debit'],
        '3010101' => ['Accounts Payable', 'Accounts Payable', 'credit'],
        '3010118' => ['Accounts Payable - Danusha Tharanga', 'Other Current Liability', 'credit'],
        '4010101' => ['Sales - Social Media Management', 'Income', 'credit'],
    ];

    public function test_zoho_journal_export_is_not_mistaken_for_a_trial_balance(): void
    {
        $this->assertSame('journals.v1', app(ZohoProfileCatalog::class)->detect(array_keys($this->line('BJV001', 'J1', '5030101', 1, 0)))['profile_key']);
    }

    public function test_journals_post_with_control_accounts_bank_lines_and_settle_bills_paid_by_journal(): void
    {
        [$company, $batch] = $this->batch();
        $newoon = User::where('created_by', $company->id)->where('name', 'Newoon LLC')->value('id');
        $paid = $this->bill($company, 'INV 163/2026', $newoon, '2026-03-27');
        $open = $this->bill($company, 'INV 359/2026', $newoon, '2026-06-30');
        $this->stageFile($batch, 'purchase_invoices', [
            ['Bill Number' => 'INV 163/2026', 'Vendor Name' => 'Newoon LLC', 'Bill Status' => 'Paid', 'Balance' => '0'],
            ['Bill Number' => 'INV 359/2026', 'Vendor Name' => 'Newoon LLC', 'Bill Status' => 'Overdue', 'Balance' => '500'],
        ], 'purchase_invoices', 'bills.v1');
        $this->stage($batch, [
            // One account on several lines: every line must reach its balance.
            $this->line('BJV086', 'J1', '5030101', 4000, 0, ['Total' => '7000', 'Description' => 'Salary Mark']),
            $this->line('BJV086', 'J1', '5030101', 3000, 0, ['Total' => '7000', 'Description' => 'Salary Pasindu']),
            $this->line('BJV086', 'J1', '3030104', 0, 7000, ['Total' => '7000']),
            // Paid out of the bank.
            $this->line('BJV087', 'J2', '3030104', 7000, 0, ['Total' => '7000', 'Journal Date' => '2023-12-05']),
            $this->line('BJV087', 'J2', '1020102', 0, 7000, ['Total' => '7000', 'Journal Date' => '2023-12-05']),
            // Zoho Accounts Receivable → ERP 1100 with the customer.
            $this->line('BJV810', 'J3', '1020201', 12200, 0, ['Total' => '12200', 'Contact Name' => 'Raw ME', 'Contact Type' => 'customer', 'Journal Type' => 'Accrual']),
            $this->line('BJV810', 'J3', '4010101', 0, 12200, ['Total' => '12200', 'Journal Type' => 'Accrual']),
            // Newoon bill paid by journal.
            $this->line('BJV937', 'J4', '3010101', 500, 0, ['Total' => '500', 'Journal Date' => '2026-03-30', 'Contact Name' => 'Newoon LLC', 'Contact Type' => 'vendor']),
            $this->line('BJV937', 'J4', '3010118', 0, 500, ['Total' => '500', 'Journal Date' => '2026-03-30']),
            // Zoho's built-in Bad Debt has no code.
            $this->line('BJV911', 'J5', '', 300, 0, ['Total' => '300', 'Account' => 'Bad Debt']),
            $this->line('BJV911', 'J5', '4010101', 0, 300, ['Total' => '300']),
            // Cash basis only: not in Zoho's accrual books.
            $this->line('BJV102', 'J6', '5030101', 100, 0, ['Total' => '100', 'Journal Type' => 'Cash']),
            $this->line('BJV102', 'J6', '1020102', 0, 100, ['Total' => '100', 'Journal Type' => 'Cash']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'accounting', $company->id);
        $this->assertSame(['accepted' => 11, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $summary = app(ZohoManualJournalService::class)->import($batch, $company->id);

        $this->assertSame([5, 11, 27000.0, 1, 1, ['INV 163/2026 by BJV937'], ['BJV102 (cash basis only)']], [$summary['journals'], $summary['lines'], $summary['amount'], $summary['bank_lines'], $summary['skipped_in_zoho'], $summary['bills_settled_list'], $summary['skipped_in_zoho_journals']]);
        $this->assertSame('7000.00', (string) $this->account($company->id, '5030101')->current_balance);
        $this->assertSame('0.00', (string) $this->account($company->id, '3030104')->current_balance);

        $salary = JournalEntry::where('created_by', $company->id)->where('reference_type', 'BJV086')->sole();
        $this->assertSame(['manual', 'posted', '7000.00', '2023-11-30'], [$salary->entry_type, $salary->status, (string) $salary->total_debit, substr((string) $salary->getRawOriginal('journal_date'), 0, 10)]);

        $receivable = JournalEntry::where('created_by', $company->id)->where('reference_type', 'BJV810')->with('items')->sole();
        $this->assertSame([$this->account($company->id, '1100')->id, User::where('created_by', $company->id)->where('name', 'Raw ME')->value('id')], [$receivable->items->firstWhere('debit_amount', '>', 0)->account_id, $receivable->items->firstWhere('debit_amount', '>', 0)->contact_id]);
        $this->assertSame('-500.00', (string) $this->account($company->id, '2000')->current_balance);
        $this->assertSame('300.00', (string) $this->account($company->id, '5700')->current_balance);

        $qib = BankAccount::where('created_by', $company->id)->sole();
        $this->assertSame(['-7000.00', 1], [(string) $qib->fresh()->current_balance, BankTransaction::where('bank_account_id', $qib->id)->count()]);
        $this->assertSame(['paid', '0.00'], [$paid->fresh()->status, (string) $paid->fresh()->balance_amount]);
        $this->assertStringContainsString('Paid in Zoho by journal BJV937', $paid->fresh()->notes);
        $this->assertSame('500.00', (string) $open->fresh()->balance_amount);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->where('reference_type', 'BJV102')->count());
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->batch();
        $newoon = User::where('created_by', $company->id)->where('name', 'Newoon LLC')->value('id');
        $this->bill($company, 'INV 293/2026', $newoon, '2026-05-29');
        $this->stageFile($batch, 'purchase_invoices', [['Bill Number' => 'INV 293/2026', 'Vendor Name' => 'Newoon LLC', 'Bill Status' => 'Paid', 'Balance' => '0']], 'purchase_invoices', 'bills.v1');
        $this->stage($batch, [
            $this->line('BJV001', 'J1', '5030101', 100, 0, ['Total' => '100']),
            $this->line('BJV001', 'J1', '3030104', 0, 90, ['Total' => '100']),
            $this->line('BJV002', 'J2', '5999999', 50, 0, ['Total' => '50', 'Account' => 'Missing']),
            $this->line('BJV002', 'J2', '3030104', 0, 50, ['Total' => '50']),
            $this->line('BJV003', 'J3', '1020201', 50, 0, ['Total' => '50', 'Contact Name' => 'Nobody', 'Contact Type' => 'customer']),
            $this->line('BJV003', 'J3', '4010101', 0, 50, ['Total' => '50']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'accounting', $company->id);

        $this->assertSame(3, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'accounting')->pluck('message')->all();
        $this->assertContains('Debits (100.00) and credits (90.00) of this journal differ.', $messages);
        $this->assertContains('Account 5999999 (Missing) was not found in ERP. Complete the Chart of Accounts step first.', $messages);
        $this->assertContains("Customer 'Nobody' was not found in ERP. Complete the Customers and Vendors steps first.", $messages);
        $this->assertContains('Bill INV 293/2026 of Newoon LLC is paid in Zoho but has 500.00 open in ERP, and no Zoho journal to Accounts Payable for this vendor pays it. Complete the Credit applications step first.', $messages);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->count());
    }

    private function batch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        User::factory()->create(['type' => 'client', 'name' => 'Raw ME', 'created_by' => $company->id]);
        User::factory()->create(['type' => 'vendor', 'name' => 'Newoon LLC', 'created_by' => $company->id]);
        $typeId = DB::table('account_types')->value('id');
        $accounts = self::ACCOUNTS + ['1100' => ['Accounts Receivable', null, 'debit'], '2000' => ['Accounts Payable', null, 'credit'], '5700' => ['Bad Debt Expense', null, 'debit']];
        foreach ($accounts as $code => [$name, , $balance]) {
            ChartOfAccount::create(['account_code' => (string) $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        BankAccount::create(['account_name' => 'QIB - 0152752070013', 'account_number' => null, 'bank_name' => 'QIB', 'account_type' => 'checking', 'zoho_gl_code' => '1020102', 'gl_account_id' => $this->account($company->id, '1020102')->id, 'currency_code' => 'QAR', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Journal import test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'full_history', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'accounting', 'position' => 22, 'status' => 'uploaded']);
        $chart = array_map(fn ($code, array $account) => ['Account ID' => 'Z'.$code, 'Account Code' => (string) $code, 'Account Name' => $account[0], 'Account Type' => $account[1]], array_keys(self::ACCOUNTS), self::ACCOUNTS);
        $this->stageFile($batch, 'chart_of_accounts', $chart, 'chart_of_accounts', 'chart_of_accounts.v1');

        return [$company, $batch];
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function bill(User $company, string $number, int $vendorId, string $date): PurchaseInvoice
    {
        return PurchaseInvoice::create(['invoice_number' => $number, 'invoice_date' => $date, 'due_date' => $date, 'vendor_id' => $vendorId, 'subtotal' => 500, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 500, 'paid_amount' => 0, 'debit_note_applied' => 0, 'balance_amount' => 500, 'status' => 'posted', 'creator_id' => $company->id, 'created_by' => $company->id]);
    }

    private function line(string $number, string $id, string $code, float $debit, float $credit, array $extra = []): array
    {
        return $extra + [
            'Journal Number' => $number, 'Journal Number Prefix' => 'BJV', 'Journal Number Suffix' => substr($number, 3), 'Journal Created By' => 'Ayesha',
            'Journal Date' => '2023-11-30', 'Journal ID' => $id, 'Journal Type' => 'Both', 'Status' => 'Published', 'Journal Entity Type' => 'Journal',
            'Reference Number' => '', 'Notes' => 'Zoho note', 'Exchange Rate' => '1.000000000000', 'Item Order' => '1', 'Project Name' => '',
            'Debit' => (string) $debit, 'Credit' => (string) $credit, 'Account' => self::ACCOUNTS[$code][0] ?? '', 'Account Code' => $code,
            'Contact Name' => '', 'Contact Type' => '', 'Currency' => 'QAR', 'Total' => '0', 'Description' => '',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, array $lines): void
    {
        $this->stageFile($batch, 'accounting', $lines, 'manual_journals', 'journals.v1');
    }

    private function stageFile(ZohoMigrationBatch $batch, string $stageKey, array $lines, string $dataset, string $profile): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => $stageKey, 'uploaded_by' => $batch->created_by,
            'original_filename' => $stageKey.'.xlsx', 'disk' => 'local', 'path' => 'zoho-migrations/test/'.$stageKey.'.xlsx',
            'sha256' => hash('sha256', $stageKey.microtime(true)), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'staged',
            'dataset_type' => $dataset, 'profile_key' => $profile, 'detection_confidence' => 100, 'headers' => array_keys($lines[0]), 'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $batch->tenant_id, 'sheet_name' => 'Sheet1',
                'source_row_number' => $index + 2, 'original_values' => $line, 'status' => 'staged',
            ]);
        }
    }
}

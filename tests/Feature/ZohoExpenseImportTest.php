<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoExpenseService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\JournalEntry;

class ZohoExpenseImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoho_expense_export_is_recognised(): void
    {
        $this->assertSame('expenses.v1', app(ZohoProfileCatalog::class)->detect(array_keys($this->line('1', 'E1')))['profile_key']);
    }

    public function test_expenses_post_per_line_with_categories_and_clearing_accounts_created_where_needed(): void
    {
        [$company, $batch] = $this->batch();
        $rent = ExpenseCategories::create(['category_name' => 'Rent - Office (Short Term)', 'category_code' => '5030201', 'is_active' => true, 'gl_account_id' => $this->account($company->id, '5030201')->id, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->line('1', 'E1', ['Expense Amount' => '6250', 'Total' => '6250', 'Expense Description' => 'Office rent', 'Reference#' => 'CHQ # 26']),
            // Itemized: two lines on two accounts, one of them not an expense account.
            $this->line('9', 'E9', ['Expense Amount' => '4026', 'Total' => '7126', 'Expense Account' => 'Staff Advance', 'Expense Account Code' => '1060102']),
            $this->line('9', 'E9', ['Expense Amount' => '3100', 'Total' => '7126']),
            // Paid through Salary Payable, which is not a bank account.
            $this->line('12', 'E12', ['Expense Amount' => '900', 'Total' => '900', 'Expense Account' => 'Staff Advance', 'Expense Account Code' => '1060102', 'Paid Through' => 'Salary Payable', 'Paid Through Account Code' => '3030104']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id);
        $this->assertSame(['accepted' => 4, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $summary = app(ZohoExpenseService::class)->import($batch, $company->id);

        $this->assertSame(['expenses' => 3, 'erp_expenses' => 4, 'amount' => 14276.0, 'skipped_existing' => 0, 'imported_earlier' => 0, 'categories_created' => 1, 'clearing_accounts_created' => 1, 'categories_created_names' => ['Staff Advance']], $summary);
        $this->assertSame(['ZEXP-1', 'ZEXP-12', 'ZEXP-9-1', 'ZEXP-9-2'], Expense::where('created_by', $company->id)->orderBy('expense_number')->pluck('expense_number')->all());

        $qib = BankAccount::where('created_by', $company->id)->where('zoho_gl_code', '1020102')->sole();
        $first = Expense::where('created_by', $company->id)->where('expense_number', 'ZEXP-1')->sole();
        $this->assertSame(['2022-08-10', $rent->id, $qib->id, $this->account($company->id, '5030201')->id, '6250.00', 'posted', 'Office rent', 'CHQ # 26', 'QAR', '6250.00'], [$first->expense_date->format('Y-m-d'), $first->category_id, $first->bank_account_id, $first->chart_of_account_id, (string) $first->amount, $first->status, $first->description, $first->reference_number, $first->currency_code, (string) $first->base_amount]);
        $journal = JournalEntry::where('reference_type', 'expense')->where('reference_id', $first->id)->with('items')->sole();
        $this->assertSame([
            $this->account($company->id, '5030201')->id => ['6250.00', '0.00'],
            $this->account($company->id, '1020102')->id => ['0.00', '6250.00'],
        ], $journal->items->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount]])->all());

        $advance = ExpenseCategories::where('created_by', $company->id)->where('category_name', 'Staff Advance')->sole();
        $this->assertSame(['1060102', $this->account($company->id, '1060102')->id], [$advance->category_code, $advance->gl_account_id]);
        $this->assertSame($advance->id, Expense::where('created_by', $company->id)->where('expense_number', 'ZEXP-9-1')->value('category_id'));

        $this->assertSame('-13376.00', (string) $qib->fresh()->current_balance);
        $this->assertSame(3, BankTransaction::where('bank_account_id', $qib->id)->where('transaction_type', 'debit')->count());
        $clearing = BankAccount::where('created_by', $company->id)->where('account_type', 'clearing_account')->sole();
        $this->assertSame(['Salary Payable', $this->account($company->id, '3030104')->id, '-900.00'], [$clearing->account_name, $clearing->gl_account_id, (string) $clearing->current_balance]);
        $this->assertSame('9350.00', (string) $this->account($company->id, '5030201')->current_balance);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'income_expenses')->value('status'));
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->batch();
        Expense::create(['expense_number' => 'ZEXP-5', 'expense_date' => '2022-01-01', 'category_id' => ExpenseCategories::create(['category_name' => 'Old', 'category_code' => 'OLD', 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id])->id, 'bank_account_id' => BankAccount::where('created_by', $company->id)->value('id'), 'amount' => 1, 'status' => 'posted', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->line('1', 'E1', ['Expense Account' => 'Missing', 'Expense Account Code' => '5999999']),
            $this->line('2', 'E2', ['Total' => '999']),
            $this->line('3', 'E3', ['Tax Amount' => '50']),
            $this->line('4', 'E4', ['Paid Through' => 'Nowhere', 'Paid Through Account Code' => '9999999']),
            $this->line('5', 'E5'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id);

        $this->assertSame(5, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'income_expenses')->pluck('message')->all();
        $this->assertContains("Expense Account 'Missing' (5999999) was not found in ERP. Complete the Chart of Accounts step first.", $messages);
        $this->assertContains('Line amounts add up to 1,000.00 but the Zoho expense total is 999.00.', $messages);
        $this->assertContains('Expenses with tax are not supported by this import yet.', $messages);
        $this->assertContains("Paid Through account 'Nowhere' (9999999) is neither a bank account nor a chart account in ERP. Complete the Bank accounts step first.", $messages);
        $this->assertContains('Expense ZEXP-5 already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->count());
    }

    public function test_a_newer_export_imports_only_the_new_expenses(): void
    {
        [$company, $batch] = $this->batch();
        $this->stage($batch, [
            $this->line('1', 'E1'),
            $this->line('9', 'E9', ['Expense Amount' => '400', 'Total' => '700']),
            $this->line('9', 'E9', ['Expense Amount' => '300', 'Total' => '700']),
        ]);
        app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id);
        app(ZohoExpenseService::class)->import($batch, $company->id);

        // The newer export has the same expenses plus one recorded after the first export.
        $this->stage($batch, [
            $this->line('1', 'E1'),
            $this->line('9', 'E9', ['Expense Amount' => '400', 'Total' => '700']),
            $this->line('9', 'E9', ['Expense Amount' => '300', 'Total' => '700']),
            $this->line('10', 'E10', ['Expense Amount' => '250', 'Total' => '250', 'Expense Date' => '2022-09-01']),
        ]);
        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id);
        $this->assertSame(['accepted' => 1, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $summary = app(ZohoExpenseService::class)->import($batch, $company->id);

        $this->assertSame([1, 1, 250.0, 3], [$summary['expenses'], $summary['erp_expenses'], $summary['amount'], $summary['imported_earlier']]);
        $this->assertSame(['ZEXP-1', 'ZEXP-10', 'ZEXP-9-1', 'ZEXP-9-2'], Expense::where('created_by', $company->id)->orderBy('expense_number')->pluck('expense_number')->all());
        $this->assertSame(4, JournalEntry::where('created_by', $company->id)->count());
        $this->assertSame('-1950.00', (string) BankAccount::where('created_by', $company->id)->where('zoho_gl_code', '1020102')->value('current_balance'));
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'income_expenses')->value('status'));

        // The same export again: nothing new, the step completes without writing anything.
        $this->stage($batch, [
            $this->line('1', 'E1'),
            $this->line('9', 'E9', ['Expense Amount' => '400', 'Total' => '700']),
            $this->line('9', 'E9', ['Expense Amount' => '300', 'Total' => '700']),
            $this->line('10', 'E10', ['Expense Amount' => '250', 'Total' => '250', 'Expense Date' => '2022-09-01']),
        ]);
        $this->assertSame(0, app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id)['accepted']);
        $this->assertSame(4, app(ZohoExpenseService::class)->import($batch, $company->id)['imported_earlier']);
        $this->assertSame(4, JournalEntry::where('created_by', $company->id)->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'income_expenses')->value('status'));
    }

    public function test_a_newer_export_that_changed_or_lost_imported_expenses_is_rejected(): void
    {
        [$company, $batch] = $this->batch();
        $this->stage($batch, [$this->line('1', 'E1'), $this->line('2', 'E2')]);
        app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id);
        app(ZohoExpenseService::class)->import($batch, $company->id);

        // Expense 1 was edited in Zoho after the import and expense 2 deleted.
        $this->stage($batch, [$this->line('1', 'E1', ['Expense Amount' => '1100', 'Total' => '1100', 'Expense Date' => '2022-08-11'])]);
        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'income_expenses', $company->id);

        $this->assertSame(1, $validation['rejected']);
        $this->assertEqualsCanonicalizing([
            'Expense ZEXP-1 was imported earlier, but its amount (1,000.00 in ERP, 1,100.00 in Zoho), date changed in Zoho since. Imported expenses are posted and are not changed by a new upload; correct the ERP expense to match Zoho, then recheck.',
            'Expense entry 2 was imported from an earlier export but is not in this file. Upload a full export of all expenses; if it was deleted in Zoho, delete the ERP expense too.',
        ], $batch->issues()->where('stage_key', 'income_expenses')->pluck('message')->all());
        $this->assertSame(2, JournalEntry::where('created_by', $company->id)->count());
    }

    private function batch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $typeId = DB::table('account_types')->value('id');
        foreach ([['1020102', 'QIB - 0152752070013', 'debit'], ['5030201', 'Rent - Office (Short Term)', 'debit'], ['1060102', 'Staff Advance', 'debit'], ['3030104', 'Salary Payable', 'credit']] as [$code, $name, $balance]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        BankAccount::create(['account_name' => 'QIB - 0152752070013', 'account_number' => '0152752070013', 'bank_name' => 'QIB', 'account_type' => 'checking', 'zoho_gl_code' => '1020102', 'gl_account_id' => $this->account($company->id, '1020102')->id, 'currency_code' => 'QAR', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Expense import test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'rebuild', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'income_expenses', 'position' => 19, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function line(string $entry, string $id, array $extra = []): array
    {
        return $extra + [
            'Expense Date' => '2022-08-10', 'Expense Description' => '', 'Expense Account' => 'Rent - Office (Short Term)', 'Expense Account Code' => '5030201',
            'Paid Through' => 'QIB - 0152752070013', 'Paid Through Account Code' => '1020102', 'Vendor' => '', 'Project Name' => '', 'Entry Number' => $entry,
            'Currency Code' => 'QAR', 'Exchange Rate' => '1', 'Is Inclusive Tax' => 'false', 'Tax Name' => '', 'Tax Amount' => '0.000000',
            'Expense Amount' => '1000.000000', 'Total' => '1000.000', 'Reference#' => '', 'Is Billable' => 'false', 'Customer Name' => '',
            'Expense Reference ID' => $id, 'Is Reimbursable' => 'false',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, array $lines): void
    {
        // As an upload does: the earlier file of the stage is superseded.
        $batch->files()->where('stage_key', 'income_expenses')->update(['status' => 'superseded']);
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => 'income_expenses', 'uploaded_by' => $batch->created_by,
            'original_filename' => 'expenses.xlsx', 'disk' => 'local', 'path' => 'zoho-migrations/test/expenses.xlsx',
            'sha256' => hash('sha256', (string) microtime(true)), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'staged',
            'dataset_type' => 'expenses', 'profile_key' => 'expenses.v1', 'detection_confidence' => 100, 'headers' => array_keys($lines[0]), 'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $batch->tenant_id, 'sheet_name' => 'Sheet1',
                'source_row_number' => $index + 2, 'original_values' => $line, 'status' => 'staged',
            ]);
        }
    }
}

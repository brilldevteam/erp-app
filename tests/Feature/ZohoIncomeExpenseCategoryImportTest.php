<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoIncomeExpenseCategoryService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\RevenueCategories;

class ZohoIncomeExpenseCategoryImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_income_and_expense_accounts_become_linked_categories_and_other_types_are_skipped(): void
    {
        [$company, $batch] = $this->categoriesBatch(['4010102' => 'Sales - Branding', '7010309' => 'Bank Charges', '5010103' => 'Subscriptions', '3030104' => 'Salary Payable']);
        $this->stage($batch, [
            $this->account('Sales - Branding', '4010102', 'Income', ['Description' => 'Branding work']),
            $this->account('Bank Charges', '7010309 ', 'Other Expense'),
            $this->account('Subscriptions', '5010103', 'Cost Of Goods Sold', ['Account Status' => 'Inactive']),
            $this->account('Salary Payable', '3030104', 'Other Current Liability'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'income_expense_categories', $company->id);
        $this->assertSame(['accepted' => 3, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $this->assertSame('skipped', ZohoMigrationStagedRow::where('batch_id', $batch->id)->where('source_row_number', 5)->value('status'));

        $counts = app(ZohoIncomeExpenseCategoryService::class)->import($batch, $company->id);

        $this->assertSame(['imported' => 3, 'updated' => 0, 'skipped' => 0, 'revenue_categories' => 1, 'expense_categories' => 2], $counts);
        $chart = fn (string $code) => ChartOfAccount::where('created_by', $company->id)->where('account_code', $code)->value('id');
        $revenue = RevenueCategories::where('created_by', $company->id)->firstOrFail();
        $this->assertSame(['Sales - Branding', '4010102', 'Branding work', $chart('4010102')], [$revenue->category_name, $revenue->category_code, $revenue->description, $revenue->gl_account_id]);
        $bank = ExpenseCategories::where('created_by', $company->id)->where('category_name', 'Bank Charges')->firstOrFail();
        $this->assertSame(['7010309', $chart('7010309')], [$bank->category_code, $bank->gl_account_id]);
        $this->assertFalse((bool) ExpenseCategories::where('created_by', $company->id)->where('category_name', 'Subscriptions')->value('is_active'));
        $this->assertSame(0, ExpenseCategories::where('created_by', $company->id)->where('category_name', 'Salary Payable')->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'income_expense_categories')->value('status'));
    }

    public function test_missing_chart_accounts_existing_categories_and_long_descriptions_are_reported(): void
    {
        [$company, $batch] = $this->categoriesBatch(['7010309' => 'Bank Charges', '7010101' => 'Telephone & Internet']);
        ExpenseCategories::create(['category_name' => 'Bank Charges', 'category_code' => 'OLD-1', 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->account('Bank Charges', '7010309', 'Other Expense'),
            $this->account('Travelling Expenses', '7010314', 'Other Expense'),
            $this->account('Telephone & Internet', '7010101', 'Other Expense', ['Description' => str_repeat('x', 256)]),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'income_expense_categories', $company->id);

        $this->assertSame(3, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'income_expense_categories')->pluck('message')->all();
        $this->assertContains('An expense category with this name or code already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertContains('Chart account 7010314 (Travelling Expenses) was not found in ERP. Complete the Chart of Accounts step with the same Zoho export first.', $messages);
        $this->assertContains('Description is longer than 255 characters (256). Shorten it in the file, then upload it again.', $messages);
    }

    private function categoriesBatch(array $chartAccounts): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $accountTypeId = DB::table('account_types')->value('id');
        foreach ($chartAccounts as $code => $name) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $accountTypeId, 'normal_balance' => 'debit', 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Category import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'income_expense_categories', 'position' => 10, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function account(string $name, string $code, string $type, array $extra = []): array
    {
        return $extra + ['Account ID' => (string) random_int(1000, 999999), 'Account Name' => $name, 'Account Code' => $code, 'Description' => '', 'Account Type' => $type, 'Account Status' => 'Active', 'Currency' => 'QAR'];
    }

    private function stage(ZohoMigrationBatch $batch, array $accounts): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'income_expense_categories',
            'uploaded_by' => $batch->created_by,
            'original_filename' => 'Chart_of_Accounts.xlsx',
            'disk' => 'local',
            'path' => 'zoho-migrations/test/chart.xlsx',
            'sha256' => hash('sha256', (string) microtime(true)),
            'size_bytes' => 1,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => 'staged',
            'dataset_type' => 'chart_of_accounts',
            'profile_key' => 'chart_of_accounts.v1',
            'detection_confidence' => 100,
            'headers' => array_keys($accounts[0]),
            'row_count' => count($accounts),
        ]);
        foreach ($accounts as $index => $account) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'tenant_id' => $batch->tenant_id,
                'sheet_name' => 'Chart of Accounts',
                'source_row_number' => $index + 2,
                'source_external_id' => $account['Account ID'],
                'original_values' => $account,
                'status' => 'staged',
            ]);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoBankAccountService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;

class ZohoBankAccountImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bank_cash_and_card_rows_of_a_chart_export_become_linked_bank_accounts(): void
    {
        [$company, $batch] = $this->bankBatch(['1020102' => 'QIB - 0152752070013', '1020101' => 'Cash In Hand', '3010112' => 'Credit Card']);
        $this->stage($batch, [
            $this->account('QIB - 0152752070013', '1020102', 'Bank'),
            $this->account('Cash In Hand', '1020101', 'Cash'),
            $this->account('Credit Card', '3010112', 'Credit Card', ['Currency' => 'USD', 'Account Status' => 'Inactive']),
            $this->account('Bank Charges', '7010309 ', 'Other Expense'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'payment_accounts', $company->id);
        $this->assertSame(['accepted' => 3, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $this->assertSame('skipped', ZohoMigrationStagedRow::where('batch_id', $batch->id)->where('source_row_number', 5)->value('status'));

        $counts = app(ZohoBankAccountService::class)->import($batch, $company->id);

        $this->assertSame(['imported' => 3, 'updated' => 0, 'skipped' => 0], $counts);
        $qib = BankAccount::where('created_by', $company->id)->where('account_name', 'QIB - 0152752070013')->firstOrFail();
        $this->assertNull($qib->account_number, 'Zoho has no verified account number, so it stays empty for finance to fill in.');
        $this->assertSame('1020102', $qib->zoho_gl_code);
        $this->assertSame('Pending verification', $qib->bank_name);
        $this->assertSame('checking', $qib->account_type);
        $this->assertSame(ChartOfAccount::where('created_by', $company->id)->where('account_code', '1020102')->value('id'), $qib->gl_account_id);
        $cash = BankAccount::where('created_by', $company->id)->where('account_name', 'Cash In Hand')->firstOrFail();
        $this->assertSame(['Cash', 'cash'], [$cash->bank_name, $cash->account_type]);
        $card = BankAccount::where('created_by', $company->id)->where('account_name', 'Credit Card')->firstOrFail();
        $this->assertSame(['credit', 'USD', false], [$card->account_type, $card->currency_code, (bool) $card->is_active]);
        $this->assertSame(0, BankAccount::where('created_by', $company->id)->where('account_name', 'Bank Charges')->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'payment_accounts')->value('status'));
    }

    public function test_accounts_missing_from_the_chart_or_already_in_erp_are_reported(): void
    {
        [$company, $batch] = $this->bankBatch(['1020101' => 'Cash In Hand']);
        BankAccount::create(['account_name' => 'Cash In Hand', 'bank_name' => 'Cash', 'account_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->account('Cash In Hand', '1020101', 'Cash'),
            $this->account('Petty Cash', '1020103', 'Cash'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'payment_accounts', $company->id);

        $this->assertSame(2, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'payment_accounts')->pluck('message')->all();
        $this->assertContains('A bank account with this name already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertContains('Chart account 1020103 (Petty Cash) was not found in ERP. Complete the Chart of Accounts step with the same Zoho export first.', $messages);
    }

    public function test_the_chart_export_can_feed_both_stages_but_not_twice_in_one_stage(): void
    {
        [, $batch] = $this->bankBatch([]);
        $file = fn (string $stageKey) => ZohoMigrationFile::create([
            'batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => $stageKey, 'uploaded_by' => $batch->created_by,
            'original_filename' => 'Chart_of_Accounts.xlsx', 'disk' => 'local', 'path' => "zoho-migrations/test/{$stageKey}.xlsx",
            'sha256' => str_repeat('a', 64), 'size_bytes' => 1, 'mime_type' => 'application/octet-stream', 'status' => 'uploaded',
        ]);

        $file('chart_of_accounts');
        $file('payment_accounts');
        $this->assertSame(2, $batch->files()->where('sha256', str_repeat('a', 64))->count());

        $this->expectException(\Illuminate\Database\QueryException::class);
        $file('payment_accounts');
    }

    private function bankBatch(array $chartAccounts): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $accountTypeId = DB::table('account_types')->value('id');
        foreach ($chartAccounts as $code => $name) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $accountTypeId, 'normal_balance' => 'debit', 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Bank account import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'payment_accounts', 'position' => 9, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function account(string $name, string $code, string $type, array $extra = []): array
    {
        return $extra + ['Account ID' => (string) random_int(1000, 999999), 'Account Name' => $name, 'Account Code' => $code, 'Account Type' => $type, 'Account # ' => '', 'Account Status' => 'Active', 'Currency' => 'QAR'];
    }

    private function stage(ZohoMigrationBatch $batch, array $accounts): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'payment_accounts',
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

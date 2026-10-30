<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoBankActivityService;
use App\Services\ZohoMigration\ZohoMigrationFileInspector;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\BankTransfer;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\RevenueCategories;

class ZohoBankActivityImportTest extends TestCase
{
    use DatabaseTransactions;

    private const ACCOUNTS = [
        // Zoho account id => [code, name, normal balance, bank type]
        'Z-QIB' => ['1020102', 'QIB - 0152752070013', 'debit', 'checking'],
        'Z-CASH' => ['1020101', 'Cash In Hand', 'debit', 'cash'],
        'Z-LOAN' => ['3030201', 'Loan from Partners - Saddam Al Saddi', 'credit', null],
        'Z-OWNER' => ['2010108', 'Sarath Kumara Ganegoda', 'credit', null],
        'Z-RENT' => ['5030201', 'Rent - Office (Short Term)', 'debit', null],
        'Z-OTHER' => ['6010101', 'Other Income', 'credit', null],
        'Z-AR' => ['1020201', 'Accounts Receivable', 'debit', null],
    ];

    public function test_zoho_ledger_report_with_a_title_row_is_staged_from_its_second_row(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->batch();
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([["Brill Creations\nDetailed General Ledger\nBasis: Accrual"], array_keys($this->leg('T1', '2024-05-22', 'Z-QIB', 100, 0, 'transfer_fund'))], null, 'A1', true);
        $sheet->fromArray(array_values($this->leg('T1', '2024-05-22', 'Z-QIB', 100, 0, 'transfer_fund')), null, 'A3', true);
        $path = 'zoho-migrations/test/ledger.xlsx';
        Storage::disk('local')->makeDirectory('zoho-migrations/test');
        (new Xlsx($sheet->getParent()))->save(Storage::disk('local')->path($path));
        $file = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'bank_activity', 'uploaded_by' => $company->id, 'original_filename' => 'ledger.xlsx', 'disk' => 'local', 'path' => $path, 'sha256' => hash('sha256', 'ledger'), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'uploaded']);

        app(ZohoMigrationFileInspector::class)->inspectAndStage($file);

        $file->refresh();
        $this->assertSame(['ledger_report.v1', 'general_ledger', 1], [$file->profile_key, $file->dataset_type, $file->row_count]);
        $row = $file->stagedRows()->sole();
        $this->assertSame([3, 'T1', 'Z-QIB'], [$row->source_row_number, $row->original_values['transaction_id'], $row->original_values['account_id']]);
    }

    public function test_bank_activity_imports_transfers_journals_and_revenues_and_skips_other_ledger_rows(): void
    {
        [$company, $batch] = $this->batch();
        $this->stage($batch, [
            // Bank to bank: an ERP bank transfer.
            $this->leg('T1', '2022-08-10', 'Z-CASH', 300, 0, 'transfer_fund', 'Cash Withdrawal'),
            $this->leg('T1', '2022-08-10', 'Z-QIB', 0, 300, 'transfer_fund', 'Cash Withdrawal'),
            // Partner loan into the bank: a bank journal.
            $this->leg('T2', '2023-01-05', 'Z-QIB', 5000, 0, 'transfer_fund', 'Loan from Mr. Saddam'),
            $this->leg('T2', '2023-01-05', 'Z-LOAN', 0, 5000, 'transfer_fund', 'Loan from Mr. Saddam'),
            $this->leg('T3', '2024-05-22', 'Z-QIB', 15000, 0, 'owner_contribution'),
            $this->leg('T3', '2024-05-22', 'Z-OWNER', 0, 15000, 'owner_contribution'),
            $this->leg('T4', '2022-06-01', 'Z-QIB', 1500, 0, 'expense_refund', 'Office rent from Toppazaar'),
            $this->leg('T4', '2022-06-01', 'Z-RENT', 0, 1500, 'expense_refund', 'Office rent from Toppazaar'),
            $this->leg('T5', '2024-10-30', 'Z-OTHER', 0, 9.95, 'interest_income', 'FT24304J95RZ'),
            $this->leg('T5', '2024-10-30', 'Z-QIB', 9.95, 0, 'interest_income', 'FT24304J95RZ'),
            // Belongs to other steps.
            $this->leg('T6', '2022-01-01', 'Z-AR', 700, 0, 'invoice'),
            $this->leg('T7', '2022-04-10', 'Z-QIB', 0, 2250, 'creditnote_refund'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'bank_activity', $company->id);
        $this->assertSame(['accepted' => 10, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $summary = app(ZohoBankActivityService::class)->import($batch, $company->id);

        $this->assertSame([5, 21809.95, 1, 3, 1, 1], [$summary['transactions'], $summary['amount'], $summary['bank_transfers'], $summary['bank_journals'], $summary['revenues'], $summary['revenue_categories_created']]);
        $qib = $this->bank($company->id, '1020102');
        $cash = $this->bank($company->id, '1020101');

        $transfer = BankTransfer::where('created_by', $company->id)->sole();
        $this->assertSame(['ZTRF-T1', '2022-08-10', $qib->id, $cash->id, '300.00', 'completed', 'Cash Withdrawal'], [$transfer->transfer_number, $transfer->transfer_date->format('Y-m-d'), $transfer->from_account_id, $transfer->to_account_id, (string) $transfer->transfer_amount, $transfer->status, $transfer->reference_number]);
        $this->assertSame([
            $this->account($company->id, '1020101')->id => ['300.00', '0.00'],
            $this->account($company->id, '1020102')->id => ['0.00', '300.00'],
        ], $this->lines(JournalEntry::findOrFail($transfer->journal_entry_id)));

        $loan = JournalEntry::where('created_by', $company->id)->where('reference_type', 'transfer_fund')->sole();
        $this->assertSame([
            $this->account($company->id, '1020102')->id => ['5000.00', '0.00'],
            $this->account($company->id, '3030201')->id => ['0.00', '5000.00'],
        ], $this->lines($loan));
        $this->assertSame(1, JournalEntry::where('created_by', $company->id)->where('reference_type', 'owner_contribution')->count());
        $this->assertSame('-1500.00', (string) $this->account($company->id, '5030201')->current_balance);

        $revenue = Revenue::where('created_by', $company->id)->sole();
        $category = RevenueCategories::findOrFail($revenue->category_id);
        $this->assertSame(['ZREV-T5', '9.95', 'posted', $qib->id, 'Other Income', '6010101'], [$revenue->revenue_number, (string) $revenue->amount, $revenue->status, $revenue->bank_account_id, $category->category_name, $category->category_code]);

        // QIB: +5000 +15000 +1500 +9.95 −300; cash +300.
        $this->assertSame(['21209.95', '300.00'], [(string) $qib->fresh()->current_balance, (string) $cash->fresh()->current_balance]);
        $this->assertSame(5, BankTransaction::where('bank_account_id', $qib->id)->count());
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->whereColumn('total_debit', '!=', 'total_credit')->count());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'bank_activity')->value('status'));
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->batch();
        BankTransfer::create(['transfer_number' => 'ZTRF-T3', 'transfer_date' => '2022-01-01', 'from_account_id' => $this->bank($company->id, '1020102')->id, 'to_account_id' => $this->bank($company->id, '1020101')->id, 'transfer_amount' => 1, 'transfer_charges' => 0, 'description' => 'Imported earlier', 'status' => 'completed', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            // Only one side: an Account Transactions report instead of the full ledger.
            $this->leg('T1', '2022-08-10', 'Z-QIB', 0, 300, 'transfer_fund'),
            $this->leg('T2', '2022-08-10', 'Z-UNKNOWN', 300, 0, 'transfer_fund'),
            $this->leg('T2', '2022-08-10', 'Z-QIB', 0, 300, 'transfer_fund'),
            $this->leg('T3', '2022-08-11', 'Z-CASH', 50, 0, 'transfer_fund'),
            $this->leg('T3', '2022-08-11', 'Z-QIB', 0, 50, 'transfer_fund'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'bank_activity', $company->id);

        $this->assertSame(3, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'bank_activity')->pluck('message')->all();
        $this->assertContains("Only one side of this transfer is in the file. Upload the Detailed General Ledger for all accounts, not a single account's transactions.", $messages);
        $this->assertContains("Account 'Somewhere' (Zoho id Z-UNKNOWN) is not in the Chart of Accounts export of this migration. Upload a current Chart of Accounts export in step 2.", $messages);
        $this->assertContains('This transfer (ZTRF-T3) already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertSame(0, JournalEntry::where('created_by', $company->id)->count());
    }

    private function batch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $typeId = DB::table('account_types')->value('id');
        foreach (self::ACCOUNTS as [$code, $name, $balance, $bankType]) {
            $account = ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type_id' => $typeId, 'normal_balance' => $balance, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
            if ($bankType) {
                BankAccount::create(['account_name' => $name, 'account_number' => null, 'bank_name' => 'Bank', 'account_type' => $bankType, 'zoho_gl_code' => $code, 'gl_account_id' => $account->id, 'currency_code' => 'QAR', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
            }
        }
        // ERP's own default "Accounts Receivable" shares the Zoho account's name; only the Zoho id tells them apart.
        ChartOfAccount::create(['account_code' => '1100', 'account_name' => 'Accounts Receivable', 'account_type_id' => $typeId, 'normal_balance' => 'debit', 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Bank activity test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'rebuild', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'bank_activity', 'position' => 20, 'status' => 'uploaded']);
        // The step 2 Chart of Accounts export, which maps Zoho account ids to codes.
        $chart = array_map(fn (string $id, array $account) => ['Account ID' => $id, 'Account Code' => $account[0], 'Account Name' => $account[1], 'Account Type' => 'Bank'], array_keys(self::ACCOUNTS), self::ACCOUNTS);
        $this->stageFile($batch, 'chart_of_accounts', $chart, 'chart_of_accounts', 'chart_of_accounts.v1');

        return [$company, $batch];
    }

    private function bank(int $tenantId, string $code): BankAccount
    {
        return BankAccount::where('created_by', $tenantId)->where('zoho_gl_code', $code)->firstOrFail();
    }

    private function account(int $tenantId, string $code): ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->firstOrFail();
    }

    private function lines(JournalEntry $journal): array
    {
        return $journal->items()->get()->mapWithKeys(fn ($item) => [$item->account_id => [(string) $item->debit_amount, (string) $item->credit_amount]])->all();
    }

    private function leg(string $transaction, string $date, string $accountId, float $debit, float $credit, string $type, string $reference = ''): array
    {
        return [
            'date' => $date, 'account_name' => self::ACCOUNTS[$accountId][1] ?? 'Somewhere', 'transaction_details' => '', 'transaction_id' => $transaction,
            'reference_transaction_id' => '', 'offset_account_id' => '', 'offset_account_type' => '', 'transaction_type' => $type, 'reference_number' => $reference,
            'entity_number' => '', 'debit' => (string) $debit, 'credit' => (string) $credit, 'net_amount' => '', 'contact_id' => '-1', 'account_id' => $accountId,
            'project_ids' => '', 'running_balance' => '', 'currency_code' => 'QAR', 'account_group' => '', 'account_type' => '',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, array $lines): void
    {
        $this->stageFile($batch, 'bank_activity', $lines, 'general_ledger', 'ledger_report.v1');
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
                'source_row_number' => $index + 3, 'original_values' => $line, 'status' => 'staged',
            ]);
        }
    }
}

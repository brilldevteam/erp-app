<?php

namespace Tests\Feature;

use App\Jobs\InspectZohoMigrationFile;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoJournalWriter;
use App\Services\ZohoMigration\ZohoMigrationFileInspector;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoReconciliationService;
use App\Services\ZohoMigration\ZohoStageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;

class ZohoReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private const ACCOUNTS = [
        // code => [name, Zoho type, ERP category]
        '1020102' => ['QIB', 'Bank', 'Assets'],
        '1020201' => ['Accounts Receivable', 'Accounts Receivable', null],
        '2020101' => ['Retained Earnings', 'Equity', 'Equity'],
        '4010101' => ['Sales - Social Media Management', 'Income', 'Revenue'],
        '5030101' => ['Salaries and wages', 'Expense', 'Expenses'],
    ];

    public function test_a_trial_balance_from_the_first_transaction_matches_and_the_reconciliation_is_confirmed(): void
    {
        [$company, $batch] = $this->batch();
        $this->books($company);
        $this->uploadTrialBalance($batch, 'From 01 Jan 2021 To 31 Dec 2026', [
            ['QIB', '1020102', 0, 6000, 0, 6000],
            ['Accounts Receivable', '1020201', 0, 4000, 0, 4000],
            ['Total for Assets', '', null, 10000, 0, null],
            ['Sales - Social Media Management', '4010101', 0, 0, 12000, -12000],
            ['Salaries and wages', '5030101', 0, 2000, 0, 2000],
        ]);
        $this->stageAging($batch, 'ar_aging', 'ar_aging.v1', [['customer_name' => 'Waypoint', 'current' => '0', 'total' => '4000']]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'reconciliation', $company->id);
        $this->assertSame(0, $validation['rejected'], implode(' ', $batch->issues()->pluck('message')->all()));
        $summary = app(ZohoReconciliationService::class)->import($batch, $company->id);

        $this->assertSame(['accounts' => 4, 'customers' => 1, 'vendors' => 0, 'as_of' => '2026-12-31'], $summary);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'reconciliation')->value('status'));
        $this->assertSame(0, DB::table('journal_entries')->where('created_by', $company->id)->where('reference_type', 'like', 'zoho_recon%')->count(), 'Reconciliation changes nothing.');
    }

    public function test_an_as_of_trial_balance_closes_earlier_years_into_retained_earnings_and_differences_are_reported(): void
    {
        [$company, $batch] = $this->batch();
        $this->books($company);
        // On 31 Jul 2026 Zoho shows 2026 income only; the 2025 profit (10,000 − 2,000) sits in Retained Earnings.
        $this->uploadTrialBalance($batch, 'As of 31 Jul 2026', [
            ['QIB', '1020102', 6000, 0],
            ['Accounts Receivable', '1020201', 4100, 0],
            ['Retained Earnings', '2020101', 0, 8000],
            ['Sales - Social Media Management', '4010101', 0, 2000],
        ], false);
        $this->stageAging($batch, 'ar_aging', 'ar_aging.v1', [['customer_name' => 'Waypoint', 'current' => '0', 'total' => '4500']]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'reconciliation', $company->id);

        $messages = $batch->issues()->where('stage_key', 'reconciliation')->pluck('message')->all();
        $this->assertSame(2, $validation['rejected']);
        $this->assertEqualsCanonicalizing([
            '1100 Accounts Receivable: ERP 4,000.00, Zoho 4,100.00 on 2026-07-31 (difference -100.00; debit balances positive).',
            'Customer Waypoint: ERP has 4,000.00 open, Zoho 4,500.00 (difference -500.00).',
        ], $messages, 'Retained Earnings and the 2026 sales match once earlier years are closed.');
        $this->expectExceptionMessage('Resolve every difference before confirming the reconciliation.');
        app(ZohoReconciliationService::class)->import($batch, $company->id);
    }

    public function test_erp_balances_missing_from_zoho_and_a_trial_balance_starting_too_late_are_reported(): void
    {
        [$company, $batch] = $this->batch();
        $this->books($company);
        $this->uploadTrialBalance($batch, 'From 01 Jan 2026 To 31 Dec 2026', [['QIB', '1020102', 0, 6000, 0, 6000]]);

        app(ZohoMigrationValidator::class)->validate($batch, 'reconciliation', $company->id);

        $this->assertContains('The trial balance starts on 2026-01-01 but ERP has transactions from 2025-06-01. Export it from the first transaction, or use an "As of" trial balance.', $batch->issues()->pluck('message')->all());
    }

    public function test_a_wrong_file_added_to_the_step_is_set_aside_and_the_other_files_are_kept(): void
    {
        [$company, $batch] = $this->batch();
        $this->books($company);
        $this->uploadTrialBalance($batch, 'From 01 Jan 2021 To 31 Dec 2026', [
            ['QIB', '1020102', 0, 6000, 0, 6000],
            ['Accounts Receivable', '1020201', 0, 4000, 0, 4000],
            ['Sales - Social Media Management', '4010101', 0, 0, 12000, -12000],
            ['Salaries and wages', '5030101', 0, 2000, 0, 2000],
        ]);
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray([['Document type', 'Number', 'Change'], ['Invoice', 'INV-1', 10]]);
        (new Xlsx($sheet->getParent()))->save(Storage::disk('local')->path('zoho-migrations/test/notes.xlsx'));
        $wrong = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => 'reconciliation', 'uploaded_by' => $company->id, 'original_filename' => 'notes.xlsx', 'disk' => 'local', 'path' => 'zoho-migrations/test/notes.xlsx', 'sha256' => hash('sha256', 'notes'), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'uploaded']);

        (new InspectZohoMigrationFile($wrong->id))->handle(app(ZohoMigrationFileInspector::class), app(ZohoMigrationValidator::class), app(ZohoStageCatalog::class));

        $this->assertSame('superseded', $wrong->fresh()->status);
        $this->assertSame('staged', $batch->files()->where('stage_key', 'reconciliation')->where('dataset_type', 'trial_balance')->value('status'));
        $this->assertSame('ready', $batch->stages()->where('stage_key', 'reconciliation')->value('status'));
        $this->assertSame('notes.xlsx was not added: No supported Zoho export profile matches these headers. The other files of this step are kept.', $batch->events()->where('event_type', 'file_rejected')->value('message'));
    }

    private function batch(): array
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('zoho-migrations/test');
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        User::factory()->create(['type' => 'client', 'name' => 'Waypoint', 'created_by' => $company->id]);
        $categories = DB::table('account_categories')->pluck('id', 'name');
        $typeFor = fn (?string $category) => DB::table('account_types')->when($category, fn ($query) => $query->where('category_id', $categories[$category] ?? 0))->value('id');
        foreach (self::ACCOUNTS + ['1100' => ['Accounts Receivable', null, 'Assets']] as $code => [$name, , $category]) {
            ChartOfAccount::create(['account_code' => (string) $code, 'account_name' => $name, 'account_type_id' => $typeFor($category), 'normal_balance' => in_array($category, ['Revenue', 'Equity'], true) ? 'credit' : 'debit', 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'Reconciliation test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'full_history', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'reconciliation', 'position' => 23, 'status' => 'uploaded']);
        $file = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'chart_of_accounts', 'uploaded_by' => $company->id, 'original_filename' => 'chart.xlsx', 'disk' => 'local', 'path' => 'x', 'sha256' => hash('sha256', 'chart'.$company->id), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'staged', 'dataset_type' => 'chart_of_accounts', 'profile_key' => 'chart_of_accounts.v1', 'detection_confidence' => 100, 'headers' => ['Account Code'], 'row_count' => count(self::ACCOUNTS)]);
        foreach (array_keys(self::ACCOUNTS) as $index => $code) {
            ZohoMigrationStagedRow::create(['batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $company->id, 'sheet_name' => 'S', 'source_row_number' => $index + 2, 'original_values' => ['Account Code' => (string) $code, 'Account Name' => self::ACCOUNTS[$code][0], 'Account Type' => self::ACCOUNTS[$code][1]], 'status' => 'imported']);
        }

        return [$company, $batch];
    }

    /** 2025: sales 10,000 (6,000 banked, 4,000 owed), salaries 2,000 paid. 2026: sales 2,000 banked. */
    private function books(User $company): void
    {
        $account = fn (string $code) => ChartOfAccount::where('created_by', $company->id)->where('account_code', $code)->sole();
        $waypoint = User::where('created_by', $company->id)->where('name', 'Waypoint')->value('id');
        $post = fn (string $number, string $date, array $lines) => app(ZohoJournalWriter::class)->post(['journal_number' => "T-{$company->id}-{$number}", 'journal_date' => $date, 'reference_type' => 'test', 'reference_id' => null, 'description' => $number, 'currency_code' => 'QAR', 'exchange_rate' => 1, 'foreign_amount' => 0], $lines, $company->id, $company->id);
        $post('1', '2025-06-01', [['account' => $account('1020102'), 'debit' => 6000, 'credit' => 0, 'description' => 'x'], ['account' => $account('1100'), 'debit' => 4000, 'credit' => 0, 'description' => 'x', 'contact_id' => $waypoint], ['account' => $account('4010101'), 'debit' => 0, 'credit' => 10000, 'description' => 'x']]);
        $post('2', '2025-07-01', [['account' => $account('5030101'), 'debit' => 2000, 'credit' => 0, 'description' => 'x'], ['account' => $account('1020102'), 'debit' => 0, 'credit' => 2000, 'description' => 'x']]);
        $post('3', '2026-03-01', [['account' => $account('1020102'), 'debit' => 2000, 'credit' => 0, 'description' => 'x'], ['account' => $account('4010101'), 'debit' => 0, 'credit' => 2000, 'description' => 'x']]);
        SalesInvoice::create(['invoice_number' => 'INV-1', 'invoice_date' => '2025-06-01', 'due_date' => '2025-06-01', 'customer_id' => $waypoint, 'subtotal' => 4000, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 4000, 'paid_amount' => 0, 'balance_amount' => 4000, 'status' => 'posted', 'type' => 'service', 'creator_id' => $company->id, 'created_by' => $company->id]);
    }

    /** Uploads a Zoho-style trial balance (title row, headers, accounts) through the real file reader. */
    private function uploadTrialBalance(ZohoMigrationBatch $batch, string $period, array $rows, bool $fromTo = true): void
    {
        $headers = $fromTo ? ['Account ', 'Account Code ', 'Opening Balance ', 'Net Debit ', 'Net Credit ', 'Closing Balance '] : ['Account ', 'Account Code ', 'Net Debit ', 'Net Credit '];
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->fromArray(array_merge([["Brill Creations\n            Trial Balance\n            Basis: Accrual\n                        {$period}"], $headers, [null], ['Assets', '']], $rows, [['Total for Trial Balance', '']]), null, 'A1', true);
        $path = 'zoho-migrations/test/tb-'.md5($period).'.xlsx';
        (new Xlsx($sheet->getParent()))->save(Storage::disk('local')->path($path));
        $file = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => 'reconciliation', 'uploaded_by' => $batch->created_by, 'original_filename' => 'Trial Balance.xlsx', 'disk' => 'local', 'path' => $path, 'sha256' => hash('sha256', $path), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'uploaded']);
        app(ZohoMigrationFileInspector::class)->inspectAndStage($file);
        $this->assertSame('trial_balance.v1', $file->fresh()->profile_key);
    }

    private function stageAging(ZohoMigrationBatch $batch, string $dataset, string $profile, array $rows): void
    {
        $file = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => 'reconciliation', 'uploaded_by' => $batch->created_by, 'original_filename' => $dataset.'.xlsx', 'disk' => 'local', 'path' => 'x', 'sha256' => hash('sha256', $dataset.$batch->id), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => 'staged', 'dataset_type' => $dataset, 'profile_key' => $profile, 'detection_confidence' => 100, 'headers' => array_keys($rows[0]), 'row_count' => count($rows)]);
        foreach ($rows as $index => $row) {
            ZohoMigrationStagedRow::create(['batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $batch->tenant_id, 'sheet_name' => 'S', 'source_row_number' => $index + 3, 'original_values' => $row, 'status' => 'staged']);
        }
    }
}

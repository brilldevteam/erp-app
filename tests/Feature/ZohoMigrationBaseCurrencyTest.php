<?php

namespace Tests\Feature;

use App\Http\Controllers\ZohoMigrationController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ZohoMigrationBaseCurrencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_new_company_takes_the_migration_base_currency(): void
    {
        $company = $this->companyIn('USD');

        $this->createMigration($company, 'QAR');

        $this->assertSame(['QAR', 'ر.ق', 'Qatari Rial'], [
            company_setting('defaultCurrency', $company->id), company_setting('currencySymbol', $company->id), company_setting('currencyName', $company->id),
        ]);
    }

    public function test_a_company_with_entries_in_another_currency_keeps_it_and_the_migration_is_refused(): void
    {
        $company = $this->companyIn('USD');
        DB::table('journal_entries')->insert([
            'journal_number' => 'JE-1', 'journal_date' => '2026-01-01', 'entry_type' => 'manual', 'reference_type' => 'manual',
            'description' => 'Opening', 'total_debit' => 10, 'total_credit' => 10, 'created_by' => $company->id,
        ]);

        try {
            $this->createMigration($company, 'QAR');
            $this->fail('The migration should be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('base_currency', $exception->errors());
        }
        $this->assertSame('USD', company_setting('defaultCurrency', $company->id));
        $this->assertSame(0, DB::table('zoho_migration_batches')->where('tenant_id', $company->id)->count());
    }

    private function companyIn(string $currency): User
    {
        Gate::before(fn () => true);
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        setSetting('defaultCurrency', $currency, $company->id);
        setSetting('currencySymbol', '$', $company->id);
        Auth::login($company);

        return $company;
    }

    private function createMigration(User $company, string $baseCurrency): void
    {
        $request = Request::create('/zoho-migrations', 'POST', [
            'name' => 'Zoho import', 'workflow_mode' => 'full', 'accounting_mode' => 'rebuild',
            'base_currency' => $baseCurrency, 'source_date_format' => 'dd/MM/yyyy', 'inventory_cutover_at' => '2026-10-31',
            'duplicate_strategy' => 'block',
        ]);
        $request->setUserResolver(fn () => $company);

        app(ZohoMigrationController::class)->store($request);
    }
}

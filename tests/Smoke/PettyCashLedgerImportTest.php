<?php

namespace Tests\Smoke;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashRequest;

/** Brill's petty cash journals shown in the petty cash section without changing the accounts. */
class PettyCashLedgerImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_saves_nothing_and_apply_matches_the_journals(): void
    {
        $account = ChartOfAccount::where('created_by', 664)->where('account_code', '1005')->firstOrFail();
        $journalLines = DB::table('journal_entry_items')->where('account_id', $account->id)->count();
        $ledgerBalance = round((float) DB::table('journal_entry_items')->where('account_id', $account->id)->sum(DB::raw('debit_amount - credit_amount')), 2);

        $this->artisan('petty-cash:import-ledger', ['--company' => 664, '--prefix' => 'TST'])->assertSuccessful();
        $this->assertSame(0, PettyCash::where('created_by', 664)->where('pettycash_number', 'like', 'TST-%')->count());

        $this->artisan('petty-cash:import-ledger', ['--company' => 664, '--prefix' => 'TST', '--apply' => true])->assertSuccessful();

        $funds = PettyCash::where('created_by', 664)->where('pettycash_number', 'like', 'TST-%');
        $this->assertGreaterThan(0, (clone $funds)->count());
        $this->assertEqualsWithDelta($ledgerBalance, (float) (clone $funds)->orderByDesc('date')->orderByDesc('id')->value('closing_balance'), 0.001);
        $this->assertGreaterThan(0, PettyCashRequest::where('created_by', 664)->where('request_number', 'like', 'TST-R-%')->count());

        // The accounts are untouched: same journal lines, and no petty cash expense journals.
        $this->assertSame($journalLines, DB::table('journal_entry_items')->where('account_id', $account->id)->count());
        $this->assertSame(0, DB::table('journal_entries')->where('reference_type', 'petty_cash_expense')->where('created_by', 664)->count());

        // A second run is refused instead of importing twice.
        $this->artisan('petty-cash:import-ledger', ['--company' => 664, '--prefix' => 'TST', '--apply' => true])->assertFailed();
    }
}

<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Workdo\PettyCashManagement\Models\PettyCashExpense;
use Workdo\PettyCashManagement\Models\PettyCashReimbursement;
use Workdo\PettyCashManagement\Models\PettyCashRequest;
use Workdo\PettyCashManagement\Services\PettyCashLedger;

/** Petty cash spending approved in the petty cash section reaches the accounts. */
class PettyCashPostingTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;
    private ChartOfAccount $pettyCashAccount;
    private ChartOfAccount $expenseAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = User::findOrFail(950);
        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);

        $this->pettyCashAccount = ChartOfAccount::where('created_by', 950)->where('account_code', '1005')->firstOrFail();
        $this->expenseAccount = ChartOfAccount::billAccountOptions(950)->first(fn ($account) => str_starts_with($account->account_code, '5'));

        // Start from this test's own funds only.
        PettyCash::where('created_by', 950)->delete();
    }

    private function fund(array $values = []): PettyCash
    {
        return PettyCash::create(array_merge([
            'pettycash_number' => 'PC-TEST-' . uniqid(), 'date' => '2026-11-10', 'opening_balance' => 0, 'added_amount' => 500,
            'total_balance' => 500, 'total_expense' => 0, 'closing_balance' => 500, 'status' => 1,
            'creator_id' => 950, 'created_by' => 950,
        ], $values));
    }

    private function category(?int $accountId): PettyCashCategory
    {
        return PettyCashCategory::create(['name' => 'Uber ' . uniqid(), 'account_id' => $accountId, 'creator_id' => 950, 'created_by' => 950]);
    }

    private function request(PettyCashCategory $category, float $amount = 40): PettyCashRequest
    {
        return PettyCashRequest::create([
            'user_id' => 950, 'categorie_id' => $category->id, 'requested_amount' => $amount, 'status' => 0,
            'remarks' => 'Uber to shoot', 'creator_id' => 950, 'created_by' => 950,
        ]);
    }

    private function approve(PettyCashRequest $request, float $amount = 40)
    {
        return $this->from('/petty-cash-management/petty-cash-requests')->put(
            route('petty-cash-management.petty-cash-requests.update-status', $request->id),
            ['status' => 1, 'approved_amount' => $amount, 'approved_by' => 950, 'approved_at' => '2026-11-12']
        );
    }

    private function journalFor(string $type, int $id): ?JournalEntry
    {
        return JournalEntry::with('items')->where('reference_type', $type)->where('reference_id', $id)->first();
    }

    public function test_approved_request_posts_expense_and_reduces_petty_cash(): void
    {
        $fund = $this->fund();
        $request = $this->request($this->category($this->expenseAccount->id));

        $this->approve($request)->assertSessionHas('success');

        $expense = PettyCashExpense::where('request_id', $request->id)->firstOrFail();
        $journal = $this->journalFor('petty_cash_expense', $expense->id);
        $this->assertNotNull($journal);
        $this->assertSame('2026-11-12', $journal->journal_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(40, (float) $journal->items->firstWhere('account_id', $this->expenseAccount->id)->debit_amount, 0.001);
        $this->assertEqualsWithDelta(40, (float) $journal->items->firstWhere('account_id', $this->pettyCashAccount->id)->credit_amount, 0.001);
        $this->assertEqualsWithDelta(460, (float) $fund->fresh()->closing_balance, 0.001);
    }

    public function test_category_without_an_account_cannot_be_approved(): void
    {
        $fund = $this->fund();
        $request = $this->request($this->category(null));

        $this->approve($request)->assertSessionHas('error');

        $this->assertSame('0', (string) $request->fresh()->status);
        $this->assertSame(0, PettyCashExpense::where('request_id', $request->id)->count());
        $this->assertEqualsWithDelta(500, (float) $fund->fresh()->closing_balance, 0.001);
    }

    public function test_deleting_an_approved_request_removes_its_journal_and_returns_the_money(): void
    {
        $fund = $this->fund();
        $request = $this->request($this->category($this->expenseAccount->id));
        $this->approve($request);
        $expense = PettyCashExpense::where('request_id', $request->id)->firstOrFail();

        $this->delete(route('petty-cash-management.petty-cash-requests.destroy', $request->id))->assertSessionHas('success');

        $this->assertNull($this->journalFor('petty_cash_expense', $expense->id));
        $this->assertNull(PettyCashExpense::find($expense->id));
        $this->assertEqualsWithDelta(500, (float) $fund->fresh()->closing_balance, 0.001);
    }

    public function test_approved_reimbursement_posts_to_the_accounts(): void
    {
        $this->fund();
        $category = $this->category($this->expenseAccount->id);
        $reimbursement = PettyCashReimbursement::create([
            'user_id' => 950, 'category_id' => $category->id, 'amount' => 25, 'status' => 0,
            'description' => 'Office water', 'creator_id' => 950, 'created_by' => 950,
        ]);

        $this->from('/petty-cash-management/reimbursements')->put(
            route('petty-cash-management.reimbursements.update-status', $reimbursement->id),
            ['status' => 1, 'approved_amount' => 25, 'approved_by' => 950, 'approved_date' => '2026-11-13']
        )->assertSessionHas('success');

        $expense = PettyCashExpense::where('reimbursement_id', $reimbursement->id)->firstOrFail();
        $journal = $this->journalFor('petty_cash_expense', $expense->id);
        $this->assertEqualsWithDelta(25, (float) $journal->items->firstWhere('account_id', $this->expenseAccount->id)->debit_amount, 0.001);
        $this->assertEqualsWithDelta(25, (float) $journal->items->firstWhere('account_id', $this->pettyCashAccount->id)->credit_amount, 0.001);
    }

    public function test_top_up_moves_money_from_the_bank_into_petty_cash_on_its_date(): void
    {
        $bank = DB::table('bank_accounts')->where('created_by', 950)->whereNotNull('gl_account_id')->first();
        $fund = $this->fund(['status' => 0, 'bank_account_id' => $bank->id, 'date' => '2026-11-05']);

        $this->from('/petty-cash-management/petty-cashes')
            ->post(route('petty-cash-management.petty-cashes.approve', $fund->id))
            ->assertSessionHas('success');

        $journal = $this->journalFor('petty_cash', $fund->id);
        $this->assertSame('2026-11-05', $journal->journal_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(500, (float) $journal->items->firstWhere('account_id', $this->pettyCashAccount->id)->debit_amount, 0.001);
        $this->assertEqualsWithDelta(500, (float) $journal->items->firstWhere('account_id', $bank->gl_account_id)->credit_amount, 0.001);
    }

    public function test_spending_comes_from_the_newest_fund_by_date(): void
    {
        $newest = $this->fund(['date' => '2026-07-27']);
        $this->fund(['date' => '2025-12-31', 'closing_balance' => 0]);

        $this->assertSame($newest->id, PettyCashLedger::currentFund(950)->id);
    }

    public function test_category_screen_saves_the_expense_account(): void
    {
        $this->post(route('petty-cash-management.petty-cash-categories.store'), ['name' => 'Taxi'])
            ->assertSessionHasErrors('account_id');

        $this->post(route('petty-cash-management.petty-cash-categories.store'), ['name' => 'Taxi', 'account_id' => $this->expenseAccount->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->expenseAccount->id, PettyCashCategory::where('created_by', 950)->where('name', 'Taxi')->value('account_id'));
    }
}

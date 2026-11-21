<?php

namespace Tests\Smoke;

use App\Models\User;
use App\Services\AuthSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\VendorPaymentAllocation;
use Workdo\Appointment\Models\Appointment;
use Workdo\Appointment\Models\Schedule;
use Workdo\BudgetPlanner\Models\Budget;
use Workdo\BudgetPlanner\Models\BudgetAllocation;
use Workdo\BudgetPlanner\Models\BudgetPeriod;
use Workdo\Retainer\Models\RetainerPaymentAllocation;
use Workdo\Retainer\Models\SalesRetainer;
use Workdo\Retainer\Models\SalesRetainerPayment;

/** Fixes for the open findings of the demo check: double clearing, budget activation, double booking. */
class OpenFindingsFixesTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = User::findOrFail(950);
        $this->actingAs($this->company)->withSession([
            AuthSessionService::SESSION_KEY => max(1, (int) ($this->company->security_version ?? 1)),
        ]);
    }

    private function needs(string $permission): void
    {
        if (!$this->company->can($permission)) {
            $this->markTestSkipped("Test company lacks {$permission}.");
        }
    }

    private function bank(): BankAccount
    {
        return BankAccount::where('created_by', 950)->whereNotNull('gl_account_id')->firstOrFail();
    }

    private function journals(): int
    {
        return JournalEntry::where('created_by', 950)->count();
    }

    public function test_vendor_payment_cannot_be_cleared_twice(): void
    {
        $this->needs('cleared-vendor-payments');
        $bill = DB::table('purchase_invoices')->where('created_by', 950)->where('status', 'posted')
            ->where('balance_amount', '>', 20)->where('currency_code', 'QAR')->first();
        if (!$bill) {
            $this->markTestSkipped('No open bill.');
        }

        $payment = VendorPayment::create([
            'payment_number' => 'VP-TEST-' . uniqid(), 'payment_date' => '2026-11-20', 'vendor_id' => $bill->vendor_id,
            'bank_account_id' => $this->bank()->id, 'payment_amount' => 10, 'currency_code' => 'QAR', 'base_currency_code' => 'QAR',
            'exchange_rate' => 1, 'base_amount' => 10, 'status' => 'pending', 'creator_id' => 950, 'created_by' => 950,
        ]);
        VendorPaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $bill->id, 'allocated_amount' => 10]);

        $journals = $this->journals();
        $this->from('/account/vendor-payments')->post(route('account.vendor-payments.update-status', $payment), ['status' => 'cleared'])
            ->assertSessionHas('success');
        $afterFirst = $this->journals();
        $this->assertGreaterThan($journals, $afterFirst);
        $paid = (float) DB::table('purchase_invoices')->where('id', $bill->id)->value('paid_amount');

        // Second click / second tab: nothing more is posted.
        $this->from('/account/vendor-payments')->post(route('account.vendor-payments.update-status', $payment), ['status' => 'cleared'])
            ->assertSessionHas('error');
        $this->assertSame($afterFirst, $this->journals());
        $this->assertEqualsWithDelta($paid, (float) DB::table('purchase_invoices')->where('id', $bill->id)->value('paid_amount'), 0.001);
        $this->assertEqualsWithDelta((float) $bill->paid_amount + 10, $paid, 0.001);

        // A cleared payment cannot be cancelled afterwards either.
        $this->post(route('account.vendor-payments.update-status', $payment), ['status' => 'cancelled'])->assertSessionHas('error');
        $this->assertSame('cleared', $payment->fresh()->status);
    }

    public function test_customer_payment_cannot_be_cleared_twice_and_cancel_posts_nothing(): void
    {
        $this->needs('cleared-customer-payments');
        $invoice = DB::table('sales_invoices')->where('created_by', 950)->where('status', 'posted')
            ->where('balance_amount', '>', 20)->where('currency_code', 'QAR')->first();
        if (!$invoice) {
            $this->markTestSkipped('No open invoice.');
        }

        $make = function () use ($invoice) {
            $payment = CustomerPayment::create([
                'payment_number' => 'CP-TEST-' . uniqid(), 'payment_date' => '2026-11-20', 'customer_id' => $invoice->customer_id,
                'bank_account_id' => $this->bank()->id, 'payment_amount' => 10, 'currency_code' => 'QAR', 'base_currency_code' => 'QAR',
                'exchange_rate' => 1, 'base_amount' => 10, 'status' => 'pending', 'creator_id' => 950, 'created_by' => 950,
            ]);
            CustomerPaymentAllocation::create(['payment_id' => $payment->id, 'invoice_id' => $invoice->id, 'allocated_amount' => 10,
                'creator_id' => 950, 'created_by' => 950]);

            return $payment;
        };

        // Cancelling a pending payment posts nothing and leaves the invoice alone.
        $cancelled = $make();
        $journals = $this->journals();
        $this->patch(route('account.customer-payments.update-status', $cancelled), ['status' => 'cancelled'])->assertSessionHas('success');
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame($journals, $this->journals());
        $this->assertEqualsWithDelta((float) $invoice->paid_amount, (float) DB::table('sales_invoices')->where('id', $invoice->id)->value('paid_amount'), 0.001);

        $payment = $make();
        $this->patch(route('account.customer-payments.update-status', $payment), ['status' => 'cleared'])->assertSessionHas('success');
        $afterFirst = $this->journals();
        $this->assertGreaterThan($journals, $afterFirst);
        $this->patch(route('account.customer-payments.update-status', $payment), ['status' => 'cleared'])->assertSessionHas('error');
        $this->assertSame($afterFirst, $this->journals());
        $this->assertEqualsWithDelta((float) $invoice->paid_amount + 10, (float) DB::table('sales_invoices')->where('id', $invoice->id)->value('paid_amount'), 0.001);

        // Only the two real statuses are accepted.
        $other = $make();
        $this->patch(route('account.customer-payments.update-status', $other), ['status' => 'paid'])->assertSessionHas('error');
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_retainer_payment_cannot_be_cleared_twice(): void
    {
        $this->needs('cleared-retainer-payments');
        $customerId = DB::table('users')->where('created_by', 950)->where('type', 'client')->value('id');
        $retainer = SalesRetainer::create([
            'retainer_number' => 'RET-TEST-' . uniqid(), 'retainer_date' => '2026-11-01', 'due_date' => '2026-11-30',
            'customer_id' => $customerId, 'subtotal' => 100, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 100,
            'paid_amount' => 0, 'balance_amount' => 100, 'status' => 'accepted', 'creator_id' => 950, 'created_by' => 950,
        ]);
        $payment = SalesRetainerPayment::create([
            'payment_number' => 'RP-TEST-' . uniqid(), 'payment_date' => '2026-11-20', 'customer_id' => $customerId,
            'bank_account_id' => $this->bank()->id, 'payment_amount' => 40,
            'status' => 'pending', 'creator_id' => 950, 'created_by' => 950,
        ]);
        RetainerPaymentAllocation::create(['payment_id' => $payment->id, 'retainer_id' => $retainer->id, 'allocated_amount' => 40,
            'creator_id' => 950, 'created_by' => 950]);

        $this->patch(route('retainer-payments.update-status', $payment), ['status' => 'cleared'])->assertSessionHas('success');
        $afterFirst = $this->journals();
        $this->assertEqualsWithDelta(40, (float) $retainer->fresh()->paid_amount, 0.001);

        $this->patch(route('retainer-payments.update-status', $payment), ['status' => 'cleared'])->assertSessionHas('error');
        $this->assertSame($afterFirst, $this->journals());
        $this->assertEqualsWithDelta(40, (float) $retainer->fresh()->paid_amount, 0.001);
        $this->assertEqualsWithDelta(60, (float) $retainer->fresh()->balance_amount, 0.001);
    }

    public function test_activating_a_budget_counts_spending_already_posted_in_its_period(): void
    {
        $this->needs('active-budgets');
        // An expense-type account with real net spending in one month.
        $line = DB::table('journal_entry_items as i')->join('journal_entries as j', 'j.id', '=', 'i.journal_entry_id')
            ->join('chart_of_accounts as a', 'a.id', '=', 'i.account_id')
            ->where('j.created_by', 950)->where('j.status', 'posted')->where('a.normal_balance', 'debit')
            ->groupBy('i.account_id', DB::raw("DATE_FORMAT(j.journal_date, '%Y-%m')"))
            ->havingRaw('SUM(i.debit_amount) - SUM(i.credit_amount) > 0')
            ->selectRaw('i.account_id, MIN(j.journal_date) as journal_date')->first();
        if (!$line) {
            $this->markTestSkipped('No posted debit line.');
        }
        $start = date('Y-m-01', strtotime($line->journal_date));
        $end = date('Y-m-t', strtotime($line->journal_date));
        $expected = (float) DB::table('journal_entry_items as i')->join('journal_entries as j', 'j.id', '=', 'i.journal_entry_id')
            ->where('i.account_id', $line->account_id)->where('j.status', 'posted')->whereBetween('j.journal_date', [$start, $end])
            ->selectRaw('COALESCE(SUM(i.debit_amount),0) - COALESCE(SUM(i.credit_amount),0) as net')->value('net');

        $period = BudgetPeriod::create(['period_name' => 'Test ' . uniqid(), 'financial_year' => date('Y', strtotime($start)),
            'start_date' => $start, 'end_date' => $end, 'status' => 'active', 'creator_id' => 950, 'created_by' => 950]);
        $budget = Budget::create(['budget_name' => 'Activation test', 'period_id' => $period->id, 'budget_type' => 'operational',
            'total_budget_amount' => 1000000, 'status' => 'approved', 'creator_id' => 950, 'created_by' => 950]);
        $allocation = BudgetAllocation::create(['budget_id' => $budget->id, 'account_id' => $line->account_id, 'allocated_amount' => 1000000,
            'spent_amount' => 0, 'remaining_amount' => 1000000, 'creator_id' => 950, 'created_by' => 950]);

        $this->from('/budget-planner/budgets')->post(route('budget-planner.budgets.active', $budget))->assertSessionHas('success');

        $this->assertSame('active', $budget->fresh()->status);
        $this->assertEqualsWithDelta(max(0, $expected), (float) $allocation->fresh()->spent_amount, 0.01);
        $this->assertEqualsWithDelta(1000000 - max(0, $expected), (float) $allocation->fresh()->remaining_amount, 0.01);
        $this->assertGreaterThan(0, (float) $allocation->fresh()->spent_amount);
    }

    public function test_public_booking_refuses_a_slot_that_is_already_taken(): void
    {
        $appointment = Appointment::create(['appointment_name' => 'Tasting ' . uniqid(), 'appointment_type' => 'paid', 'week_day' => '[]',
            'duration' => 60, 'phone_enabled' => 0, 'question_ids' => '[]', 'enabled' => 1, 'creator_id' => 950, 'created_by' => 950]);
        $slug = $this->company->slug;
        $url = route('appointment.public.store', [$slug, $appointment->getEncryptedId()]);
        $date = now()->addDays(3)->toDateString();
        $book = fn (string $name, string $start, string $end) => $this->post($url, [
            'name' => $name, 'email' => strtolower($name) . '@example.test', 'phone' => '', 'date' => $date,
            'start_time' => $start, 'end_time' => $end, 'questions' => [],
        ]);
        $count = fn () => Schedule::where('appointment_id', $appointment->id)->count();

        $book('First', '10:00', '11:00')->assertSessionHasNoErrors();
        $this->assertSame(1, $count());

        // Same slot, and a slot that overlaps it: both refused.
        $book('Second', '10:00', '11:00')->assertSessionHasErrors('start_time');
        $book('Third', '10:30', '11:30')->assertSessionHasErrors('start_time');
        $this->assertSame(1, $count());

        // The next slot is free.
        $book('Fourth', '11:00', '12:00')->assertSessionHasNoErrors();
        $this->assertSame(2, $count());

        // A cancelled booking frees its slot, on the booking check and in the taken-slots list.
        Schedule::where('appointment_id', $appointment->id)->where('name', 'First')->update(['status' => 'cancel']);
        $booked = $this->getJson(route('appointment.public.booked-slots', [$slug, $appointment->getEncryptedId(), $date]))->json('bookedSlots');
        $this->assertSame(['11:00-12:00'], $booked);
        $book('Fifth', '10:00', '11:00')->assertSessionHasNoErrors();
        $this->assertSame(3, $count());

        // An end time before the start time is refused.
        $book('Sixth', '15:00', '14:00')->assertSessionHasErrors('end_time');
    }
}

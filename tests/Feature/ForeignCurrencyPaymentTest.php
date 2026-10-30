<?php

namespace Tests\Feature;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Workdo\Account\Http\Controllers\CustomerPaymentController;
use Workdo\Account\Http\Requests\StoreCustomerPaymentRequest;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;
use Workdo\Account\Services\BankTransactionsService;

class ForeignCurrencyPaymentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_foreign_invoice_paid_at_a_new_rate_is_paid_in_full_and_the_difference_is_exchange_gain_or_loss(): void
    {
        foreach (['4.65' => 273.96, '3.00' => -178.09] as $rate => $difference) {
            [$invoice, $bank] = $this->usdInvoice();

            $this->pay($invoice, $bank, $rate);

            $allocation = CustomerPaymentAllocation::where('invoice_id', $invoice->id)->sole();
            $this->assertSame(['273.97', '1000.00', number_format(273.97 * (float) $rate, 2, '.', ''), number_format($difference, 2, '.', '')], [
                (string) $allocation->allocated_amount, (string) $allocation->carrying_base_amount, (string) $allocation->settlement_base_amount, (string) $allocation->exchange_difference,
            ], "rate {$rate}");
        }
    }

    public function test_a_foreign_payment_received_into_a_base_currency_account_moves_its_register_by_the_base_equivalent(): void
    {
        [$invoice, $usdBank] = $this->usdInvoice();
        $qarBank = $usdBank->replicate()->fill(['account_name' => 'Petty Cash', 'currency_code' => 'QAR']);
        $qarBank->save();

        $this->pay($invoice, $qarBank, '4.65');

        $payment = CustomerPayment::where('bank_account_id', $qarBank->id)->sole();
        $this->assertSame(['273.97', '1273.96'], [(string) $payment->payment_amount, (string) $payment->base_amount]);
        app(BankTransactionsService::class)->createCustomerPayment($payment);
        $this->assertSame('1273.96', (string) BankTransaction::where('bank_account_id', $qarBank->id)->sole()->amount);
        $this->assertSame(1273.96, (float) $qarBank->fresh()->current_balance);
    }

    private function usdInvoice(): array
    {
        Gate::before(fn () => true);
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $customer = User::factory()->create(['type' => 'client', 'created_by' => $company->id]);
        DB::table('settings')->insert(['key' => 'defaultCurrency', 'value' => 'QAR', 'created_by' => $company->id]);
        Auth::login($company);

        $bank = BankAccount::create(['account_name' => 'USD account', 'account_number' => '1', 'bank_name' => 'Bank', 'account_type' => 'bank', 'currency_code' => 'USD', 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        // QAR 1,000 entered at 3.65 is USD 273.97, kept at the rate that converts back to QAR 1,000.00.
        $invoice = SalesInvoice::create(['invoice_number' => 'SI-USD-'.$company->id, 'invoice_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'customer_id' => $customer->id, 'subtotal' => 273.97, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 273.97, 'paid_amount' => 0, 'balance_amount' => 273.97, 'status' => 'posted', 'type' => 'service', 'currency_code' => 'USD', 'base_currency_code' => 'QAR', 'exchange_rate' => '3.65003468', 'base_amount' => 1000, 'creator_id' => $company->id, 'created_by' => $company->id]);

        return [$invoice, $bank];
    }

    private function pay(SalesInvoice $invoice, BankAccount $bank, string $rate): void
    {
        $request = StoreCustomerPaymentRequest::create('/', 'POST', [
            'payment_date' => now()->toDateString(), 'customer_id' => $invoice->customer_id, 'bank_account_id' => $bank->id, 'payment_mode' => 'bank_transfer',
            'payment_amount' => '273.97', 'currency_code' => 'USD', 'exchange_rate' => $rate,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 273.97]],
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => Auth::user());
        $request->validateResolved();

        $response = app(CustomerPaymentController::class)->store($request);
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertNotNull($response);
    }
}

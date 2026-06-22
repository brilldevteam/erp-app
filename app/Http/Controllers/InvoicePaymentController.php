<?php

namespace App\Http\Controllers;

use App\Models\DocumentPaymentTransaction;
use App\Services\Documents\DocumentDataService;
use App\Services\Documents\DocumentShareService;
use App\Services\Documents\InvoicePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class InvoicePaymentController extends Controller
{
    public function __construct(
        private readonly DocumentShareService $shares,
        private readonly DocumentDataService $documents,
        private readonly InvoicePaymentService $payments,
    ) {
    }

    public function start(string $token, string $provider)
    {
        [$link, $invoice] = $this->invoice($token);
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);
        abort_unless(in_array($invoice->status, ['posted', 'sent', 'partial'], true) && (float) $invoice->balance_amount > 0, 422, __('This invoice cannot be paid online.'));
        $this->assertConfigured($provider, $link->created_by);

        $currency = strtoupper(company_setting('defaultCurrency', $link->created_by) ?: 'USD');
        $transaction = DocumentPaymentTransaction::create([
            'invoice_id' => $invoice->id,
            'provider' => $provider,
            'amount' => $invoice->balance_amount,
            'currency' => $currency,
            'created_by' => $link->created_by,
        ]);

        try {
            return $provider === 'stripe'
                ? $this->startStripe($token, $transaction, $invoice)
                : $this->startPayPal($token, $transaction, $invoice);
        } catch (Throwable $exception) {
            $transaction->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
            report($exception);

            return redirect()->route('documents.public.show', $token)->with('error', __('Unable to start payment. Please try again.'));
        }
    }

    public function complete(Request $request, string $token, string $provider)
    {
        [$link] = $this->invoice($token);
        $this->assertConfigured($provider, $link->created_by);

        try {
            if ($provider === 'stripe') {
                $sessionId = $request->string('session_id')->toString();
                $stripe = new StripeClient(company_setting('stripe_secret', $link->created_by));
                $session = $stripe->checkout->sessions->retrieve($sessionId);
                abort_unless($session->payment_status === 'paid', 422, __('Stripe has not confirmed this payment.'));
                $transaction = DocumentPaymentTransaction::whereKey($session->metadata->transaction_id ?? 0)->where('created_by', $link->created_by)->firstOrFail();
                $this->payments->settle($transaction, (string) ($session->payment_intent ?: $session->id), $session->toArray());
            } elseif ($provider === 'paypal') {
                $orderId = $request->string('token')->toString();
                $transaction = DocumentPaymentTransaction::where('provider', 'paypal')->where('provider_reference', $orderId)->where('created_by', $link->created_by)->firstOrFail();
                $capture = $this->paypalRequest($link->created_by)->post("v2/checkout/orders/{$orderId}/capture")->throw()->json();
                abort_unless(($capture['status'] ?? null) === 'COMPLETED', 422, __('PayPal has not confirmed this payment.'));
                $reference = data_get($capture, 'purchase_units.0.payments.captures.0.id', $orderId);
                $this->payments->settle($transaction, (string) $reference, $capture);
            } else {
                abort(404);
            }

            return redirect()->route('documents.public.show', $token)->with('success', __('Payment received successfully.'));
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('documents.public.show', $token)->with('error', __('Payment could not be confirmed. Please contact the company if you were charged.'));
        }
    }

    public function cancel(string $token, string $provider)
    {
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);

        return redirect()->route('documents.public.show', $token)->with('error', __('Payment was cancelled.'));
    }

    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $unverified = json_decode($payload, true);
        $transactionId = data_get($unverified, 'data.object.metadata.transaction_id');
        $transaction = DocumentPaymentTransaction::findOrFail($transactionId);
        $secret = company_setting('stripe_webhook_secret', $transaction->created_by);
        abort_unless($secret, 503, __('Stripe webhook secret is not configured.'));

        $event = \Stripe\Webhook::constructEvent($payload, (string) $request->header('Stripe-Signature'), $secret);
        if ($event->type === 'checkout.session.completed' && $event->data->object->payment_status === 'paid') {
            $session = $event->data->object;
            $this->payments->settle($transaction, (string) ($session->payment_intent ?: $session->id), $session->toArray());
        }

        return response()->json(['received' => true]);
    }

    private function startStripe(string $token, DocumentPaymentTransaction $transaction, $invoice)
    {
        $stripe = new StripeClient(company_setting('stripe_secret', $transaction->created_by));
        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $invoice->customer?->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($transaction->currency),
                    'unit_amount' => (int) round((float) $transaction->amount * 100),
                    'product_data' => ['name' => __('Invoice :number', ['number' => $invoice->invoice_number])],
                ],
            ]],
            'metadata' => ['transaction_id' => (string) $transaction->id, 'invoice_id' => (string) $invoice->id],
            'success_url' => route('documents.public.payment.return', ['token' => $token, 'provider' => 'stripe']).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('documents.public.payment.cancel', ['token' => $token, 'provider' => 'stripe']),
        ]);
        $transaction->update(['provider_reference' => $session->id, 'provider_payload' => $session->toArray()]);

        return redirect()->away($session->url);
    }

    private function startPayPal(string $token, DocumentPaymentTransaction $transaction, $invoice)
    {
        $response = $this->paypalRequest($transaction->created_by)->post('v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $transaction->id,
                'description' => __('Invoice :number', ['number' => $invoice->invoice_number]),
                'amount' => ['currency_code' => $transaction->currency, 'value' => number_format((float) $transaction->amount, 2, '.', '')],
            ]],
            'application_context' => [
                'return_url' => route('documents.public.payment.return', ['token' => $token, 'provider' => 'paypal']),
                'cancel_url' => route('documents.public.payment.cancel', ['token' => $token, 'provider' => 'paypal']),
            ],
        ])->throw()->json();
        $transaction->update(['provider_reference' => $response['id'], 'provider_payload' => $response]);
        $approval = collect($response['links'] ?? [])->firstWhere('rel', 'approve');
        throw_unless($approval, new RuntimeException(__('PayPal did not return an approval link.')));

        return redirect()->away($approval['href']);
    }

    private function paypalRequest(int $tenantId)
    {
        $mode = company_setting('paypal_mode', $tenantId) === 'live' ? 'live' : 'sandbox';
        $base = $mode === 'live' ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
        $client = company_setting('paypal_client_id', $tenantId);
        $secret = company_setting('paypal_secret_key', $tenantId);
        $token = Http::asForm()->withBasicAuth($client, $secret)->post($base.'v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json('access_token');

        return Http::baseUrl($base)->withToken($token)->acceptJson()->asJson();
    }

    private function invoice(string $token): array
    {
        $link = $this->shares->resolve($token);
        abort_unless($link->document_type === 'invoice', 404);
        $invoice = $this->documents->find('invoice', $link->document_id);
        abort_unless((int) $invoice->created_by === (int) $link->created_by, 404);

        return [$link, $invoice];
    }

    private function assertConfigured(string $provider, int $tenantId): void
    {
        abort_unless(in_array($provider, ['stripe', 'paypal'], true), 404);
        abort_unless(Module_is_active(ucfirst($provider), $tenantId), 404);
        if ($provider === 'stripe') {
            abort_unless(company_setting('stripe_enabled', $tenantId) === 'on' && company_setting('stripe_secret', $tenantId), 503);
        } else {
            abort_unless(company_setting('paypal_enabled', $tenantId) === 'on' && company_setting('paypal_client_id', $tenantId) && company_setting('paypal_secret_key', $tenantId), 503);
        }
    }
}

<?php

namespace App\Services\Documents;

use App\Models\DocumentPaymentTransaction;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;

class InvoicePaymentService
{
    public function __construct(private readonly DocumentActivityService $activities)
    {
    }

    public function settle(DocumentPaymentTransaction $transaction, string $providerReference, array $payload = []): DocumentPaymentTransaction
    {
        return DB::transaction(function () use ($transaction, $providerReference, $payload) {
            $transaction = DocumentPaymentTransaction::lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status === 'completed') {
                return $transaction;
            }

            $duplicate = DocumentPaymentTransaction::where('provider', $transaction->provider)
                ->where('provider_reference', $providerReference)
                ->whereKeyNot($transaction->id)
                ->where('status', 'completed')
                ->exists();
            if ($duplicate) {
                throw new RuntimeException(__('This provider payment was already applied.'));
            }

            $invoice = SalesInvoice::whereKey($transaction->invoice_id)->lockForUpdate()->firstOrFail();
            $amount = min((float) $transaction->amount, (float) $invoice->balance_amount);
            if ($amount <= 0) {
                throw new RuntimeException(__('This invoice has no outstanding balance.'));
            }

            $bankAccount = BankAccount::where('created_by', $invoice->created_by)
                ->where('is_active', true)
                ->orderByDesc('payment_gateway')
                ->first();
            if (!$bankAccount) {
                throw new RuntimeException(__('Configure an active bank account before accepting online payments.'));
            }

            $payment = CustomerPayment::create([
                'payment_date' => now()->toDateString(),
                'customer_id' => $invoice->customer_id,
                'bank_account_id' => $bankAccount->id,
                'reference_number' => $providerReference,
                'payment_amount' => $amount,
                'status' => 'cleared',
                'notes' => __('Online :provider payment for :invoice', ['provider' => ucfirst($transaction->provider), 'invoice' => $invoice->invoice_number]),
                'created_by' => $invoice->created_by,
            ]);

            CustomerPaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $amount,
                'created_by' => $invoice->created_by,
            ]);

            $invoice->paid_amount = min((float) $invoice->total_amount, (float) $invoice->paid_amount + $amount);
            $invoice->balance_amount = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount);
            $invoice->status = $invoice->balance_amount <= 0 ? 'paid' : 'partial';
            $invoice->save();

            $transaction->update([
                'provider_reference' => $providerReference,
                'status' => 'completed',
                'customer_payment_id' => $payment->id,
                'provider_payload' => $payload,
                'completed_at' => now(),
                'failure_reason' => null,
            ]);

            $this->activities->record('invoice', $invoice, 'paid', [
                'provider' => $transaction->provider,
                'reference' => $providerReference,
                'amount' => $amount,
            ], 'customer');

            return $transaction->fresh();
        });
    }
}

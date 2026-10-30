<?php

namespace App\Services\ZohoMigration;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\CustomerPaymentAllocation;

/**
 * Zoho customer payments as ERP customer payments: debit the deposit account, credit receivables 1100.
 */
class ZohoCustomerPaymentService extends ZohoPaymentService
{
    protected function config(): array
    {
        return [
            'stage_key' => 'customer_payments',
            'id' => ['customerpayment id', 'payment id'],
            'id_label' => 'CustomerPayment ID',
            'partner' => 'customer name',
            'account' => 'deposit to',
            'account_code' => 'deposit to account code',
            'account_label' => 'Deposit To account',
            'document' => 'invoice number',
            'applied' => 'amount applied to invoice',
            'document_label' => 'invoice',
            'document_step' => 'Sales invoices',
            'label' => 'customer payment',
            'label_plural' => 'customer payments',
            'partner_label' => 'Customer',
            'partner_step' => 'Customers',
            'allows_unused' => false,
            'advance_account' => null,
            'control_code' => ZohoSalesInvoiceService::RECEIVABLE_ACCOUNT_CODE,
            'control_name' => 'Accounts Receivable',
            'bank_direction' => 'credit',
            'reference_type' => 'customer_payment',
            'journal_label' => 'Customer Payment',
            'route' => 'account.customer-payments.index',
        ];
    }

    protected function partnerUser(string $name, int $tenantId): ?User
    {
        return $this->customerUser($name, $tenantId);
    }

    protected function document(string $number, int $partnerId, int $tenantId): ?Model
    {
        return SalesInvoice::where('created_by', $tenantId)->where('customer_id', $partnerId)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($number)])->first();
    }

    protected function existing(string $number, int $partnerId, int $tenantId): ?Model
    {
        return CustomerPayment::where('created_by', $tenantId)->where('customer_id', $partnerId)->where('payment_number', $number)->first();
    }

    protected function createPayment(array $header, array $allocations, int $partnerId, BankAccount $bank, int $actorId, int $tenantId): Model
    {
        $rate = (float) $header['exchange_rate'] ?: 1.0;
        $payment = CustomerPayment::create([
            'payment_number' => $header['number'],
            'payment_date' => $header['date'],
            'payment_mode' => $header['mode'],
            'customer_id' => $partnerId,
            'bank_account_id' => $bank->id,
            'reference_number' => $header['reference'],
            'payment_amount' => $header['amount'],
            'currency_code' => $header['currency_code'],
            'exchange_rate' => $rate,
            'base_amount' => round($header['amount'] * $rate, 2),
            'status' => 'cleared',
            'needs_bank_verification' => true,
            'notes' => $header['description'] !== '' ? $header['description'] : null,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        foreach ($allocations as $allocation) {
            $invoice = $this->document($allocation['document_number'], $partnerId, $tenantId);
            CustomerPaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $allocation['applied'],
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);
            $this->applyToDocument($invoice, $allocation['applied']);
        }

        return $payment;
    }

    protected function journalLines(array $header, float $appliedBase, float $unusedBase, ChartOfAccount $bankLedger, int $partnerId, int $tenantId): array
    {
        return [
            ['account' => $bankLedger, 'debit' => $appliedBase, 'credit' => 0.0, 'description' => 'Payment received - '.$header['partner']],
            ['account' => $this->controlAccount($tenantId), 'debit' => 0.0, 'credit' => $appliedBase, 'description' => 'Payment '.$header['number'].' - '.$header['partner'], 'contact_id' => $partnerId],
        ];
    }
}

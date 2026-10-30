<?php

namespace App\Services\ZohoMigration;

use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\VendorPaymentAllocation;

/**
 * Zoho vendor payments as ERP vendor payments: debit payables 2000 (and any unapplied amount to Zoho's
 * "Advances paid to vendors"), credit the paid-through account.
 */
class ZohoVendorPaymentService extends ZohoPaymentService
{
    public const ADVANCE_ACCOUNT_NAME = 'Advances paid to vendors';

    protected function config(): array
    {
        return [
            'stage_key' => 'vendor_payments',
            'id' => ['vendorpayment id', 'payment id'],
            'id_label' => 'VendorPayment ID',
            'partner' => 'vendor name',
            'account' => 'paid through',
            'account_code' => 'paid through account code',
            'account_label' => 'Paid Through account',
            'document' => 'bill number',
            'applied' => 'bill amount',
            'document_label' => 'bill',
            'document_step' => 'Purchase invoices',
            'label' => 'vendor payment',
            'label_plural' => 'vendor payments',
            'partner_label' => 'Vendor',
            'partner_step' => 'Vendors',
            'allows_unused' => true,
            'advance_account' => self::ADVANCE_ACCOUNT_NAME,
            'control_code' => ZohoBillService::PAYABLE_ACCOUNT_CODE,
            'control_name' => 'Accounts Payable',
            'bank_direction' => 'debit',
            'reference_type' => 'vendor_payment',
            'journal_label' => 'Vendor Payment',
            'route' => 'account.vendor-payments.index',
        ];
    }

    protected function partnerUser(string $name, int $tenantId): ?User
    {
        return $this->vendorUser($name, $tenantId);
    }

    protected function document(string $number, int $partnerId, int $tenantId): ?Model
    {
        return PurchaseInvoice::where('created_by', $tenantId)->where('vendor_id', $partnerId)->whereRaw('LOWER(invoice_number) = ?', [mb_strtolower($number)])->first();
    }

    protected function existing(string $number, int $partnerId, int $tenantId): ?Model
    {
        return VendorPayment::where('created_by', $tenantId)->where('vendor_id', $partnerId)->where('payment_number', $number)->first();
    }

    protected function createPayment(array $header, array $allocations, int $partnerId, BankAccount $bank, int $actorId, int $tenantId): Model
    {
        $rate = (float) $header['exchange_rate'] ?: 1.0;
        $notes = array_filter([
            $header['description'] !== '' ? $header['description'] : null,
            $header['unused'] > 0 ? 'Unapplied in Zoho: '.number_format($header['unused'], 2).' held as an advance to the vendor.' : null,
        ]);
        $payment = VendorPayment::create([
            'payment_number' => $header['number'],
            'payment_date' => $header['date'],
            'payment_mode' => $header['mode'],
            'vendor_id' => $partnerId,
            'bank_account_id' => $bank->id,
            'reference_number' => $header['reference'],
            'payment_amount' => $header['amount'],
            'currency_code' => $header['currency_code'],
            'base_currency_code' => strtoupper((string) (company_setting('defaultCurrency', $tenantId) ?: 'USD')),
            'exchange_rate' => $rate,
            'base_amount' => round($header['amount'] * $rate, 2),
            'status' => 'cleared',
            'needs_bank_verification' => true,
            'notes' => $notes ? implode(' ', $notes) : null,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        foreach ($allocations as $allocation) {
            $bill = $this->document($allocation['document_number'], $partnerId, $tenantId);
            $carryingValue = round($allocation['applied'] * (float) ($bill->exchange_rate ?: 1), 2);
            VendorPaymentAllocation::create([
                'payment_id' => $payment->id,
                'invoice_id' => $bill->id,
                'allocated_amount' => $allocation['applied'],
                'carrying_base_amount' => $carryingValue,
                'settlement_base_amount' => $carryingValue,
                'exchange_difference' => 0,
            ]);
            $this->applyToDocument($bill, $allocation['applied']);
        }

        return $payment;
    }

    protected function journalLines(array $header, float $appliedBase, float $unusedBase, ChartOfAccount $bankLedger, int $partnerId, int $tenantId): array
    {
        $lines = [];
        if ($appliedBase > 0) {
            $lines[] = ['account' => $this->controlAccount($tenantId), 'debit' => $appliedBase, 'credit' => 0.0, 'description' => 'Payment '.$header['number'].' - '.$header['partner'], 'contact_id' => $partnerId];
        }
        if ($unusedBase > 0) {
            $lines[] = ['account' => $this->advanceAccount($tenantId), 'debit' => $unusedBase, 'credit' => 0.0, 'description' => 'Advance to '.$header['partner'].' (unapplied in Zoho)', 'contact_id' => $partnerId];
        }
        $lines[] = ['account' => $bankLedger, 'debit' => 0.0, 'credit' => round($appliedBase + $unusedBase, 2), 'description' => 'Payment made - '.$header['partner']];

        return $lines;
    }
}

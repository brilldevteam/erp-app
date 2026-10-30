<?php

namespace App\Services\ZohoMigration;

use Illuminate\Support\Str;

class ZohoProfileCatalog
{
    private const PROFILES = [
        'contacts.v1' => ['dataset' => 'contacts', 'priority' => 50, 'required' => [['contact id'], ['contact name', 'display name']]],
        'items.v1' => ['dataset' => 'items', 'priority' => 50, 'required' => [['item id'], ['item name']]],
        'estimates.v1' => ['dataset' => 'quotations', 'priority' => 50, 'required' => [['estimate id'], ['estimate number'], ['customer name']]],
        'invoices.v1' => ['dataset' => 'sales_invoices', 'priority' => 50, 'required' => [['invoice id'], ['invoice number'], ['customer name']]],
        'invoice_details.v1' => ['dataset' => 'invoice_control', 'priority' => 90, 'required' => [['invoice number'], ['total'], ['balance due'], ['status']]],
        // Zoho's payment exports name their id columns "CustomerPayment ID" and "VendorPayment ID".
        'payments_received.v1' => ['dataset' => 'customer_payments', 'priority' => 50, 'required' => [['payment id', 'customerpayment id'], ['payment number'], ['customer name'], ['amount', 'payment amount']]],
        'customer_payment_allocations.v1' => ['dataset' => 'customer_payment_allocations', 'priority' => 100, 'required' => [['payment id'], ['invoice id', 'invoice number'], ['amount applied', 'applied amount']]],
        // Zoho's credit note export names its id column "CreditNotes ID".
        'credit_notes.v1' => ['dataset' => 'credit_notes', 'priority' => 50, 'required' => [['credit note id', 'creditnotes id'], ['credit note number'], ['customer name']]],
        // Zoho has no export of how a credit note was split over several invoices; this sheet copies its
        // "Credit Applied Invoices" list (Credit Note Number, Invoice Number, Amount Credited).
        'credit_note_applications.v1' => ['dataset' => 'credit_note_applications', 'priority' => 100, 'required' => [['credit note number', 'credit note id'], ['invoice number', 'invoice id'], ['amount credited', 'amount applied', 'applied amount']]],
        // Zoho's Refund History report (credit note refunds).
        'refund_history.v1' => ['dataset' => 'credit_refunds', 'priority' => 90, 'required' => [['refund id'], ['entity number'], ['entity type'], ['refund from account name'], ['bcy amount']]],
        'bills.v1' => ['dataset' => 'purchase_invoices', 'priority' => 50, 'required' => [['bill id'], ['bill number'], ['vendor name']]],
        'bill_details.v1' => ['dataset' => 'bill_control', 'priority' => 90, 'required' => [['bill number'], ['total'], ['balance due'], ['status']]],
        // Ranked above bills: a vendor payment export also carries Bill ID, Bill Number and Vendor Name.
        'vendor_payments.v1' => ['dataset' => 'vendor_payments', 'priority' => 60, 'required' => [['payment id', 'vendorpayment id'], ['payment number'], ['vendor name'], ['amount', 'payment amount']]],
        'vendor_payment_allocations.v1' => ['dataset' => 'vendor_payment_allocations', 'priority' => 100, 'required' => [['payment id'], ['bill id', 'bill number'], ['amount applied', 'applied amount']]],
        'expenses.v1' => ['dataset' => 'expenses', 'priority' => 50, 'required' => [['expense reference id'], ['expense date'], ['expense account'], ['paid through'], ['expense amount']]],
        'vendor_credits.v1' => ['dataset' => 'vendor_credits', 'priority' => 50, 'required' => [['vendor credit id'], ['vendor credit number'], ['vendor name']]],
        'chart_of_accounts.v1' => ['dataset' => 'chart_of_accounts', 'priority' => 50, 'required' => [['account id'], ['account name'], ['account type']]],
        // Ranked above the trial balance, whose Account / Debit / Credit columns a journal export also has.
        'journals.v1' => ['dataset' => 'manual_journals', 'priority' => 60, 'required' => [['journal id'], ['journal number'], ['journal date'], ['account'], ['debit'], ['credit']]],
        // Zoho's Detailed General Ledger / Account Transactions reports: one row per account leg, keyed by Zoho ids.
        'ledger_report.v1' => ['dataset' => 'general_ledger', 'priority' => 85, 'required' => [['transaction id'], ['transaction type'], ['account id'], ['account name'], ['debit'], ['credit']]],
        'general_ledger.v1' => ['dataset' => 'general_ledger', 'priority' => 80, 'required' => [['transaction id'], ['account'], ['debit'], ['credit']]],
        // Zoho's Trial Balance report: "As of" (Net Debit / Net Credit) or "From … To …" (also Opening and Closing Balance).
        'trial_balance.v1' => ['dataset' => 'trial_balance', 'priority' => 50, 'required' => [['account'], ['account code'], ['net debit', 'debit'], ['net credit', 'credit']]],
        'ar_aging.v1' => ['dataset' => 'ar_aging', 'priority' => 80, 'required' => [['customer name'], ['current'], ['total']]],
        'ap_aging.v1' => ['dataset' => 'ap_aging', 'priority' => 80, 'required' => [['vendor name'], ['current'], ['total']]],
    ];

    public function detect(array $headers): array
    {
        $normalized = array_values(array_unique(array_filter(array_map([$this, 'normalize'], $headers))));
        $matches = [];

        foreach (self::PROFILES as $key => $profile) {
            $matchedGroups = 0;
            foreach ($profile['required'] as $alternatives) {
                if (array_intersect($alternatives, $normalized)) {
                    $matchedGroups++;
                }
            }

            $total = count($profile['required']);
            if ($matchedGroups === $total) {
                $matches[] = [
                    'profile_key' => $key,
                    'dataset_type' => $profile['dataset'],
                    'confidence' => 100,
                    'priority' => $profile['priority'],
                ];
            }
        }

        if ($matches) {
            $highestPriority = max(array_column($matches, 'priority'));
            $preferred = array_values(array_filter(
                $matches,
                fn (array $match) => $match['priority'] === $highestPriority
            ));
            if (count($preferred) === 1) {
                unset($preferred[0]['priority']);

                return $preferred[0] + ['ambiguous' => false];
            }
        }

        return [
            'profile_key' => null,
            'dataset_type' => null,
            'confidence' => 0,
            'ambiguous' => count($matches) > 1,
            'candidates' => $matches,
        ];
    }

    public function normalize(mixed $value): string
    {
        return (string) Str::of((string) $value)
            ->replaceMatches('/[\x{FEFF}\x{200B}]/u', '')
            ->trim()
            ->lower()
            ->replaceMatches('/[_\-]+/', ' ')
            ->replaceMatches('/\s+/', ' ');
    }
}

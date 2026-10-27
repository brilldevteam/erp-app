<?php

namespace App\Services\ZohoMigration;

use Illuminate\Support\Str;

class ZohoProfileCatalog
{
    private const PROFILES = [
        'contacts.v1' => ['dataset' => 'contacts', 'priority' => 50, 'required' => [['contact id'], ['contact name', 'display name']]],
        'items.v1' => ['dataset' => 'items', 'priority' => 50, 'required' => [['item id'], ['item name']]],
        'invoices.v1' => ['dataset' => 'sales_invoices', 'priority' => 50, 'required' => [['invoice id'], ['invoice number'], ['customer name']]],
        'invoice_details.v1' => ['dataset' => 'invoice_control', 'priority' => 90, 'required' => [['invoice number'], ['total'], ['balance due'], ['status']]],
        'payments_received.v1' => ['dataset' => 'customer_payments', 'priority' => 50, 'required' => [['payment id'], ['payment number'], ['customer name'], ['amount', 'payment amount']]],
        'customer_payment_allocations.v1' => ['dataset' => 'customer_payment_allocations', 'priority' => 100, 'required' => [['payment id'], ['invoice id', 'invoice number'], ['amount applied', 'applied amount']]],
        'credit_notes.v1' => ['dataset' => 'credit_notes', 'priority' => 50, 'required' => [['credit note id'], ['credit note number'], ['customer name']]],
        'credit_note_applications.v1' => ['dataset' => 'credit_note_applications', 'priority' => 100, 'required' => [['credit note id'], ['invoice id', 'invoice number'], ['amount applied', 'applied amount']]],
        'bills.v1' => ['dataset' => 'purchase_invoices', 'priority' => 50, 'required' => [['bill id'], ['bill number'], ['vendor name']]],
        'bill_details.v1' => ['dataset' => 'bill_control', 'priority' => 90, 'required' => [['bill number'], ['total'], ['balance due'], ['status']]],
        'vendor_payments.v1' => ['dataset' => 'vendor_payments', 'priority' => 50, 'required' => [['payment id'], ['payment number'], ['vendor name'], ['amount', 'payment amount']]],
        'vendor_payment_allocations.v1' => ['dataset' => 'vendor_payment_allocations', 'priority' => 100, 'required' => [['payment id'], ['bill id', 'bill number'], ['amount applied', 'applied amount']]],
        'vendor_credits.v1' => ['dataset' => 'vendor_credits', 'priority' => 50, 'required' => [['vendor credit id'], ['vendor credit number'], ['vendor name']]],
        'chart_of_accounts.v1' => ['dataset' => 'chart_of_accounts', 'priority' => 50, 'required' => [['account id'], ['account name'], ['account type']]],
        'general_ledger.v1' => ['dataset' => 'general_ledger', 'priority' => 80, 'required' => [['transaction id'], ['account'], ['debit'], ['credit']]],
        'trial_balance.v1' => ['dataset' => 'trial_balance', 'priority' => 50, 'required' => [['account'], ['debit'], ['credit']]],
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

<?php

namespace App\Services\ZohoMigration;

class ZohoStageCatalog
{
    private const STAGES = [
        ['key' => 'configuration', 'title' => 'Migration configuration', 'description' => 'Organization, accounting mode, cutover and import rules.', 'supported' => true, 'datasets' => [], 'destination' => null],
        ['key' => 'currencies', 'title' => 'Currencies and exchange rates', 'description' => 'Currencies and historical rates used by transactions.', 'supported' => false, 'datasets' => ['currencies', 'exchange_rates'], 'destination' => null],
        ['key' => 'chart_of_accounts', 'title' => 'Account types and Chart of Accounts', 'description' => 'Financial account structure and account codes.', 'supported' => true, 'datasets' => ['chart_of_accounts'], 'destination' => 'account.chart-of-accounts.index'],
        ['key' => 'taxes', 'title' => 'Taxes and tax groups', 'description' => 'Zoho tax rates and grouped tax definitions.', 'supported' => false, 'datasets' => ['taxes', 'tax_groups'], 'destination' => null],
        ['key' => 'units', 'title' => 'Product and service units', 'description' => 'Units used by products, services and document lines.', 'supported' => false, 'datasets' => ['units'], 'destination' => null],
        ['key' => 'categories', 'title' => 'Product and service categories', 'description' => 'Item classification and reporting categories.', 'supported' => false, 'datasets' => ['item_categories'], 'destination' => null],
        ['key' => 'warehouses', 'title' => 'Warehouses', 'description' => 'Storage locations used by inventory documents.', 'supported' => false, 'datasets' => ['warehouses'], 'destination' => 'warehouses.index'],
        ['key' => 'customers', 'title' => 'Customers', 'description' => 'Customer contacts, addresses, tax treatment and currency.', 'supported' => true, 'datasets' => ['contacts'], 'destination' => 'account.customers.index'],
        ['key' => 'vendors', 'title' => 'Vendors', 'description' => 'Vendor contacts, addresses, tax treatment and currency.', 'supported' => true, 'datasets' => ['contacts'], 'destination' => 'account.vendors.index'],
        ['key' => 'items', 'title' => 'Products and services', 'description' => 'Zoho has no item master for this migration. Copy and paste the verified product and service list before importing documents.', 'supported' => true, 'manual_entry' => true, 'datasets' => ['items'], 'destination' => 'product-service.items.index'],
        ['key' => 'payment_accounts', 'title' => 'Bank, cash and payment accounts', 'description' => 'Bank, cash, card and clearing accounts.', 'supported' => false, 'datasets' => ['bank_accounts'], 'destination' => 'account.bank-accounts.index'],
        ['key' => 'income_expense_categories', 'title' => 'Revenue and expense categories', 'description' => 'Categories used by direct income and expense entries.', 'supported' => false, 'datasets' => ['revenue_categories', 'expense_categories'], 'destination' => null],
        ['key' => 'quotations', 'title' => 'Quotations', 'description' => 'Customer quotations with complete line details.', 'supported' => false, 'datasets' => ['quotations'], 'destination' => 'account.quotations.index'],
        ['key' => 'sales_invoices', 'title' => 'Sales invoices', 'description' => 'Invoices and lines without trusting typed paid balances.', 'supported' => true, 'datasets' => ['sales_invoices', 'invoice_control'], 'destination' => 'sales-invoices.index'],
        ['key' => 'purchase_invoices', 'title' => 'Purchase invoices and bills', 'description' => 'Vendor bills and complete line details.', 'supported' => true, 'datasets' => ['purchase_invoices', 'bill_control'], 'destination' => 'purchase-invoices.index'],
        ['key' => 'credit_notes', 'title' => 'Credit notes', 'description' => 'Customer credits with discounts and line details.', 'supported' => true, 'datasets' => ['credit_notes'], 'destination' => 'account.credit-notes.index'],
        ['key' => 'vendor_credits', 'title' => 'Vendor credits', 'description' => 'Vendor credits with complete line details.', 'supported' => true, 'datasets' => ['vendor_credits'], 'destination' => 'account.debit-notes.index'],
        ['key' => 'customer_payments', 'title' => 'Customer payments and allocations', 'description' => 'Payments received and one allocation per invoice.', 'supported' => true, 'datasets' => ['customer_payments', 'customer_payment_allocations'], 'destination' => 'account.customer-payments.index'],
        ['key' => 'vendor_payments', 'title' => 'Vendor payments and allocations', 'description' => 'Vendor payments and one allocation per bill.', 'supported' => true, 'datasets' => ['vendor_payments', 'vendor_payment_allocations'], 'destination' => 'account.vendor-payments.index'],
        ['key' => 'credit_applications', 'title' => 'Credit applications and refunds', 'description' => 'Customer and vendor credit applications and refunds.', 'supported' => true, 'datasets' => ['credit_note_applications'], 'destination' => null],
        ['key' => 'income_expenses', 'title' => 'Revenues and expenses', 'description' => 'Direct revenue and expense transactions.', 'supported' => false, 'datasets' => ['revenues', 'expenses'], 'destination' => null],
        ['key' => 'bank_activity', 'title' => 'Bank transfers and petty cash', 'description' => 'Transfers and petty-cash activity.', 'supported' => false, 'datasets' => ['bank_transfers', 'petty_cash'], 'destination' => null],
        ['key' => 'inventory_cutover', 'title' => 'Warehouse stock', 'description' => 'Zoho has no warehouse stock export. Copy and paste the approved SKU-by-warehouse opening quantities.', 'supported' => true, 'manual_entry' => true, 'datasets' => ['inventory_snapshot'], 'destination' => 'warehouses.index'],
        ['key' => 'accounting', 'title' => 'Manual journals or Zoho ledger', 'description' => 'Accounting records according to the selected mode.', 'supported' => true, 'datasets' => ['general_ledger'], 'destination' => 'account.journal-entries.index'],
        ['key' => 'reconciliation', 'title' => 'Final reconciliation', 'description' => 'AR, AP, bank, cash, inventory and Trial Balance controls.', 'supported' => true, 'datasets' => ['trial_balance', 'ar_aging', 'ap_aging'], 'destination' => 'account.reports.index'],
    ];

    public function all(): array
    {
        return array_map(fn (array $stage, int $index) => $stage + [
            'position' => $index + 1,
            'previous_key' => $index > 0 ? self::STAGES[$index - 1]['key'] : null,
        ], self::STAGES, array_keys(self::STAGES));
    }

    public function find(string $key): ?array
    {
        foreach ($this->all() as $stage) {
            if ($stage['key'] === $key) {
                return $stage;
            }
        }

        return null;
    }
}

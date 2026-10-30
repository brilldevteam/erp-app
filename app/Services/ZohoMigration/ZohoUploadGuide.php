<?php

namespace App\Services\ZohoMigration;

/**
 * What each upload step expects, shown before the file is chosen: which Zoho export or report, where to find it, and
 * for steps that take several files the order to add them in. The datasets tie each entry to the files already attached.
 */
class ZohoUploadGuide
{
    private const ALL_DATES = 'Choose all dates (or a custom range from the first transaction to today) so nothing is left out.';
    private const CHART_FILE = [
        'datasets' => ['chart_of_accounts'],
        'title' => 'Chart of Accounts export',
        'where' => ['Accountant → Chart of Accounts', '⋯ menu (top right) → Export Chart of Accounts', 'Format: XLSX or CSV'],
    ];

    private const GUIDES = [
        'chart_of_accounts' => [
            'files' => [self::CHART_FILE + ['note' => 'Include inactive accounts: older transactions may still use them.']],
        ],
        'customers' => [
            'files' => [[
                'datasets' => ['contacts'],
                'title' => 'Customers export',
                'where' => ['Sales → Customers', '⋯ menu → Export Customers', 'Choose all customers, format XLSX or CSV'],
                'note' => 'Include inactive customers: their old invoices are imported too.',
            ]],
        ],
        'vendors' => [
            'files' => [[
                'datasets' => ['contacts'],
                'title' => 'Vendors export',
                'where' => ['Purchases → Vendors', '⋯ menu → Export Vendors', 'Choose all vendors, format XLSX or CSV'],
                'note' => 'Include inactive vendors: their old bills are imported too.',
            ]],
        ],
        'payment_accounts' => [
            'files' => [self::CHART_FILE + ['note' => 'Use the same Chart of Accounts file as step 2. Bank, cash, card and other payment accounts are taken from it.']],
        ],
        'income_expense_categories' => [
            'files' => [self::CHART_FILE + ['note' => 'Use the same Chart of Accounts file as step 2. Income and expense accounts become the categories.']],
        ],
        'quotations' => [
            'files' => [[
                'datasets' => ['quotations'],
                'title' => 'Quotes export',
                'where' => ['Sales → Quotes', '⋯ menu → Export Quotes', 'All statuses, format XLSX or CSV'],
                'note' => self::ALL_DATES,
            ]],
        ],
        'sales_invoices' => [
            'files' => [[
                'datasets' => ['sales_invoices'],
                'title' => 'Invoices export',
                'where' => ['Sales → Invoices', '⋯ menu → Export Invoices', 'All statuses, format XLSX or CSV'],
                'note' => self::ALL_DATES,
            ]],
        ],
        'purchase_invoices' => [
            'files' => [[
                'datasets' => ['purchase_invoices'],
                'title' => 'Bills export',
                'where' => ['Purchases → Bills', '⋯ menu → Export Bills', 'All statuses, format XLSX or CSV'],
                'note' => self::ALL_DATES,
            ]],
        ],
        'credit_notes' => [
            'files' => [[
                'datasets' => ['credit_notes'],
                'title' => 'Credit Notes export',
                'where' => ['Sales → Credit Notes', '⋯ menu → Export Credit Notes', 'All statuses, format XLSX or CSV'],
                'note' => self::ALL_DATES,
            ]],
        ],
        'vendor_credits' => [
            'files' => [[
                'datasets' => ['vendor_credits'],
                'title' => 'Vendor Credits export',
                'where' => ['Purchases → Vendor Credits', '⋯ menu → Export Vendor Credits', 'All statuses, format XLSX or CSV'],
                'note' => self::ALL_DATES,
            ]],
        ],
        'customer_payments' => [
            'files' => [[
                'datasets' => ['customer_payments', 'customer_payment_allocations'],
                'title' => 'Payments Received export',
                'where' => ['Sales → Payments Received', '⋯ menu → Export Payments Received', 'Format XLSX or CSV'],
                'note' => self::ALL_DATES.' The export lists each payment with the invoices it paid.',
            ]],
        ],
        'vendor_payments' => [
            'files' => [[
                'datasets' => ['vendor_payments', 'vendor_payment_allocations'],
                'title' => 'Payments Made export',
                'where' => ['Purchases → Payments Made', '⋯ menu → Export Payments Made', 'Format XLSX or CSV'],
                'note' => self::ALL_DATES.' The export lists each payment with the bills it paid.',
            ]],
        ],
        'credit_applications' => [
            'files' => [
                [
                    'datasets' => ['credit_refunds'],
                    'title' => 'Refund History report',
                    'where' => ['Reports → Refund History', 'Date range: from the first transaction to today', 'Export → XLSX'],
                    'note' => 'Needed even if there are few refunds: it shows which credits were paid back instead of applied.',
                ],
                [
                    'datasets' => ['credit_note_applications'],
                    'title' => 'Credit split sheet',
                    'optional' => true,
                    'where' => ['Make it in Excel with three columns: Credit Note Number, Invoice Number, Amount Credited', 'Copy the amounts from the credit note in Zoho (its list of credited invoices)'],
                    'note' => 'Only for a credit note applied to several invoices when the step asks for it. Zoho has no export of this split.',
                ],
            ],
        ],
        'income_expenses' => [
            'files' => [[
                'datasets' => ['expenses'],
                'title' => 'Expenses export',
                'where' => ['Purchases → Expenses', '⋯ menu → Export Expenses', 'All dates, format XLSX or CSV'],
                'note' => 'Export it on the same day as the other files, so no recent expense is missing. After the import, a newer full export can still be uploaded: only new expenses are added.',
            ]],
        ],
        'bank_activity' => [
            'files' => [[
                'datasets' => ['general_ledger'],
                'title' => 'Detailed General Ledger report',
                'where' => ['Reports → Accountant → Detailed General Ledger', 'Date range: from the first transaction to today, all accounts', 'Export → XLSX'],
                'note' => 'Upload it exactly as exported: the title row with the dates must stay.',
            ]],
        ],
        'accounting' => [
            'files' => [[
                'datasets' => ['manual_journals'],
                'title' => 'Manual Journals export',
                'where' => ['Accountant → Manual Journals', '⋯ menu → Export Journals', 'All dates, format XLSX or CSV'],
            ]],
        ],
        'reconciliation' => [
            'files' => [
                [
                    'datasets' => ['trial_balance'],
                    'title' => 'Trial Balance report',
                    'where' => ['Reports → Accountant → Trial Balance', 'Report basis: Accrual', 'Date: from the first transaction to the cut-off date, or "As of" the cut-off date', 'Export → XLSX'],
                    'note' => 'Upload it exactly as exported: the title row gives the date.',
                ],
                [
                    'datasets' => ['ar_aging'],
                    'title' => 'AR Aging Summary report',
                    'optional' => true,
                    'where' => ['Reports → Receivables → AR Aging Summary', 'As of the same cut-off date', 'Export → XLSX'],
                    'note' => 'Checks every customer balance.',
                ],
                [
                    'datasets' => ['ap_aging'],
                    'title' => 'AP Aging Summary report',
                    'optional' => true,
                    'where' => ['Reports → Payables → AP Aging Summary', 'As of the same cut-off date', 'Export → XLSX'],
                    'note' => 'Checks every vendor balance.',
                ],
            ],
        ],
    ];

    public static function for(string $stageKey): ?array
    {
        $guide = self::GUIDES[$stageKey] ?? null;
        if (!$guide) {
            return null;
        }

        return $guide + ['tips' => [
            'Upload the file exactly as Zoho exported it: do not rename columns, delete rows or copy it into another sheet.',
            'XLSX or CSV, up to 10 MB and 10,000 rows.',
        ]];
    }
}

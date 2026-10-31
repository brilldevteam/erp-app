<?php

namespace App\Services\ZohoMigration;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Empty files laid out exactly like Zoho Books exports, for users who fill the data in by hand. Headers, their order and
 * the sheet names are copied from real Zoho exports (reports keep Zoho's own trailing spaces); the credit split sheet is
 * the three-column layout the upload guide describes. Reports start with Zoho's one-cell title row.
 */
class ZohoExportTemplate
{
    private const TEMPLATES = [
        'customers' => ['sheet' => 'Contacts', 'headers' => [
            'Created Time', 'Last Modified Time', 'Display Name', 'Display Name (Secondary Language)', 'Company Name', 'Salutation', 'First Name',
            'Last Name', 'Phone', 'Currency Code', 'Notes', 'Website', 'Status', 'Created By', 'Accounts Receivable', 'Opening Balance',
            'Opening Balance Exchange Rate', 'Bank Account Payment', 'Portal Enabled', 'Credit Limit', 'Customer Sub Type', 'Billing Attention',
            'Billing Address', 'Billing Street2', 'Billing City', 'Billing State', 'Billing Country', 'Billing County', 'Billing Code',
            'Billing Phone', 'Billing Fax', 'Billing Latitude', 'Billing Longitude', 'Billing Attention (Secondary Language)',
            'Billing Address (Secondary Language)', 'Billing Street 2 (Secondary Language)', 'Billing City (Secondary Language)',
            'Billing State (Secondary Language)', 'Billing Country (Secondary Language)', 'Billing Code (Secondary Language)',
            'Billing Phone (Secondary Language)', 'Billing Fax (Secondary Language)', 'Shipping Attention', 'Shipping Address', 'Shipping Street2',
            'Shipping City', 'Shipping State', 'Shipping Country', 'Shipping County', 'Shipping Code', 'Shipping Phone', 'Shipping Fax',
            'Shipping Latitude', 'Shipping Longitude', 'Shipping Attention (Secondary Language)', 'Shipping Address (Secondary Language)',
            'Shipping Street 2 (Secondary Language)', 'Shipping City (Secondary Language)', 'Shipping State (Secondary Language)',
            'Shipping Country (Secondary Language)', 'Shipping Code (Secondary Language)', 'Shipping Phone (Secondary Language)',
            'Shipping Fax (Secondary Language)', 'Skype Identity', 'Facebook', 'Twitter', 'Department', 'Designation', 'Price List', 'Payment Terms',
            'Payment Terms Label', 'Last Sync Time', 'Owner Name', 'Primary Contact ID', 'EmailID', 'MobilePhone', 'Contact ID', 'Contact Name',
            'Contact Type', 'Contact Address ID', 'Source',
        ]],
        'vendors' => ['sheet' => 'Vendors', 'headers' => [
            'Created Time', 'Last Modified Time', 'Contact ID', 'Contact Name', 'Company Name', 'Display Name', 'Salutation', 'First Name',
            'Last Name', 'EmailID', 'Phone', 'MobilePhone', 'Currency Code', 'Notes', 'Website', 'Status', 'Created By', 'Opening Balance',
            'Payment Terms Label', 'Payment Terms', 'Skype Identity', 'Department', 'Designation', 'Facebook', 'Twitter', 'Price List',
            'Contact Address ID', 'Billing Attention', 'Billing Address', 'Billing Street2', 'Billing City', 'Billing State', 'Billing Country',
            'Billing Code', 'Billing Phone', 'Billing Fax', 'Shipping Attention', 'Shipping Address', 'Shipping Street2', 'Shipping City',
            'Shipping State', 'Shipping Country', 'Shipping Code', 'Shipping Phone', 'Shipping Fax', 'Source', 'Last Sync Time', 'Exchange Rate',
            'Owner Name', 'Primary Contact ID',
        ]],
        'chart_of_accounts' => ['sheet' => 'Accounts', 'headers' => [
            'Account ID', 'Account Name', 'Account Code', 'Description', 'Account Type', 'Mileage Rate', 'Mileage Unit', 'IsMileage', 'Account # ',
            'Account Status', 'Currency', 'Parent Account',
        ]],
        'quotations' => ['sheet' => 'Estimate', 'headers' => [
            'Estimate Date', 'Estimate ID', 'Estimate Number', 'Estimate Status', 'Customer ID', 'Expiry Date', 'PurchaseOrder', 'Currency Code',
            'Exchange Rate', 'Discount Type', 'Is Discount Before Tax', 'Entity Discount Percent', 'SubTotal', 'Total', 'Adjustment', 'Notes',
            'Terms & Conditions', 'Subject', 'Customer Name', 'Project Name', 'Project ID', 'Sales person', 'Billing Address', 'Billing City',
            'Billing State', 'Billing Country', 'Billing Code', 'Billing Fax', 'Template Name', 'Adjustment Description', 'Shipping Address',
            'Shipping City', 'Shipping State', 'Shipping Country', 'Shipping Code', 'Shipping Fax', 'Source', 'Reference ID', 'Last Sync Time',
            'Entity Discount Amount', 'Shipping Charge', 'Item Name', 'Item Desc', 'Quantity', 'Discount', 'Discount Amount', 'Item Total',
            'Product ID', 'Account', 'SKU', 'Usage unit', 'Item Price', 'Kit Combo Item Name',
        ]],
        'sales_invoices' => ['sheet' => 'Invoice', 'headers' => [
            'Invoice Date', 'Invoice ID', 'Invoice Number', 'Issued Date', 'Invoice Status', 'Accounts Receivable', 'Customer ID', 'Customer Name',
            'Customer Name (Secondary Language)', 'Due Date', 'PurchaseOrder', 'Currency Code', 'Exchange Rate', 'Discount Type',
            'Is Discount Before Tax', 'Template Name', 'Entity Discount Percent', 'SubTotal', 'Total', 'TotalRetentionAmountFCY',
            'TotalRetentionAmountBCY', 'Balance', 'Adjustment', 'Adjustment Description', 'Adjustment Account', 'Expected Payment Date',
            'Last Payment Date', 'Payment Terms', 'Payment Terms Label', 'Notes', 'Terms & Conditions', 'Invoice Type', 'Entity Discount Amount',
            'Shipping Charge', 'Shipping Charge Account', 'Item Name', 'Item Desc', 'Quantity', 'Discount', 'Discount Amount', 'Item Total',
            'Usage unit', 'Item Price', 'Product ID', 'Brand', 'Sales Order Number', 'Expense Reference ID', 'Recurrence Name', 'PayPal',
            'Authorize.Net', 'Google Checkout', 'Payflow Pro', 'Stripe', 'Paytm', '2Checkout', 'Braintree', 'Forte', 'WorldPay', 'Payments Pro',
            'Square', 'WePay', 'Razorpay', 'ICICI EazyPay', 'GoCardless', 'Partial Payments', 'Billing Attention', 'Billing Address',
            'Billing Street2', 'Billing City', 'Billing State', 'Billing Country', 'Billing Code', 'Billing Phone', 'Billing Fax',
            'Billing Attention (Secondary Language)', 'Billing Address (Secondary Language)', 'Billing Street 2 (Secondary Language)',
            'Billing City (Secondary Language)', 'Billing State (Secondary Language)', 'Billing Country (Secondary Language)',
            'Billing Code (Secondary Language)', 'Billing Phone (Secondary Language)', 'Billing Fax (Secondary Language)', 'Shipping Attention',
            'Shipping Address', 'Shipping Street2', 'Shipping City', 'Shipping State', 'Shipping Country', 'Shipping Code', 'Shipping Fax',
            'Shipping Phone Number', 'Shipping Attention (Secondary Language)', 'Shipping Address (Secondary Language)',
            'Shipping Street 2 (Secondary Language)', 'Shipping City (Secondary Language)', 'Shipping State (Secondary Language)',
            'Shipping Country (Secondary Language)', 'Shipping Code (Secondary Language)', 'Shipping Phone (Secondary Language)',
            'Shipping Fax (Secondary Language)', 'SKU', 'Project ID', 'Project Name', 'Round Off', 'Sales person', 'Subject',
            'Primary Contact EmailID', 'Primary Contact Mobile', 'Primary Contact Phone', 'Estimate Number', 'Custom Charges', 'Shipping Bill#',
            'Shipping Bill Date', 'Shipping Bill Total', 'PortCode', 'Account', 'Account Code', 'Kit Combo Item Name',
        ]],
        'purchase_invoices' => ['sheet' => 'Bills', 'headers' => [
            'Bill Date', 'Due Date', 'Bill ID', 'Accounts Payable', 'Vendor Name', 'Entity Discount Percent', 'Payment Terms', 'Payment Terms Label',
            'Bill Number', 'PurchaseOrder', 'Currency Code', 'Exchange Rate', 'SubTotal', 'Total', 'Balance', 'TotalRetentionAmountFCY',
            'TotalRetentionAmountBCY', 'Vendor Notes', 'Terms & Conditions', 'Adjustment', 'Adjustment Description', 'Adjustment Account',
            'Is Inclusive Tax', 'Submitted By', 'Approved By', 'Submitted Date', 'Approved Date', 'Bill Status', 'Created By', 'Product ID',
            'Item Name', 'Account', 'Account Code', 'Description', 'Quantity', 'Usage unit', 'Tax Amount', 'Item Total', 'Is Billable', 'SKU',
            'Rate', 'Discount Type', 'Is Discount Before Tax', 'Discount', 'Discount Amount', 'Purchase Order Number', 'Tax ID', 'Tax Name',
            'Tax Percentage', 'Tax Type', 'Entity Discount Amount', 'Discount Account', 'Discount Account Code', 'Item Discount Account',
            'Item Discount Account Code', 'Is Landed Cost', 'Customer Name', 'Project Name',
        ]],
        'credit_notes' => ['sheet' => 'CreditNotes', 'headers' => [
            'Credit Note Date', 'Issued Date', 'Product ID', 'Issued Date', 'CreditNotes ID', 'Credit Note Number', 'Credit Note Status',
            'Accounts Receivable', 'Customer Name', 'Customer Name (Secondary Language)', 'Billing Attention', 'Billing Address', 'Billing Street 2',
            'Billing City', 'Billing State', 'Billing Country', 'Billing Code', 'Billing Phone', 'Billing Fax',
            'Billing Attention (Secondary Language)', 'Billing Address (Secondary Language)', 'Billing Street 2 (Secondary Language)',
            'Billing City (Secondary Language)', 'Billing State (Secondary Language)', 'Billing Country (Secondary Language)',
            'Billing Code (Secondary Language)', 'Billing Phone (Secondary Language)', 'Billing Fax (Secondary Language)', 'Shipping Attention',
            'Shipping Address', 'Shipping Street 2', 'Shipping City', 'Shipping State', 'Shipping Country', 'Shipping Phone', 'Shipping Code',
            'Shipping Fax', 'Shipping Attention (Secondary Language)', 'Shipping Address (Secondary Language)',
            'Shipping Street 2 (Secondary Language)', 'Shipping City (Secondary Language)', 'Shipping State (Secondary Language)',
            'Shipping Country (Secondary Language)', 'Shipping Code (Secondary Language)', 'Shipping Phone (Secondary Language)',
            'Shipping Fax (Secondary Language)', 'Customer ID', 'Currency Code', 'Exchange Rate', 'Total', 'Balance', 'Entity Discount Percent',
            'Notes', 'Terms & Conditions', 'Reference#', 'Shipping Charge', 'Shipping Charge Account', 'Adjustment', 'Adjustment Account',
            'Is Discount Before Tax', 'Item Name', 'Discount', 'Discount Amount', 'Quantity', 'Item Desc', 'Item Total', 'Applied Invoice Number',
            'Project ID', 'Project Name', 'Sales person', 'Discount Type', 'SubTotal', 'Round Off', 'Adjustment Description', 'Subject',
            'Template Name', 'Usage unit', 'Item Price', 'Account', 'Account Code', 'SKU', 'Entity Discount Amount', 'Kit Combo Item Name',
        ]],
        'vendor_credits' => ['sheet' => 'DebitNotes', 'headers' => [
            'Vendor Credit Date', 'Vendor Credit ID', 'Vendor Credit Number', 'Entity Type', 'Vendor Credit Status', 'Accounts Payable',
            'Vendor Name', 'Vendor ID', 'Associated Bill Number', 'Currency Code', 'Exchange Rate', 'Is Inclusive Tax', 'Account', 'Account Code',
            'Item Name', 'Product ID', 'SKU', 'Item Desc', 'Quantity', 'Usage unit', 'Item Price', 'Tax1 ID', 'Item Tax', 'Item Tax %',
            'Item Tax Amount', 'Item Tax Type', 'Item Non Advol Cess Amount', 'Item Total', 'SubTotal', 'Discount Type', 'Is Discount Before Tax',
            'Entity Discount Percent', 'Entity Discount Amount', 'Discount Account', 'Discount Account Code', 'Total', 'Balance', 'Adjustment',
            'Adjustment Description', 'Adjustment Account', 'Notes', 'Reference#', 'Project ID', 'Project Name',
        ]],
        'customer_payments' => ['sheet' => 'CustomerPayments', 'headers' => [
            'Payment Number', 'CustomerPayment ID', 'Mode', 'CustomerID', 'Description', 'Exchange Rate', 'Amount', 'Unused Amount', 'Bank Charges',
            'Reference Number', 'Currency Code', 'Payment Number Prefix', 'Payment Number Suffix', 'Customer Name', 'Payment Type', 'Date',
            'Created Time', 'Deposit To', 'Deposit To Account Code', 'Payment Status', 'InvoicePayment ID', 'Amount Applied to Invoice',
            'Invoice Payment Applied Date', 'Invoice Number', 'Invoice Date',
        ]],
        'vendor_payments' => ['sheet' => 'VendorPayments', 'headers' => [
            'Payment Number', 'Payment Number Prefix', 'Payment Number Suffix', 'VendorPayment ID', 'Mode', 'Description', 'Exchange Rate', 'Amount',
            'Unused Amount', 'Reference Number', 'Currency Code', 'Bank Charges', 'Payment Status', 'Date', 'Vendor Name', 'EmailID', 'Paid Through',
            'Paid Through Account Code', 'Tax Account', 'Bank Reference Number', 'PIPayment ID', 'Bill ID', 'Bill Amount',
            'Bill Payment Applied Date', 'Bill Date', 'Bill Number',
        ]],
        'credit_note_applications' => ['sheet' => 'Credit split', 'headers' => [
            'Credit Note Number', 'Invoice Number', 'Amount Credited',
        ]],
        'expenses' => ['sheet' => 'Expense', 'headers' => [
            'Expense Date', 'Expense Description', 'Expense Account', 'Expense Account Code', 'Paid Through', 'Paid Through Account Code', 'Vendor',
            'Project Name', 'Entry Number', 'Currency Code', 'Exchange Rate', 'Is Inclusive Tax', 'Mileage Rate', 'Mileage Unit', 'Distance',
            'Start Odometer Reading', 'End Odometer Reading', 'Mileage Type', 'Vehicle Name', 'Claimant Email', 'Tax Name', 'Tax Percentage',
            'Tax Type', 'Tax Amount', 'Expense Amount', 'Total', 'Reference#', 'Is Billable', 'Customer Name', 'Expense Reference ID',
            'Recurrence Name', 'ExpenseReport Name', 'Is Reimbursable',
        ]],
        'general_ledger' => ['sheet' => 'Detailed General Ledger', 'report' => 'Detailed General Ledger', 'headers' => [
            'date', 'account_name', 'transaction_details', 'transaction_id', 'reference_transaction_id', 'offset_account_id', 'offset_account_type',
            'transaction_type', 'reference_number', 'entity_number', 'debit', 'credit', 'net_amount', 'contact_id', 'account_id', 'project_ids',
            'running_balance', 'currency_code', 'account_group', 'account_type',
        ]],
        'trial_balance' => ['sheet' => 'Trial Balance', 'report' => 'Trial Balance', 'headers' => [
            'Account ', 'Account Code ', 'Opening Balance ', 'Net Debit ', 'Net Credit ', 'Closing Balance ',
        ]],
    ];

    public static function exists(string $key): bool
    {
        return isset(self::TEMPLATES[$key]);
    }

    public static function headers(string $key): array
    {
        return self::TEMPLATES[$key]['headers'];
    }

    /** Zoho's report title: company line, report name, basis and a "From … To …" period that the user sets. */
    public static function title(string $key): ?string
    {
        $report = self::TEMPLATES[$key]['report'] ?? null;

        return $report ? "Company name\n{$report}\nBasis: Accrual\nFrom 01 Jan ".now()->format('Y').' To '.now()->format('d M Y') : null;
    }

    public function write(string $key, string $format): string
    {
        $template = self::TEMPLATES[$key];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($template['sheet']);

        $headerRow = 1;
        if ($title = self::title($key)) {
            $sheet->setCellValueExplicit('A1', $title, DataType::TYPE_STRING);
            $sheet->getStyle('A1')->getAlignment()->setWrapText(true);
            $headerRow = 2;
        }
        foreach ($template['headers'] as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValueExplicit($column.$headerRow, $header, DataType::TYPE_STRING);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->getStyle('A'.$headerRow.':'.Coordinate::stringFromColumnIndex(count($template['headers'])).$headerRow)->getFont()->setBold(true);
        $sheet->freezePane('A'.($headerRow + 1));

        $temporary = tempnam(sys_get_temp_dir(), 'zoho-export-template-');
        $format === 'csv' ? (new Csv($spreadsheet))->save($temporary) : (new Xlsx($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        return $temporary;
    }
}

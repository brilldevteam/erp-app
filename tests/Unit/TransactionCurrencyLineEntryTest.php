<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TransactionCurrencyLineEntryTest extends TestCase
{
    public function test_all_three_item_tables_show_the_selected_currency_symbol(): void
    {
        foreach ([
            'resources/js/pages/Sales/components/InvoiceItemsTable.tsx',
            'resources/js/pages/Purchase/components/InvoiceItemsTable.tsx',
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/components/QuotationItemsTable.tsx',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString('getCurrencySymbolForCode(currencyCode, usePage().props)', $source, $path);
            $this->assertStringContainsString("{t('Unit Price')} ({currencySymbol})", $source, $path);
            $this->assertStringContainsString('formatTransactionMoney(item.total_amount, currencyCode)', $source, $path);
        }
    }

    public function test_current_forms_submit_line_amounts_in_the_selected_transaction_currency(): void
    {
        foreach ([
            'resources/js/pages/Sales/Create.tsx',
            'resources/js/pages/Sales/Edit.tsx',
            'resources/js/pages/Purchase/Create.tsx',
            'resources/js/pages/Purchase/Edit.tsx',
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Create.tsx',
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Edit.tsx',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringNotContainsString('line_amounts_are_base_currency: true', $source, $path);
            $this->assertStringContainsString('amount={totals.total}', $source, $path);
        }
    }

    public function test_invoice_servers_accept_direct_transaction_amounts_and_keep_legacy_base_amount_requests(): void
    {
        foreach ([
            'app/Services/SalesInvoiceService.php',
            'app/Http/Controllers/SalesInvoiceController.php',
            'app/Http/Controllers/PurchaseInvoiceController.php',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString('line_amounts_are_base_currency', $source, $path);
            $this->assertStringContainsString('lineItemsFromBase', $source, $path);
            $this->assertStringContainsString('transactionValues(', $source, $path);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

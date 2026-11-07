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

    public function test_all_three_document_forms_keep_multi_currency_inside_the_details_card(): void
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
            $detailsCardStart = strpos($source, '<Card>');
            $detailsCardEnd = strpos($source, '</Card>', $detailsCardStart);
            $currencyFields = strpos($source, '<TransactionCurrencyFields');

            $this->assertNotFalse($detailsCardStart, $path);
            $this->assertNotFalse($detailsCardEnd, $path);
            $this->assertNotFalse($currencyFields, $path);
            $this->assertGreaterThan($detailsCardStart, $currencyFields, $path);
            $this->assertLessThan($detailsCardEnd, $currencyFields, $path);
            $this->assertStringContainsString(
                'className="mt-6 border-t pt-6"',
                substr($source, $detailsCardStart, $detailsCardEnd - $detailsCardStart),
                $path,
            );
        }
    }

    public function test_legacy_documents_without_base_snapshots_use_the_company_currency_when_edited(): void
    {
        foreach ([
            'resources/js/pages/Sales/Edit.tsx' => 'invoice',
            'resources/js/pages/Purchase/Edit.tsx' => 'invoice',
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Edit.tsx' => 'quotation',
        ] as $path => $document) {
            $source = $this->source($path);

            $this->assertStringContainsString("page.companyAllSetting?.defaultCurrency || 'USD'", $source, $path);
            $this->assertStringContainsString("{$document}.base_currency_code || companyBaseCurrency", $source, $path);
            $this->assertStringContainsString("{$document}.base_currency_code\n        ? String({$document}.currency_code || baseCurrency).toUpperCase()\n        : baseCurrency", $source, $path);
            $this->assertStringContainsString("initialCurrency === baseCurrency ? '1.00000000'", $source, $path);
        }
    }

    public function test_selecting_the_base_currency_closes_multi_currency_mode(): void
    {
        $source = $this->source('resources/js/components/transaction-currency-fields.tsx');

        $this->assertStringContainsString("page.companyAllSetting?.defaultCurrency || page.settings?.defaultCurrency || 'USD'", $source);
        $this->assertStringContainsString('const availableCurrencies = currencies.filter((currency) => currency.code !== baseCurrency);', $source);
        $this->assertStringContainsString('const [multiCurrencyEnabled, setMultiCurrencyEnabled] = useState(defaultEnabled);', $source);
        $this->assertStringContainsString("if (value === baseCurrency) {\n            onRateChange('1.00000000');\n            setMultiCurrencyEnabled(false);", $source);
    }

    public function test_multi_currency_control_is_an_accessible_theme_switch(): void
    {
        $source = $this->source('resources/js/components/transaction-currency-fields.tsx');

        $this->assertStringNotContainsString("@/components/ui/checkbox", $source);
        $this->assertStringContainsString('aria-pressed={multiCurrencyEnabled}', $source);
        $this->assertStringContainsString("onClick={() => toggleMultiCurrency(!multiCurrencyEnabled)}", $source);
        $this->assertStringContainsString("multiCurrencyEnabled ? 'bg-primary' : 'bg-muted-foreground/70'", $source);
        $this->assertStringContainsString("multiCurrencyEnabled ? 'translate-x-[22px]' : 'translate-x-0.5'", $source);
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

        return str_replace("\r\n", "\n", $source);
    }
}

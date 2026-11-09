<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SalesInvoiceListPageTest extends TestCase
{
    public function test_list_page_keeps_module_buttons_and_dual_currency_amounts(): void
    {
        $page = $this->source('resources/js/pages/Sales/Index.tsx');

        $this->assertStringContainsString("['all', 'All invoices'], ['product', 'Product'], ['service', 'Service'], ['overdue', 'Overdue']", $page);

        foreach (["'signatureBtn'", "'invoiceActionButtons'", "'xeroSyncInvoiceBtn'", "'salesInvoiceAlert'", '<DualCurrencyAmount', '<TooltipProvider>'] as $required) {
            $this->assertStringContainsString($required, $page);
        }
    }

    public function test_overdue_means_issued_unpaid_and_past_due(): void
    {
        $model = $this->source('app/Models/SalesInvoice.php');

        $this->assertStringContainsString("public const RECEIVABLE_STATUSES = ['posted', 'partial', 'overdue'];", $model);
        $this->assertStringContainsString("->where('balance_amount', '>', 0)", $model);
        $this->assertStringContainsString('$query->overdueReceivable();', $this->source('app/Http/Controllers/SalesInvoiceController.php'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

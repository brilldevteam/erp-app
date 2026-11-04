<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InvoiceRevertToDraftFeatureTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    public function test_revert_routes_and_permissions_are_separate_for_sales_and_purchase(): void
    {
        $routes = $this->source('routes/web.php');
        $migration = $this->source('database/migrations/2026_11_04_000002_add_invoice_revert_to_draft.php');

        $this->assertStringContainsString('sales-invoices/{salesInvoice}/revert-to-draft', $routes);
        $this->assertStringContainsString('purchase-invoices/{purchaseInvoice}/revert-to-draft', $routes);
        $this->assertStringContainsString('revert-sales-invoices-to-draft', $migration);
        $this->assertStringContainsString('revert-purchase-invoices-to-draft', $migration);
    }

    public function test_service_blocks_dependencies_and_unsafe_inventory_reversal(): void
    {
        $source = $this->source('app/Services/InvoiceReversionService.php');

        $this->assertStringContainsString("status !== 'posted'", $source);
        $this->assertStringContainsString('paymentAllocations()->exists()', $source);
        $this->assertStringContainsString("CreditNote::where('invoice_id'", $source);
        $this->assertStringContainsString("DebitNote::where('invoice_id'", $source);
        $this->assertStringContainsString('salesReturns()->exists()', $source);
        $this->assertStringContainsString('purchaseReturns()->exists()', $source);
        $this->assertStringContainsString('some received stock has already been consumed', $source);
    }

    public function test_reversion_is_transactional_and_records_a_reasoned_audit_entry(): void
    {
        $source = $this->source('app/Services/InvoiceReversionService.php');

        $this->assertGreaterThanOrEqual(2, substr_count($source, 'DB::transaction'));
        $this->assertStringContainsString("'action' => 'reverted_to_draft'", $source);
        $this->assertStringContainsString("'reason' => \$reason", $source);
        $this->assertStringContainsString("\$invoice->update(['status' => 'draft'])", $source);
    }

    public function test_journals_are_reversed_for_product_service_cogs_and_purchase_entries(): void
    {
        $source = $this->source('packages/workdo/Account/src/Services/JournalService.php');

        $this->assertStringContainsString("['sales_invoice', 'service_invoice', 'sales_invoice_cogs']", $source);
        $this->assertStringContainsString("'purchase_invoice' => ['purchase_invoice']", $source);
        $this->assertStringContainsString("'debit_amount' => \$item->credit_amount", $source);
        $this->assertStringContainsString("'credit_amount' => \$item->debit_amount", $source);
        $this->assertGreaterThanOrEqual(2, substr_count($source, "update(['status' => 'reversed'])"));
    }

    public function test_both_views_require_a_reason_before_reverting(): void
    {
        $dialog = $this->source('resources/js/components/revert-invoice-dialog.tsx');
        $sales = $this->source('resources/js/pages/Sales/View.tsx');
        $purchase = $this->source('resources/js/pages/Purchase/View.tsx');

        $this->assertStringContainsString('value.length < 5', $dialog);
        $this->assertStringContainsString('sales-invoices.revert-to-draft', $sales);
        $this->assertStringContainsString('purchase-invoices.revert-to-draft', $purchase);
        $this->assertStringContainsString('InvoiceStatusHistory', $sales);
        $this->assertStringContainsString('InvoiceStatusHistory', $purchase);
    }

    public function test_posting_and_reposting_are_recorded_in_status_history(): void
    {
        $sales = $this->source('app/Http/Controllers/SalesInvoiceController.php');
        $purchase = $this->source('app/Http/Controllers/PurchaseInvoiceController.php');

        foreach ([$sales, $purchase] as $source) {
            $this->assertStringContainsString("'from_status' => 'draft'", $source);
            $this->assertStringContainsString("'to_status' => 'posted'", $source);
            $this->assertStringContainsString("'action' => 'posted'", $source);
        }
    }
}

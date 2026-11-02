<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class LiveSecuritySmokeRegressionTest extends TestCase
{
    public function test_customer_and_vendor_dashboard_totals_are_party_scoped(): void
    {
        $source = $this->source('packages/workdo/Account/src/Http/Controllers/DashboardController.php');

        $this->assertStringContainsString("SalesInvoice::where('customer_id', \$user->id)", $source);
        $this->assertStringContainsString("PurchaseInvoice::where('vendor_id', \$user->id)", $source);
        $this->assertStringNotContainsString(
            "\$totalRevenues = Revenue::where('created_by', \$user->created_by)",
            $source
        );
        $this->assertStringNotContainsString(
            "\$totalExpenses = Expense::where('created_by', \$user->created_by)",
            $source
        );
    }

    public function test_new_purchase_invoices_use_the_shared_company_currency_and_do_not_auto_switch_for_vendor(): void
    {
        $source = $this->source('resources/js/pages/Purchase/Create.tsx');

        $this->assertStringContainsString(
            "const baseCurrency = companyAllSetting?.defaultCurrency || 'USD';",
            $source
        );
        $this->assertStringContainsString('currency_code: baseCurrency', $source);
        $this->assertStringContainsString('baseCurrencyCode={baseCurrency}', $source);
        $this->assertStringNotContainsString('settings?.defaultCurrency', $source);
        $this->assertStringNotContainsString('...(preferredCurrency ?', $source);
    }

    public function test_payment_forms_start_in_base_currency_and_show_allocation_errors(): void
    {
        foreach ([
            'packages/workdo/Account/src/Resources/js/Pages/CustomerPayments/Create.tsx',
            'packages/workdo/Account/src/Resources/js/Pages/VendorPayments/Create.tsx',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString('currency_code: baseCurrency', $source, $path);
            $this->assertStringContainsString('errors as Record<string, string>).allocations', $source, $path);
        }
    }

    public function test_created_invoices_redirect_with_success_when_follow_up_integration_fails(): void
    {
        $sales = $this->source('app/Http/Controllers/SalesInvoiceController.php');
        $purchases = $this->source('app/Http/Controllers/PurchaseInvoiceController.php');

        $this->assertStringContainsString("->route('sales-invoices.index')", $sales);
        $this->assertStringContainsString('The invoice was created, but a notification or integration could not be completed.', $sales);
        $this->assertStringContainsString("->route('purchase-invoices.index')", $purchases);
        $this->assertStringContainsString('The purchase invoice was created, but a notification or integration could not be completed.', $purchases);
    }

    public function test_credit_note_approval_redirects_to_fresh_details(): void
    {
        $controller = $this->source('packages/workdo/Account/src/Http/Controllers/CreditNoteController.php');
        $view = $this->source('packages/workdo/Account/src/Resources/js/Pages/CreditNotes/View.tsx');

        $this->assertStringContainsString("->route('account.credit-notes.show', \$creditNote)", $controller);
        $this->assertStringNotContainsString('router.reload();', $view);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

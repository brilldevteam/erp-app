<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuotationSalespersonFeatureTest extends TestCase
{
    public function test_quotation_forms_include_the_salesperson_selector_below_due_date(): void
    {
        foreach (['Create.tsx', 'Edit.tsx'] as $file) {
            $source = $this->source("packages/workdo/Quotation/src/Resources/js/Pages/Quotations/{$file}");
            $dueDatePosition = strpos($source, 'errors.due_date');
            $salespersonPosition = strpos($source, '<SalespersonField');

            $this->assertNotFalse($dueDatePosition, $file);
            $this->assertNotFalse($salespersonPosition, $file);
            $this->assertGreaterThan($dueDatePosition, $salespersonPosition, $file);
            $this->assertStringContainsString('salesperson_id:', $source, $file);
        }
    }

    public function test_salesperson_endpoints_and_company_scoped_validation_are_present(): void
    {
        $routes = $this->source('packages/workdo/Quotation/src/Routes/web.php');
        $controller = $this->source('packages/workdo/Quotation/src/Http/Controllers/QuotationSalespersonController.php');
        $model = $this->source('packages/workdo/Quotation/src/Models/QuotationSalesperson.php');

        $this->assertStringContainsString("name('quotation-salespersons.index')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.show')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.store')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.update')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.toggle')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.destroy')", $routes);
        $this->assertStringContainsString("where('created_by', creatorId())", $controller);
        $this->assertStringContainsString('$salesperson->created_by === creatorId()', $controller);
        $this->assertStringContainsString("protected \$table = 'quotation_salespersons'", $model);
    }

    public function test_salesperson_has_a_sales_submenu_and_management_page(): void
    {
        $menu = $this->source('packages/workdo/Quotation/src/Resources/js/menus/company-menu.ts');
        $page = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Salespersons/Index.tsx');
        $controller = $this->source('packages/workdo/Quotation/src/Http/Controllers/QuotationSalespersonController.php');

        $this->assertStringContainsString("title: t('Salesperson')", $menu);
        $this->assertStringContainsString("route('quotation-salespersons.index')", $menu);
        $this->assertStringContainsString("parent: 'sales'", $menu);
        $this->assertStringContainsString("t('New Salesperson')", $page);
        $this->assertStringContainsString("t('Search salespersons by name or email')", $page);
        $this->assertStringContainsString("['all', 'All Salespersons']", $page);
        $this->assertStringContainsString("['active', 'Active']", $page);
        $this->assertStringContainsString("['inactive', 'Inactive']", $page);
        $this->assertStringNotContainsString("Customer accounts", $page);
        $this->assertStringContainsString("t('Mark as Inactive')", $page);
        $this->assertStringContainsString("Inertia::render('Quotation/Quotations/Salespersons/Index'", $controller);
        $this->assertStringContainsString("Inertia::render('Quotation/Quotations/Salespersons/Show'", $controller);
        $this->assertStringContainsString("route('quotation-salespersons.show', person.id)", $page);
        $workspace = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Salespersons/Show.tsx');
        $this->assertStringContainsString("route('quotations.show', quotationId)", $workspace);
        $this->assertStringContainsString("route('sales-invoices.show', invoiceId)", $workspace);
        $this->assertStringContainsString("openInvoice(quotation.invoice!.id)", $workspace);
        $this->assertStringContainsString("openInvoice(payment.invoice_id!)", $workspace);
        $this->assertStringContainsString("['payments', 'Payments']", $workspace);
        $this->assertStringContainsString("t('Quoted value')", $workspace);
        $this->assertStringContainsString("t('Payments collected')", $workspace);
        $this->assertStringContainsString("t('Salesperson quotations')", $workspace);
        $this->assertStringNotContainsString('Department', $workspace);
        $this->assertStringNotContainsString('department', $workspace);
    }

    public function test_salesperson_is_required_for_quotation_create_and_update(): void
    {
        foreach (['StoreQuotationRequest.php', 'UpdateQuotationRequest.php'] as $file) {
            $request = $this->source("packages/workdo/Quotation/src/Http/Requests/{$file}");
            $this->assertStringContainsString("'salesperson_id' => ['required'", $request, $file);
            $this->assertStringContainsString("'salesperson_id.required'", $request, $file);
        }

        $field = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/components/SalespersonField.tsx');
        $this->assertStringContainsString('<Label htmlFor="salesperson_id" required>', $field);
        $this->assertStringNotContainsString('No Salesperson', $field);
    }

    public function test_create_quotation_details_use_two_rows_without_notes(): void
    {
        $source = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Create.tsx');
        $detailsStart = strpos($source, "{t('Quotation Details')}");
        $itemsStart = strpos($source, "{t('Quotation Items')}");

        $this->assertNotFalse($detailsStart);
        $this->assertNotFalse($itemsStart);

        $details = substr($source, $detailsStart, $itemsStart - $detailsStart);

        $this->assertStringContainsString('grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4', $details);
        $this->assertSame(1, substr_count($details, 'grid grid-cols-1'));
        $this->assertStringContainsString('htmlFor="payment_terms"', $details);
        $this->assertStringContainsString('htmlFor="subject"', $details);
        $this->assertStringNotContainsString('htmlFor="notes"', $details);
        $this->assertStringNotContainsString('Additional notes...', $details);
    }

    public function test_create_quotation_prefills_editable_notes_and_terms_from_the_template(): void
    {
        $page = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Create.tsx');
        $controller = $this->source('packages/workdo/Quotation/src/Http/Controllers/QuotationController.php');
        $request = $this->source('packages/workdo/Quotation/src/Http/Requests/StoreQuotationRequest.php');
        $model = $this->source('packages/workdo/Quotation/src/Models/SalesQuotation.php');
        $preview = $this->source('resources/js/components/document-templates/document-template-preview.tsx');

        $this->assertStringContainsString('terms?: string | null', $page);
        $this->assertStringContainsString('notes: defaultTemplate?.notes', $page);
        $this->assertStringContainsString('terms: defaultTemplate?.terms', $page);
        $this->assertStringContainsString('onValueChange={handleTemplateChange}', $page);
        $this->assertStringContainsString('htmlFor="notes"', $page);
        $this->assertStringContainsString('htmlFor="terms"', $page);
        $this->assertStringContainsString("'terms' => 'nullable|string'", $request);
        $this->assertStringContainsString('$quotation->terms', $controller);
        $this->assertStringContainsString("'terms',", $model);
        $this->assertStringContainsString('document.terms || template.terms', $preview);
    }

    public function test_multi_currency_switch_defaults_off_and_does_not_reverse_user_clicks(): void
    {
        $source = $this->source('resources/js/components/transaction-currency-fields.tsx');

        $this->assertStringContainsString('defaultEnabled = false', $source);
        $this->assertStringContainsString('useState(defaultEnabled)', $source);
        $this->assertStringNotContainsString('if (multiCurrencyEnabled) {', $source);
        $this->assertStringContainsString("left-0 top-0.5", $source);
        $this->assertStringContainsString('whitespace-nowrap', $source);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

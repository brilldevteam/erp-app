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

        $this->assertStringContainsString("name('quotation-salespersons.store')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.update')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.toggle')", $routes);
        $this->assertStringContainsString("name('quotation-salespersons.destroy')", $routes);
        $this->assertStringContainsString("where('created_by', creatorId())", $controller);
        $this->assertStringContainsString('$salesperson->created_by === creatorId()', $controller);
        $this->assertStringContainsString("protected \$table = 'quotation_salespersons'", $model);
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

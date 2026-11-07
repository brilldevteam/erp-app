<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuotationTypeSplitTest extends TestCase
{
    private const PAGES = 'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/';

    public function test_create_button_offers_product_and_service_quotations(): void
    {
        $index = $this->source(self::PAGES.'Index.tsx');

        $this->assertStringContainsString("route('quotations.create', { type: 'product' })", $index);
        $this->assertStringContainsString("route('quotations.create', { type: 'service' })", $index);
        $this->assertStringNotContainsString("route('quotations.create'))", $index);
    }

    public function test_item_list_is_limited_to_the_quotation_type(): void
    {
        $controller = $this->source('packages/workdo/Quotation/src/Http/Controllers/QuotationController.php');

        $this->assertStringContainsString("->when(\$catalogType === 'service', fn (\$query) => \$query->where('type', 'service'))", $controller);
        $this->assertStringContainsString("->when(\$catalogType === 'product', fn (\$query) => \$query->where('type', '!=', 'service'))", $controller);

        foreach (['Create.tsx', 'Edit.tsx'] as $file) {
            $this->assertStringContainsString('type: quotationType || undefined', $this->source(self::PAGES.$file));
        }
    }

    public function test_saved_lines_must_match_the_quotation_type(): void
    {
        foreach (['StoreQuotationRequest.php', 'UpdateQuotationRequest.php'] as $file) {
            $request = $this->source("packages/workdo/Quotation/src/Http/Requests/{$file}");

            $this->assertStringContainsString("->when(\$type === 'service', fn (\$items) => \$items->where('type', 'service'))", $request);
            $this->assertStringContainsString("->when(\$type === 'product', fn (\$items) => \$items->where('type', '!=', 'service'))", $request);
        }

        $store = $this->source('packages/workdo/Quotation/src/Http/Requests/StoreQuotationRequest.php');
        $this->assertStringContainsString("'type' => 'required|in:product,service'", $store);
    }

    public function test_list_page_has_customer_style_views_and_a_strict_overdue_rule(): void
    {
        $index = $this->source(self::PAGES.'Index.tsx');
        $model = $this->source('packages/workdo/Quotation/src/Models/SalesQuotation.php');

        $this->assertStringContainsString("['all', 'All quotations'], ['product', 'Product'], ['service', 'Service'], ['overdue', 'Overdue']", $index);
        $this->assertStringContainsString("public const AWAITING_STATUSES = ['draft', 'sent', 'expired'];", $model);
        $this->assertStringContainsString("->where('converted_to_invoice', false)", $model);
        $this->assertStringContainsString("->whereNull('type')", $model);
    }

    public function test_editing_a_quotation_keeps_its_terms(): void
    {
        $this->assertStringContainsString("terms: quotation.terms || ''", $this->source(self::PAGES.'Edit.tsx'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

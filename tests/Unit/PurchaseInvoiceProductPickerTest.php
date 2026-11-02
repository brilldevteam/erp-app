<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PurchaseInvoiceProductPickerTest extends TestCase
{
    public function test_purchase_invoice_create_and_edit_use_the_product_picker_in_purchase_mode(): void
    {
        foreach (['Create.tsx', 'Edit.tsx'] as $file) {
            $source = $this->source("resources/js/pages/Purchase/{$file}");

            $this->assertStringContainsString('<ProductPickerDialog', $source, $file);
            $this->assertStringContainsString('priceMode="purchase"', $source, $file);
            $this->assertStringContainsString('requireAvailableStock={false}', $source, $file);
            $this->assertStringContainsString('Number(product.purchase_price)', $source, $file);
            $this->assertStringContainsString("account_id: null", $source, $file);
            $this->assertStringContainsString("t('Add Product')", $source, $file);
        }
    }

    public function test_purchase_catalog_contains_picker_metadata_and_purchase_prices(): void
    {
        $controller = $this->source('app/Http/Controllers/PurchaseInvoiceController.php');

        $this->assertStringContainsString('private function purchaseProducts()', $controller);
        $this->assertStringContainsString("'purchase_price' => \$product->purchase_price", $controller);
        $this->assertStringContainsString("'productCatalog' => \$this->productCatalog()", $controller);
        $this->assertStringContainsString("ProductServiceCategory::where('created_by', creatorId())", $controller);
        $this->assertStringContainsString("ProductServiceUnit::where('created_by', creatorId())", $controller);
        $this->assertStringContainsString("ProductServiceTax::where('created_by', creatorId())", $controller);
    }

    public function test_shared_picker_keeps_sales_stock_rules_but_allows_purchase_replenishment(): void
    {
        $source = $this->source('resources/js/components/product-picker-dialog.tsx');

        $this->assertStringContainsString("priceMode?: 'sale' | 'purchase'", $source);
        $this->assertStringContainsString('requireAvailableStock?: boolean', $source);
        $this->assertStringContainsString("props.priceMode === 'purchase' ? product.purchase_price : product.sale_price", $source);
        $this->assertStringContainsString('props.requireAvailableStock !== false', $source);
    }

    public function test_shared_picker_has_no_category_filter_but_product_creation_keeps_category_assignment(): void
    {
        $source = $this->source('resources/js/components/product-picker-dialog.tsx');

        $this->assertStringNotContainsString("const [category, setCategory]", $source);
        $this->assertStringNotContainsString("t('All Categories')", $source);
        $this->assertStringContainsString("placeholder={t('Search by product name or SKU')}", $source);
        $this->assertStringContainsString('flex h-10 items-center gap-2 rounded-lg border', $source);
        $this->assertStringContainsString('h-4 w-4 shrink-0 text-muted-foreground', $source);
        $this->assertStringContainsString("<Field label={t('Category')}", $source);
        $this->assertStringContainsString('props.categories.map', $source);
    }

    public function test_sales_invoice_multi_currency_controls_have_balanced_vertical_spacing(): void
    {
        $create = $this->source('resources/js/pages/Sales/Create.tsx');
        $edit = $this->source('resources/js/pages/Sales/Edit.tsx');

        $this->assertStringContainsString('className="my-4 grid grid-cols-1 gap-4 py-3 md:grid-cols-2"', $create);
        $this->assertStringContainsString('<div className="py-3">', $edit);
    }

    public function test_purchase_multi_currency_controls_are_contained_in_expandable_cards(): void
    {
        foreach (['Create.tsx', 'Edit.tsx'] as $file) {
            $source = $this->source("resources/js/pages/Purchase/{$file}");
            $currencyFields = strpos($source, '<TransactionCurrencyFields');

            $this->assertNotFalse($currencyFields, $file);
            $cardStart = strrpos(substr($source, 0, $currencyFields), '<Card>');
            $cardEnd = strpos($source, '</Card>', $currencyFields);
            $this->assertNotFalse($cardStart, $file);
            $this->assertNotFalse($cardEnd, $file);
            $this->assertStringContainsString('<CardContent className="pt-6">', substr($source, $cardStart, $cardEnd - $cardStart), $file);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

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

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return $source;
    }
}

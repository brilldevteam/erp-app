<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ManualDocumentLineTest extends TestCase
{
    public function test_document_requests_accept_a_manual_name_instead_of_a_catalog_product(): void
    {
        foreach ([
            'app/Http/Requests/StoreSalesInvoiceRequest.php',
            'app/Http/Requests/UpdateSalesInvoiceRequest.php',
            'app/Http/Requests/StorePurchaseInvoiceRequest.php',
            'app/Http/Requests/UpdatePurchaseInvoiceRequest.php',
            'packages/workdo/Quotation/src/Http/Requests/StoreQuotationRequest.php',
            'packages/workdo/Quotation/src/Http/Requests/UpdateQuotationRequest.php',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString("'items.*.product_id' => ['nullable'", $source, $path);
            $this->assertStringContainsString("'items.*.item_name' => 'required_without:items.*.product_id", $source, $path);
        }
    }

    public function test_all_document_tables_bind_the_editable_item_combobox_to_manual_names(): void
    {
        foreach ([
            'resources/js/pages/Sales/components/InvoiceItemsTable.tsx',
            'resources/js/pages/Purchase/components/InvoiceItemsTable.tsx',
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/components/QuotationItemsTable.tsx',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString("manualValue={item.item_name || ''}", $source, $path);
            $this->assertStringContainsString('product_id: null, item_name: value', $source, $path);
        }

        $quotation = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/components/QuotationItemsTable.tsx');
        $this->assertStringContainsString("{t('Item Details')}", $quotation);
        $this->assertStringContainsString('onClearError?.(`items.${index}.item_name`)', $quotation);
    }

    public function test_manual_names_are_persisted_and_rendered_without_a_product(): void
    {
        foreach ([
            'app/Http/Controllers/SalesInvoiceController.php',
            'app/Http/Controllers/PurchaseInvoiceController.php',
            'packages/workdo/Quotation/src/Http/Controllers/QuotationController.php',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringContainsString("item_name", $source, $path);
            $this->assertStringContainsString("['product_id'] ?? null", $source, $path);
        }

        $template = $this->source('app/Services/DocumentTemplates/DocumentTemplateService.php');
        $this->assertStringContainsString('$item->product?->name ?: ($item->item_name', $template);

        $salesService = $this->source('app/Services/SalesInvoiceService.php');
        $this->assertStringContainsString('$item->product_id = $itemData[\'product_id\'] ?? null', $salesService);
        $this->assertStringContainsString('$item->item_name = $itemData[\'item_name\'] ?? null', $salesService);
        $this->assertStringContainsString('if (!empty($itemData[\'product_id\'])', $salesService);

        $salesView = $this->source('resources/js/pages/Sales/View.tsx');
        $this->assertStringContainsString('item.product?.name || item.item_name', $salesView);
    }

    public function test_product_dialog_and_item_results_each_have_only_one_scroll_container(): void
    {
        $dialog = $this->source('resources/js/components/product-picker-dialog.tsx');
        $selector = $this->source('resources/js/components/document-item-selector.tsx');

        $this->assertStringContainsString('flex max-h-[90vh] flex-col overflow-hidden', $dialog);
        $this->assertStringContainsString('overflow-y-auto overscroll-contain', $dialog);
        $this->assertStringContainsString('max-h-72 overflow-y-auto overscroll-contain', $selector);
    }

    public function test_item_selector_uses_one_stable_popover_trigger(): void
    {
        $selector = $this->source('resources/js/components/document-item-selector.tsx');

        $this->assertStringContainsString('<PopoverAnchor asChild>', $selector);
        $this->assertStringContainsString('onOpenAutoFocus={event => event.preventDefault()}', $selector);
        $this->assertStringContainsString('onFocus={() => setOpen(true)}', $selector);
        $this->assertStringContainsString('inputRef.current?.contains(event.target as Node)', $selector);
        $this->assertStringContainsString('aria-autocomplete="list"', $selector);
        $this->assertStringContainsString('autoComplete="off"', $selector);
        $this->assertStringNotContainsString('ChevronsUpDown', $selector);
    }

    public function test_quotation_lines_allow_tax_and_description_overrides(): void
    {
        $table = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/components/QuotationItemsTable.tsx');

        $this->assertStringContainsString('taxTypes?: TaxOption[]', $table);
        $this->assertStringContainsString('handleTaxSelect(index, tax)', $table);
        $this->assertStringContainsString('<TaxField', $table);
        $this->assertStringContainsString("updateItem(index, 'description', e.target.value)", $table);

        $taxField = $this->source('packages/workdo/Quotation/src/Resources/js/Pages/Quotations/components/TaxField.tsx');
        $this->assertStringContainsString("t('No Tax')", $taxField);
        $this->assertStringContainsString("t('Manage Taxes')", $taxField);
        $this->assertStringContainsString("{t('Save')}", $taxField);
        $this->assertStringContainsString('if (editing && value === String(editing.id)) onChange(tax);', $taxField);
        $this->assertStringNotContainsString("'Save and Select'</Button>", $taxField);
        $this->assertStringNotContainsString('if (isCreating) setOpen(false);', $taxField);
        $this->assertStringContainsString('const [optimisticTaxes, setOptimisticTaxes]', $taxField);
        $this->assertStringContainsString('availableTaxes.find(tax => String(tax.id) === value)', $taxField);
        $this->assertStringContainsString("route('product-service.taxes.store')", $taxField);
        $this->assertStringContainsString("route('product-service.taxes.destroy'", $taxField);

        foreach ([
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Create.tsx',
            'packages/workdo/Quotation/src/Resources/js/Pages/Quotations/Edit.tsx',
        ] as $path) {
            $this->assertStringContainsString('taxTypes={productCatalog?.taxes || []}', $this->source($path), $path);
        }

        foreach ([
            'packages/workdo/ProductService/src/Resources/js/Pages/Items/Create.tsx',
            'packages/workdo/ProductService/src/Resources/js/Pages/Items/Edit.tsx',
        ] as $path) {
            $this->assertStringContainsString("<SelectItem value=\"none\">{t('No Tax')}</SelectItem>", $this->source($path), $path);
        }

        $taxController = $this->source('packages/workdo/ProductService/src/Http/Controllers/TaxController.php');
        $this->assertStringContainsString('$request->expectsJson()', $taxController);
        $this->assertStringContainsString('abort_unless((int) $tax->created_by === (int) creatorId()', $taxController);

        $salesTable = $this->source('resources/js/pages/Sales/components/InvoiceItemsTable.tsx');
        $this->assertStringContainsString("if (!currentTax) return 'none'", $salesTable);
        $this->assertStringContainsString('if (currentTax.id) return String(currentTax.id)', $salesTable);
        $this->assertStringContainsString('<TaxField', $salesTable);
        $this->assertStringContainsString('canManage={canManageTaxes}', $salesTable);

        foreach ([
            'resources/js/pages/Sales/Create.tsx',
            'resources/js/pages/Sales/Edit.tsx',
        ] as $path) {
            $source = $this->source($path);
            $this->assertStringContainsString("canManageTaxes={auth.user.permissions?.includes('manage-product-service-taxes')", $source, $path);
            $this->assertStringContainsString("canDeleteTaxes={auth.user.permissions?.includes('delete-product-service-taxes')", $source, $path);
        }

        $purchaseTable = $this->source('resources/js/pages/Purchase/components/InvoiceItemsTable.tsx');
        $this->assertStringContainsString("if (!currentTax) return 'none'", $purchaseTable);
        $this->assertStringContainsString('if (currentTax.id) return String(currentTax.id)', $purchaseTable);
        $this->assertStringContainsString('handleTaxSelect(index, tax)', $purchaseTable);
        $this->assertStringContainsString('<TaxField', $purchaseTable);
        $this->assertStringContainsString('canManage={canManageTaxes}', $purchaseTable);

        foreach ([
            'resources/js/pages/Purchase/Create.tsx',
            'resources/js/pages/Purchase/Edit.tsx',
        ] as $path) {
            $source = $this->source($path);
            $this->assertStringContainsString('taxTypes={productCatalog?.taxes || []}', $source, $path);
            $this->assertStringContainsString("canManageTaxes={auth.user.permissions?.includes('manage-product-service-taxes')", $source, $path);
            $this->assertStringContainsString("canDeleteTaxes={auth.user.permissions?.includes('delete-product-service-taxes')", $source, $path);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.$path);
        $this->assertIsString($source);

        return str_replace("\r\n", "\n", $source);
    }
}

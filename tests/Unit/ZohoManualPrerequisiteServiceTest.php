<?php

namespace Tests\Unit;

use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use App\Services\ZohoMigration\ZohoManualPrerequisiteService;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class ZohoManualPrerequisiteServiceTest extends TestCase
{
    public function test_manual_templates_use_fixed_copy_paste_column_order(): void
    {
        $service = new ZohoManualPrerequisiteService(new ProductServiceDefinition());

        $this->assertSame(
            ['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price', 'description'],
            $service->template('items')['headers']
        );
        $this->assertSame(
            ['sku', 'warehouse', 'quantity'],
            $service->template('inventory_cutover')['headers']
        );
    }

    public function test_tab_separated_excel_rows_are_parsed_without_reordering_values(): void
    {
        $service = new ZohoManualPrerequisiteService(new ProductServiceDefinition());
        $parse = new ReflectionMethod($service, 'parse');

        [$headers, $rows] = $parse->invoke($service, "sku\twarehouse\tquantity\nSKU-1\tMain Warehouse\t25");

        $this->assertSame(['sku', 'warehouse', 'quantity'], $headers);
        $this->assertSame(2, $rows[0]['source_row_number']);
        $this->assertSame(['sku' => 'SKU-1', 'warehouse' => 'Main Warehouse', 'quantity' => '25'], $rows[0]['values']);
    }

    public function test_incomplete_pasted_rows_are_rejected_instead_of_shifting_columns(): void
    {
        $service = new ZohoManualPrerequisiteService(new ProductServiceDefinition());
        $parse = new ReflectionMethod($service, 'parse');

        $this->expectException(ValidationException::class);
        $parse->invoke($service, "sku\twarehouse\tquantity\nSKU-1\t25");
    }

    public function test_downloadable_manual_source_escapes_spreadsheet_formulas(): void
    {
        $service = new ZohoManualPrerequisiteService(new ProductServiceDefinition());
        $csv = new ReflectionMethod($service, 'csv');

        $content = $csv->invoke($service, ['sku', 'description'], [[
            'sku' => 'SKU-1',
            'description' => '=HYPERLINK("https://example.test")',
        ]]);

        $this->assertStringContainsString("'=HYPERLINK", $content);
    }
}

<?php

namespace Tests\Unit;

use App\Models\ZohoMigrationBatch;
use App\Services\BulkImport\Definitions\ProductServiceCategoryDefinition;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use App\Services\BulkImport\Definitions\ProductServiceTaxDefinition;
use App\Services\BulkImport\Definitions\ProductServiceUnitDefinition;
use App\Services\BulkImport\Definitions\WarehouseDefinition;
use App\Services\ZohoMigration\ZohoManualPrerequisiteService;
use App\Services\ZohoMigration\ZohoManualSpreadsheet;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class ZohoManualPrerequisiteServiceTest extends TestCase
{
    public function test_manual_templates_use_fixed_copy_paste_column_order(): void
    {
        $service = $this->service();

        $this->assertSame(['tax_name', 'rate'], $service->template('taxes')['headers']);

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
        $service = $this->service();
        $parse = new ReflectionMethod($service, 'parse');

        [$headers, $rows] = $parse->invoke($service, "sku\twarehouse\tquantity\nSKU-1\tMain Warehouse\t25");

        $this->assertSame(['sku', 'warehouse', 'quantity'], $headers);
        $this->assertSame(2, $rows[0]['source_row_number']);
        $this->assertSame(['sku' => 'SKU-1', 'warehouse' => 'Main Warehouse', 'quantity' => '25'], $rows[0]['values']);
    }

    public function test_an_empty_last_cell_on_the_last_pasted_row_is_kept(): void
    {
        $service = $this->service();
        $parse = new ReflectionMethod($service, 'parse');

        [, $rows] = $parse->invoke($service, "sku\twarehouse\tquantity\r\nSKU-1\tMain Warehouse\t\r\n");

        $this->assertSame(['sku' => 'SKU-1', 'warehouse' => 'Main Warehouse', 'quantity' => ''], $rows[0]['values']);
    }

    public function test_incomplete_pasted_rows_are_rejected_instead_of_shifting_columns(): void
    {
        $service = $this->service();
        $parse = new ReflectionMethod($service, 'parse');

        $this->expectException(ValidationException::class);
        $parse->invoke($service, "sku\twarehouse\tquantity\nSKU-1\t25");
    }

    public function test_downloadable_manual_source_escapes_spreadsheet_formulas(): void
    {
        $service = $this->service();
        $csv = new ReflectionMethod($service, 'csv');

        $content = $csv->invoke($service, ['sku', 'description'], [[
            'sku' => 'SKU-1',
            'description' => '=HYPERLINK("https://example.test")',
        ]]);

        $this->assertStringContainsString("'=HYPERLINK", $content);
    }

    public function test_products_cannot_be_completed_without_rows(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->completeWithoutRows(new ZohoMigrationBatch(), 'items', 1);
    }

    public function test_categories_and_units_share_one_typed_template(): void
    {
        $this->assertSame(['record_type', 'name', 'color'], $this->service()->template('categories_units')['headers']);
        $this->assertNull($this->service()->template('units'));
    }

    public function test_category_and_unit_rows_are_validated_per_record_type(): void
    {
        $service = $this->service();
        $batch = new ZohoMigrationBatch();
        $batch->tenant_id = 1;
        $batch->duplicate_strategy = 'link';
        $row = fn (int $number, string $type, string $name, string $color = '') => ['source_row_number' => $number, 'values' => ['record_type' => $type, 'name' => $name, 'color' => $color]];

        $prepared = (new ReflectionMethod($service, 'prepareSimpleList'))->invoke($service, [
            $row(2, 'category', 'General', '#10b981'),
            $row(3, 'unit', 'Pcs'),
            $row(4, 'unit', ' pcs '),
            $row(5, 'category', 'Pcs'),
            $row(6, 'unit', ''),
            $row(7, 'category', ''),
            $row(8, 'category', 'Bad colour', 'green'),
            $row(9, 'brand', 'Acme'),
        ], $batch, 'categories_units');

        $this->assertSame([], $prepared[0]['errors']);
        $this->assertSame(['name' => 'General', 'color' => '#10b981'], $prepared[0]['record']);
        $this->assertSame(['unit_name' => 'Pcs'], $prepared[1]['record']);
        $this->assertSame(['Unit name is duplicated in rows 3 and 4.'], $prepared[2]['errors']);
        $this->assertSame([], $prepared[3]['errors'], 'A category may share a name with a unit.');
        $this->assertSame(['Unit name is required.'], $prepared[4]['errors']);
        $this->assertSame(['Category name is required.'], $prepared[5]['errors']);
        $this->assertSame(['Color must be a hex value like #10b981.'], $prepared[6]['errors']);
        $this->assertSame(['Record type must be category or unit.'], $prepared[7]['errors']);
    }

    private function service(): ZohoManualPrerequisiteService
    {
        return new ZohoManualPrerequisiteService(new ProductServiceDefinition(), new ProductServiceTaxDefinition(), new ProductServiceCategoryDefinition(), new ProductServiceUnitDefinition(), new WarehouseDefinition(), new ZohoManualSpreadsheet());
    }
}

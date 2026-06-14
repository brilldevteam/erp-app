<?php

namespace Tests\Feature;

use App\Models\BulkImport;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use App\Services\BulkImport\SpreadsheetService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BulkImportSpreadsheetTest extends TestCase
{
    public function test_xlsx_template_contains_headers_example_and_instructions(): void
    {
        $path = app(SpreadsheetService::class)->template(new ProductServiceDefinition(), 'xlsx');
        $spreadsheet = IOFactory::load($path);

        $this->assertSame('name', $spreadsheet->getSheetByName('Import')->getCell('A1')->getValue());
        $this->assertSame('Example Product', $spreadsheet->getSheetByName('Import')->getCell('A2')->getValue());
        $this->assertNotNull($spreadsheet->getSheetByName('Instructions'));

        $spreadsheet->disconnectWorksheets();
        unlink($path);
    }

    public function test_reader_maps_rows_and_detects_formulas(): void
    {
        Storage::fake('local');
        $definition = new ProductServiceDefinition();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($definition->headers(), null, 'A1');
        $sheet->fromArray($definition->example(), null, 'A2');
        $sheet->setCellValue('G2', '=50+50');
        $this->storeSpreadsheet($spreadsheet, 'bulk-imports/test/source.xlsx');

        $mapping = [];
        foreach ($definition->headers() as $index => $field) {
            $mapping[$field] = 'column_'.($index + 1);
        }
        $rows = app(SpreadsheetService::class)->read(
            new BulkImport(['file_path' => 'bulk-imports/test/source.xlsx']),
            $definition,
            $mapping
        );

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['row_number']);
        $this->assertTrue($rows[0]['formula']);
        $this->assertContains('name', $rows[0]['data']['_mapped_fields']);
    }

    public function test_inspector_accepts_existing_spreadsheet_headers_and_auto_maps_aliases(): void
    {
        Storage::fake('local');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(['Item Name', 'Item Code'], null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray(['Example', 'SKU-1'], null, 'A2');
        $this->storeSpreadsheet($spreadsheet, 'bulk-imports/test/source.xlsx');

        $inspection = app(SpreadsheetService::class)->inspect(
            new BulkImport(['file_path' => 'bulk-imports/test/source.xlsx']),
            new ProductServiceDefinition()
        );

        $this->assertSame('column_1', $inspection['mapping']['name']);
        $this->assertSame('column_2', $inspection['mapping']['sku']);
        $this->assertSame('Example', $inspection['sample_rows'][0]['column_1']);
    }

    public function test_mapping_requires_only_identity_fields(): void
    {
        $definition = new ProductServiceDefinition();
        $headers = [
            ['key' => 'column_1'],
            ['key' => 'column_2'],
        ];

        $mapping = app(SpreadsheetService::class)->validateMapping([
            'name' => 'column_1',
            'sku' => 'column_2',
        ], $definition, $headers);

        $this->assertSame('column_1', $mapping['name']);
        $this->assertNull($mapping['category']);
    }

    private function storeSpreadsheet(Spreadsheet $spreadsheet, string $path): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'bulk-import-test-');
        (new Xlsx($spreadsheet))->save($temporary);
        Storage::disk('local')->put($path, file_get_contents($temporary));
        $spreadsheet->disconnectWorksheets();
        unlink($temporary);
    }
}

<?php

namespace Tests\Feature;

use App\Models\BulkImport;
use App\Services\BulkImport\Definitions\ProductServiceDefinition;
use App\Services\BulkImport\SpreadsheetService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
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

        $rows = app(SpreadsheetService::class)->read(
            new BulkImport(['file_path' => 'bulk-imports/test/source.xlsx']),
            $definition
        );

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['row_number']);
        $this->assertTrue($rows[0]['formula']);
    }

    public function test_reader_rejects_modified_headers(): void
    {
        Storage::fake('local');
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(['name', 'sku'], null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray(['Example', 'SKU-1'], null, 'A2');
        $this->storeSpreadsheet($spreadsheet, 'bulk-imports/test/source.xlsx');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid spreadsheet headers');

        app(SpreadsheetService::class)->read(
            new BulkImport(['file_path' => 'bulk-imports/test/source.xlsx']),
            new ProductServiceDefinition()
        );
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

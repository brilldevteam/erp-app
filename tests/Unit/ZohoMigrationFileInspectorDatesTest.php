<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoMigrationFileInspector;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use ReflectionMethod;
use Tests\TestCase;

class ZohoMigrationFileInspectorDatesTest extends TestCase
{
    public function test_date_cells_are_staged_as_readable_dates_and_numbers_stay_numbers(): void
    {
        $sheet = (new Spreadsheet())->getActiveSheet();
        $sheet->setCellValue('A2', 44747.5);
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode('yyyy-mm-dd hh:mm:ss');
        $sheet->setCellValue('B2', 44747);
        $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->setCellValue('C2', 2500.75);

        $inspector = app(ZohoMigrationFileInspector::class);
        $cellValue = new ReflectionMethod($inspector, 'cellValue');

        $this->assertSame('2022-07-05 12:00:00', $cellValue->invoke($inspector, $sheet, 1, 2, 44747.5));
        $this->assertSame('2022-07-05', $cellValue->invoke($inspector, $sheet, 2, 2, 44747));
        $this->assertSame(2500.75, $cellValue->invoke($inspector, $sheet, 3, 2, 2500.75));
    }
}

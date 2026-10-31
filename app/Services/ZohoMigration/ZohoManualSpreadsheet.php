<?php

namespace App\Services\ZohoMigration;

use App\Services\BulkImport\SpreadsheetService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Spreadsheet templates and reading for manual migration stages, laid out like the ERP bulk-import
 * templates (Import sheet, bold frozen header, Instructions sheet) but without its column-mapping step.
 */
class ZohoManualSpreadsheet
{
    /**
     * @param  array<string, array<int, array<int, mixed>>>  $extraSheets  XLSX-only sheets keyed by title.
     */
    public function template(array $headers, array $example, array $instructions, string $format, array $extraSheets = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');
        $sheet->fromArray($headers, null, 'A1');
        $exampleRows = isset($example[0]) && is_array($example[0]) ? $example : [$example];
        $sheet->fromArray($exampleRows, null, 'A2');
        $sheet->getStyle('A1:'.$sheet->getHighestDataColumn().'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', $sheet->getHighestDataColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($format === 'xlsx') {
            $notes = $spreadsheet->createSheet();
            $notes->setTitle('Instructions');
            $notes->fromArray([['Import Instructions'], ...array_map(fn ($instruction) => [$instruction], $instructions)]);
            $notes->getStyle('A1')->getFont()->setBold(true);
            $notes->getColumnDimension('A')->setWidth(100);

            foreach ($extraSheets as $title => $rows) {
                $extra = $spreadsheet->createSheet();
                $extra->setTitle($title);
                $extra->fromArray($rows, null, 'A1');
                $extra->getStyle('A1:'.$extra->getHighestDataColumn().'1')->getFont()->setBold(true);
                foreach (range('A', $extra->getHighestDataColumn()) as $column) {
                    $extra->getColumnDimension($column)->setAutoSize(true);
                }
            }
        }

        $temporary = tempnam(sys_get_temp_dir(), 'zoho-manual-template-');
        $format === 'csv'
            ? (new Csv($spreadsheet))->save($temporary)
            : (new Xlsx($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        return $temporary;
    }

    /**
     * Reads the first sheet of a CSV/XLSX file: its non-empty header labels by column index, and every
     * non-blank data row with its cell values and whether any cell held a formula.
     */
    public function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            if ($highestRow < 2) {
                throw new RuntimeException('The spreadsheet does not contain any data rows.');
            }
            if (($highestRow - 1) > SpreadsheetService::MAX_ROWS) {
                throw new RuntimeException('The spreadsheet exceeds the 10,000 row limit.');
            }

            $highestColumn = $sheet->getHighestDataColumn();
            $headers = [];
            foreach ($sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0] as $index => $header) {
                if (trim((string) $header) !== '') {
                    $headers[$index + 1] = trim((string) $header);
                }
            }

            $rows = [];
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $values = [];
                $formula = false;
                foreach (array_keys($headers) as $column) {
                    $cell = $sheet->getCell([$column, $rowNumber]);
                    $formula = $formula || $cell->isFormula();
                    $values[$column] = $cell->getCalculatedValue();
                }
                if (array_filter($values, fn ($value) => $value !== null && trim((string) $value) !== '')) {
                    $rows[] = ['row_number' => $rowNumber, 'values' => $values, 'formula' => $formula];
                }
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}

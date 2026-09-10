<?php

namespace Workdo\DoubleEntry\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AccountingReportExcelExportService
{
    public function create(string $title, array $metadata, array $headers, array $rows, array $numericColumns = []): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $title), 0, 31) ?: 'Report');
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($headers)));

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("A1:{$lastColumn}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $rowNumber = 2;
        foreach ($metadata as $label => $value) {
            $sheet->setCellValue("A{$rowNumber}", $label);
            $sheet->setCellValue("B{$rowNumber}", $value);
            $sheet->getStyle("A{$rowNumber}")->getFont()->setBold(true);
            $rowNumber++;
        }
        $rowNumber++;
        $headerRow = $rowNumber;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF111827');

        foreach ($rows as $row) {
            $rowNumber++;
            $sheet->fromArray(array_values($row), null, "A{$rowNumber}");
        }

        foreach ($numericColumns as $column) {
            $sheet->getStyle("{$column}".($headerRow + 1).":{$column}{$rowNumber}")
                ->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("{$column}".($headerRow + 1).":{$column}{$rowNumber}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$rowNumber}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$rowNumber}")->getAlignment()->setWrapText(true);
        foreach (range(1, count($headers)) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($column)->setWidth(in_array($column, $numericColumns, true) ? 18 : 28);
        }
        $sheet->freezePane('A'.($headerRow + 1));
        if ($rows !== []) {
            $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$rowNumber}");
        }

        $temporary = tempnam(sys_get_temp_dir(), 'accounting-report-');
        (new Xlsx($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        return $temporary;
    }
}

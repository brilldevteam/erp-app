<?php

namespace Workdo\DoubleEntry\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Workdo\Account\Models\ChartOfAccount;

class GeneralLedgerExcelExportService
{
    public function create(array $data, ChartOfAccount $account, array $filters): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('General Ledger');

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', __('General Ledger'));
        $sheet->setCellValue('A2', __('Account'));
        $sheet->setCellValue('B2', $account->account_code.' - '.$account->account_name);
        $sheet->setCellValue('A3', __('Period'));
        $sheet->setCellValue('B3', ($filters['from_date'] ?? '').' to '.($filters['to_date'] ?? ''));
        $sheet->fromArray([
            __('Date'),
            __('Description'),
            __('Reference'),
            __('Debit'),
            __('Credit'),
            __('Balance'),
        ], null, 'A5');

        $row = 6;
        $sheet->setCellValue("B{$row}", __('Opening Balance'));
        $sheet->setCellValue("F{$row}", (float) ($data['opening_balance'] ?? 0));
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        $row++;

        foreach ($data['transactions'] ?? [] as $transaction) {
            $reference = trim(implode(' #', array_filter([
                $transaction['reference_type'] ?? null,
                $transaction['reference_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== '')));

            $sheet->fromArray([
                $transaction['date'] ?? '',
                $transaction['description'] ?? '',
                $reference,
                (float) ($transaction['debit'] ?? 0),
                (float) ($transaction['credit'] ?? 0),
                (float) ($transaction['balance'] ?? 0),
            ], null, "A{$row}");
            $row++;
        }

        $sheet->setCellValue("B{$row}", __('Closing Balance'));
        $sheet->setCellValue("F{$row}", (float) ($data['closing_balance'] ?? 0));
        $sheet->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);

        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:F5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A5:F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF111827');
        $sheet->getStyle("D6:F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("D6:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A5:F{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle("B6:C{$row}")->getAlignment()->setWrapText(true);
        $sheet->getColumnDimension('A')->setWidth(14);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(28);
        foreach (['D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(18);
        }
        $sheet->freezePane('A6');
        $sheet->setAutoFilter("A5:F".max(5, $row - 1));

        $temporary = tempnam(sys_get_temp_dir(), 'general-ledger-');
        (new Xlsx($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        return $temporary;
    }
}

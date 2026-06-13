<?php

namespace App\Services\BulkImport;

use App\Models\BulkImport;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class SpreadsheetService
{
    public const MAX_ROWS = 10000;

    public function read(BulkImport $import, EntityDefinition $definition): array
    {
        $path = Storage::disk('local')->path($import->file_path);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        if ($highestRow < 2) {
            throw new RuntimeException('The spreadsheet does not contain any data rows.');
        }
        if (($highestRow - 1) > self::MAX_ROWS) {
            throw new RuntimeException('The spreadsheet exceeds the 10,000 row limit.');
        }

        $highestColumn = $sheet->getHighestDataColumn();
        $rawHeaders = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0];
        $headers = array_map(fn ($header) => $this->normalizeHeader($header), $rawHeaders);
        $expected = $definition->headers();

        if ($headers !== $expected) {
            $missing = array_diff($expected, $headers);
            $extra = array_diff($headers, $expected);
            $parts = [];
            if ($missing) $parts[] = 'missing: '.implode(', ', $missing);
            if ($extra) $parts[] = 'unexpected: '.implode(', ', $extra);
            if (!$parts) $parts[] = 'columns are not in the template order';
            throw new RuntimeException('Invalid spreadsheet headers ('.implode('; ', $parts).').');
        }

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false)[0];
            if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $data = [];
            $formula = false;
            foreach ($headers as $index => $header) {
                $cell = $sheet->getCell([$index + 1, $rowNumber]);
                if ($cell->isFormula()) {
                    $formula = true;
                }
                $data[$header] = $values[$index] ?? null;
            }
            $rows[] = ['row_number' => $rowNumber, 'data' => $data, 'formula' => $formula];
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    public function template(EntityDefinition $definition, string $format): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import');
        $sheet->fromArray($definition->headers(), null, 'A1');
        $sheet->fromArray($definition->example(), null, 'A2');
        $sheet->getStyle('A1:'.$sheet->getHighestDataColumn().'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', $sheet->getHighestDataColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        if ($format === 'xlsx') {
            $notes = $spreadsheet->createSheet();
            $notes->setTitle('Instructions');
            $notes->fromArray([
                ['Bulk Import Instructions'],
                ['Do not rename, reorder, add, or remove template columns.'],
                ['Delete the example row before importing your own data.'],
                ['Reference names must already exist in your company.'],
                ['Files may contain at most '.self::MAX_ROWS.' data rows.'],
                ['Spreadsheet formulas are not accepted.'],
                ...array_map(fn ($instruction) => [$instruction], $definition->instructions()),
            ]);
            $notes->getStyle('A1')->getFont()->setBold(true);
            $notes->getColumnDimension('A')->setWidth(100);
        }

        $temporary = tempnam(sys_get_temp_dir(), 'bulk-import-template-');
        $format === 'csv'
            ? (new Csv($spreadsheet))->save($temporary)
            : (new Xlsx($spreadsheet))->save($temporary);
        $spreadsheet->disconnectWorksheets();

        return $temporary;
    }

    public function writeErrorReport(array $rows, array $headers, string $path): void
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['row_number', ...$headers, 'errors', 'result']);
        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['row_number'],
                ...array_map(fn ($header) => $row['data'][$header] ?? '', $headers),
                implode(' | ', $row['errors'] ?? []),
                $row['result'] ?? 'invalid',
            ]);
        }
        rewind($stream);
        Storage::disk('local')->put($path, stream_get_contents($stream));
        fclose($stream);
    }

    private function normalizeHeader(mixed $header): string
    {
        $header = strtolower(trim((string) $header));

        return preg_replace('/[^a-z0-9]+/', '_', $header);
    }
}

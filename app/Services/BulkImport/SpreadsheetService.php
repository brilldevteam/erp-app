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

    public function inspect(BulkImport $import, EntityDefinition $definition): array
    {
        $spreadsheet = $this->load($import);
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
        $headers = [];
        foreach ($rawHeaders as $index => $header) {
            $label = trim((string) $header);
            if ($label === '') {
                continue;
            }
            $samples = [];
            for ($row = 2; $row <= min($highestRow, 5); $row++) {
                $value = $sheet->getCell([$index + 1, $row])->getCalculatedValue();
                if ($value !== null && $value !== '') {
                    $samples[] = (string) $value;
                }
            }
            $headers[] = [
                'key' => 'column_'.($index + 1),
                'index' => $index + 1,
                'label' => $label,
                'normalized' => $this->normalizeHeader($label),
                'samples' => array_slice($samples, 0, 3),
            ];
        }

        if (!$headers) {
            throw new RuntimeException('The spreadsheet header row is empty.');
        }

        $mapping = $this->autoMapping($headers, $definition);
        $rows = [];
        for ($rowNumber = 2; $rowNumber <= min($highestRow, 6); $rowNumber++) {
            $values = [];
            foreach ($headers as $header) {
                $values[$header['key']] = $sheet->getCell([$header['index'], $rowNumber])->getCalculatedValue();
            }
            if (array_filter($values, fn ($value) => $value !== null && $value !== '')) {
                $rows[] = $values;
            }
        }
        $spreadsheet->disconnectWorksheets();

        return [
            'headers' => $headers,
            'mapping' => $mapping,
            'fields' => $this->fieldMetadata($definition),
            'sample_rows' => $rows,
        ];
    }

    public function read(BulkImport $import, EntityDefinition $definition, array $mapping): array
    {
        $spreadsheet = $this->load($import);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $rawHeaders = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0];
        $columns = [];
        foreach ($rawHeaders as $index => $header) {
            if (trim((string) $header) !== '') {
                $columns['column_'.($index + 1)] = $index + 1;
            }
        }

        $rows = [];
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false)[0];
            if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
                continue;
            }

            $data = [];
            $formula = false;
            foreach ($definition->headers() as $field) {
                $source = $mapping[$field] ?? null;
                $column = $source ? ($columns[$source] ?? null) : null;
                if (!$column) {
                    $data[$field] = null;
                    continue;
                }
                $cell = $sheet->getCell([$column, $rowNumber]);
                if ($cell->isFormula()) {
                    $formula = true;
                }
                $data[$field] = $cell->getCalculatedValue();
            }
            $data['_mapped_fields'] = array_keys(array_filter(
                $mapping,
                fn ($source) => $source !== null && $source !== ''
            ));
            $rows[] = [
                'row_number' => $rowNumber,
                'data' => $definition->prepare($data),
                'formula' => $formula,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    public function validateMapping(array $mapping, EntityDefinition $definition, array $sourceHeaders): array
    {
        $allowedSources = array_column($sourceHeaders, 'key');
        $clean = [];
        foreach ($definition->headers() as $field) {
            $source = $mapping[$field] ?? null;
            $clean[$field] = in_array($source, $allowedSources, true) ? $source : null;
        }

        $missing = array_values(array_filter(
            $definition->requiredFields(),
            fn ($field) => empty($clean[$field])
        ));
        if ($missing) {
            throw new RuntimeException('Map the required fields: '.implode(', ', $missing).'.');
        }

        return $clean;
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
                ['You may use this template or upload an existing file and map its columns.'],
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

    private function load(BulkImport $import): Spreadsheet
    {
        $path = Storage::disk('local')->path($import->file_path);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);

        return $reader->load($path);
    }

    private function autoMapping(array $headers, EntityDefinition $definition): array
    {
        $lookup = [];
        foreach ($headers as $header) {
            $lookup[$header['normalized']] = $header['key'];
        }

        $mapping = [];
        foreach ($definition->headers() as $field) {
            $aliases = [$field, ...($definition->aliases()[$field] ?? [])];
            $mapping[$field] = null;
            foreach ($aliases as $alias) {
                $normalized = $this->normalizeHeader($alias);
                if (isset($lookup[$normalized])) {
                    $mapping[$field] = $lookup[$normalized];
                    break;
                }
            }
        }

        return $mapping;
    }

    private function fieldMetadata(EntityDefinition $definition): array
    {
        return array_map(fn ($field) => [
            'key' => $field,
            'label' => ucwords(str_replace('_', ' ', $field)),
            'required' => in_array($field, $definition->requiredFields(), true),
        ], $definition->headers());
    }
}

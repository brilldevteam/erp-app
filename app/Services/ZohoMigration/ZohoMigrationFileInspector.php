<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStagedRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class ZohoMigrationFileInspector
{
    public const MAX_ROWS = 10000;

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly ZohoStageCatalog $stages,
    )
    {
    }

    public function inspectAndStage(ZohoMigrationFile $file): void
    {
        $absolutePath = Storage::disk($file->disk)->path($file->path);
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();

        if ($highestRow < 2) {
            throw new RuntimeException('The spreadsheet does not contain data rows.');
        }
        if (($highestRow - 1) > self::MAX_ROWS) {
            throw new RuntimeException('The spreadsheet exceeds the 10,000 row limit. Split only at complete-document boundaries.');
        }

        $readHeaders = fn (int $row) => array_map(
            fn ($value) => trim((string) $value),
            $sheet->rangeToArray("A{$row}:{$highestColumn}{$row}", null, true, false)[0]
        );
        $headerRow = 1;
        $reportTitle = null;
        $headers = $readHeaders(1);
        // Zoho reports (Detailed General Ledger, Account Transactions) put a one-cell report title above the headers.
        if (count(array_filter($headers, fn ($value) => $value !== '')) === 1 && $highestRow > 2) {
            $headerRow = 2;
            $reportTitle = trim((string) current(array_filter($headers, fn ($value) => $value !== '')));
            $headers = $readHeaders(2);
        }
        if (!array_filter($headers, fn ($value) => $value !== '')) {
            throw new RuntimeException('The spreadsheet header row is empty.');
        }

        $detection = $this->profiles->detect($headers);
        if (!$detection['profile_key']) {
            $message = $detection['ambiguous']
                ? 'The Zoho export profile is ambiguous and requires review.'
                : 'No supported Zoho export profile matches these headers.';
            throw new RuntimeException($message);
        }
        $stage = $this->stages->find((string) $file->stage_key);
        if (!$stage || !in_array($detection['dataset_type'], $stage['datasets'], true)) {
            throw new RuntimeException('This file was detected as '.str_replace('_', ' ', (string) $detection['dataset_type']).' and does not belong in the selected stage.');
        }

        $rows = [];
        for ($rowNumber = $headerRow + 1; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false)[0];
            if (!array_filter($values, fn ($value) => $value !== null && $value !== '')) {
                continue;
            }

            $original = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $original[$header] = $this->cellValue($sheet, $index + 1, $rowNumber, $values[$index] ?? null);
                }
            }
            $rows[] = [
                'batch_id' => $file->batch_id,
                'file_id' => $file->id,
                'tenant_id' => $file->tenant_id,
                'sheet_name' => $sheet->getTitle(),
                'source_row_number' => $rowNumber,
                'original_values' => $original,
                'status' => 'staged',
            ];
        }
        $spreadsheet->disconnectWorksheets();

        DB::transaction(function () use ($file, $headers, $detection, $rows, $stage, $reportTitle): void {
            $activeFile = ZohoMigrationFile::lockForUpdate()->findOrFail($file->id);
            if ($activeFile->status === 'superseded') {
                return;
            }
            if ($stage['multi_source'] ?? false) {
                ZohoMigrationFile::where('batch_id', $file->batch_id)->where('stage_key', $file->stage_key)
                    ->where('dataset_type', $detection['dataset_type'])->whereKeyNot($file->id)
                    ->whereIn('status', ['uploaded', 'detecting', 'staged', 'review_required', 'failed'])
                    ->update(['status' => 'superseded']);
            }

            $activeFile->stagedRows()->delete();
            $now = now();
            foreach (array_chunk($rows, 500) as $chunk) {
                ZohoMigrationStagedRow::insert(array_map(fn (array $row) => [
                    ...$row,
                    'original_values' => json_encode($row['original_values'], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
            $activeFile->update([
                'status' => 'staged',
                'dataset_type' => $detection['dataset_type'],
                'profile_key' => $detection['profile_key'],
                'detection_confidence' => $detection['confidence'],
                'headers' => $headers,
                'report_title' => $reportTitle,
                'row_count' => count($rows),
                'failure_message' => null,
            ]);
            // Still processing: the inspection job validates next, and the page keeps refreshing until it sets the result.
            $file->batch->stages()->where('stage_key', $file->stage_key)->update([
                'status' => 'processing',
                'accepted_count' => count($rows),
                'rejected_count' => 0,
                'warning_count' => 0,
                'summary' => json_encode([
                    'dataset_type' => $detection['dataset_type'],
                    'profile_key' => $detection['profile_key'],
                    'file_id' => $file->id,
                ]),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * Date cells are stored as Excel serial numbers; keep them as readable ISO dates (with time when present).
     */
    private function cellValue(Worksheet $sheet, int $column, int $row, mixed $value): mixed
    {
        if (!is_int($value) && !is_float($value)) {
            return $value;
        }
        if (!Date::isDateTime($sheet->getCell([$column, $row]), $value)) {
            return $value;
        }

        $date = Date::excelToDateTimeObject($value);

        return $date->format(fmod((float) $value, 1.0) === 0.0 ? 'Y-m-d' : 'Y-m-d H:i:s');
    }
}

<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStagedRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

        $headers = array_map(
            fn ($value) => trim((string) $value),
            $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false)[0]
        );
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
        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $values = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, false, false)[0];
            if (!array_filter($values, fn ($value) => $value !== null && $value !== '')) {
                continue;
            }

            $original = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $original[$header] = $values[$index] ?? null;
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

        DB::transaction(function () use ($file, $headers, $detection, $rows): void {
            $activeFile = ZohoMigrationFile::lockForUpdate()->findOrFail($file->id);
            if ($activeFile->status === 'superseded') {
                return;
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
                'row_count' => count($rows),
                'failure_message' => null,
            ]);
            $file->batch->stages()->where('stage_key', $file->stage_key)->update([
                'status' => 'review_required',
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
}

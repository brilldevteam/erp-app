<?php

namespace App\Jobs;

use App\Models\BulkImport;
use App\Services\BulkImport\BulkImportRegistry;
use App\Services\BulkImport\SpreadsheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ValidateBulkImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $importId) {}

    public function handle(BulkImportRegistry $registry, SpreadsheetService $spreadsheets): void
    {
        $import = BulkImport::findOrFail($this->importId);
        $import->update(['status' => 'validating', 'failure_message' => null]);

        try {
            $definition = $registry->get($import->entity_type);
            $rows = $spreadsheets->read($import, $definition);
            $seen = [];
            $invalid = [];
            $valid = 0;
            $duplicates = 0;
            $new = 0;

            foreach ($rows as &$row) {
                $errors = $row['formula'] ? ['Spreadsheet formulas are not allowed.'] : [];
                $errors = [...$errors, ...$definition->validate($row['data'], $import->tenant_id)];
                $identity = $definition->identity($row['data']);

                if ($identity === '') {
                    $errors[] = 'Duplicate key is required.';
                } elseif (isset($seen[$identity])) {
                    $errors[] = 'Duplicate key appears more than once in this file.';
                }
                $seen[$identity] = true;

                $row['duplicate'] = !$errors && $definition->duplicate($row['data'], $import->tenant_id);
                $row['errors'] = array_values(array_unique($errors));
                unset($row['formula']);

                if ($row['errors']) {
                    $invalid[] = $row;
                } else {
                    $valid++;
                    $row['duplicate'] ? $duplicates++ : $new++;
                }
            }
            unset($row);

            $directory = dirname($import->file_path);
            $previewPath = "{$directory}/preview.json";
            $errorPath = $invalid ? "{$directory}/validation-errors.csv" : null;
            Storage::disk('local')->put($previewPath, json_encode($rows, JSON_UNESCAPED_UNICODE));
            if ($errorPath) {
                $spreadsheets->writeErrorReport($invalid, $definition->headers(), $errorPath);
            }

            $import->update([
                'status' => 'ready',
                'preview_path' => $previewPath,
                'error_path' => $errorPath,
                'total_rows' => count($rows),
                'valid_rows' => $valid,
                'invalid_rows' => count($invalid),
                'duplicate_rows' => $duplicates,
                'new_rows' => $new,
                'validated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
            ]);
        }
    }
}

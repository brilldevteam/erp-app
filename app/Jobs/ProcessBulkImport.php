<?php

namespace App\Jobs;

use App\Models\BulkImport;
use App\Models\User;
use App\Services\BulkImport\BulkImportRegistry;
use App\Services\BulkImport\SpreadsheetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessBulkImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(public int $importId) {}

    public function handle(BulkImportRegistry $registry, SpreadsheetService $spreadsheets): void
    {
        $import = BulkImport::findOrFail($this->importId);
        $definition = $registry->get($import->entity_type);
        $rows = json_decode(Storage::disk('local')->get($import->preview_path), true) ?: [];
        $errorRows = array_values(array_filter($rows, fn ($row) => !empty($row['errors'])));
        $counts = [
            'processed_rows' => 0,
            'imported_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
        ];
        Auth::setUser(User::findOrFail($import->creator_id));

        try {
            $import->update(['status' => 'importing', 'failure_message' => null]);

            foreach (array_chunk($rows, 100) as $chunk) {
                foreach ($chunk as $row) {
                    if (!empty($row['errors'])) {
                        continue;
                    }

                    try {
                        $lockKey = 'bulk-import:'.$import->tenant_id.':'.$import->entity_type.':'
                            .hash('sha256', $definition->identity($row['data']));
                        $result = Cache::lock($lockKey, 30)->block(10, fn () => DB::transaction(
                            fn () => $definition->import(
                                $row['data'],
                                $import->strategy,
                                $import->tenant_id,
                                $import->creator_id
                            )
                        ));
                        $counts["{$result}_rows"]++;
                    } catch (Throwable $exception) {
                        $row['errors'] = [$exception->getMessage()];
                        $row['result'] = 'failed';
                        $errorRows[] = $row;
                    }

                    $counts['processed_rows']++;
                }
                $import->update($counts);
            }

            if ($errorRows) {
                $path = dirname($import->file_path).'/import-errors.csv';
                $spreadsheets->writeErrorReport($errorRows, $definition->headers(), $path);
                $import->error_path = $path;
            }

            $import->fill($counts);
            $import->status = 'completed';
            $import->completed_at = now();
            $import->save();
        } finally {
            Auth::forgetUser();
        }
    }

    public function failed(Throwable $exception): void
    {
        BulkImport::whereKey($this->importId)->update([
            'status' => 'failed',
            'failure_message' => $exception->getMessage(),
        ]);
    }
}

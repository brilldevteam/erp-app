<?php

namespace App\Jobs;

use App\Models\BulkImport;
use App\Models\User;
use App\Services\BulkImport\AllowsRepeatedIdentity;
use App\Services\BulkImport\BulkImportRegistry;
use App\Services\BulkImport\EntityDefinition;
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

            if ($definition instanceof AllowsRepeatedIdentity) {
                $this->processRepeatedIdentityRows($import, $definition, $rows, $counts, $errorRows);
            } else {
                $this->processSingleIdentityRows($import, $definition, $rows, $counts, $errorRows);
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

    private function processSingleIdentityRows(
        BulkImport $import,
        EntityDefinition $definition,
        array $rows,
        array &$counts,
        array &$errorRows
    ): void {
        foreach (array_chunk($rows, 100) as $chunk) {
            foreach ($chunk as $row) {
                if (!empty($row['errors'])) {
                    continue;
                }

                try {
                    $lockKey = $this->lockKey($import, $definition, $row);
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
    }

    private function processRepeatedIdentityRows(
        BulkImport $import,
        EntityDefinition $definition,
        array $rows,
        array &$counts,
        array &$errorRows
    ): void {
        $groups = [];

        foreach ($rows as $row) {
            if (!empty($row['errors'])) {
                continue;
            }

            $groups[$definition->identity($row['data'])][] = $row;
        }

        foreach (array_chunk($groups, 100, true) as $chunk) {
            foreach ($chunk as $group) {
                try {
                    $lockKey = $this->lockKey($import, $definition, $group[0]);
                    $results = Cache::lock($lockKey, 30)->block(10, fn () => DB::transaction(function () use ($definition, $group, $import): array {
                        $results = [];

                        foreach ($group as $row) {
                            $results[] = $definition->import(
                                $row['data'],
                                $import->strategy,
                                $import->tenant_id,
                                $import->creator_id
                            );
                        }

                        return $results;
                    }));

                    foreach ($results as $result) {
                        $counts["{$result}_rows"]++;
                    }
                } catch (Throwable $exception) {
                    foreach ($group as $row) {
                        $row['errors'] = [$exception->getMessage()];
                        $row['result'] = 'failed';
                        $errorRows[] = $row;
                    }
                }

                $counts['processed_rows'] += count($group);
            }

            $import->update($counts);
        }
    }

    private function lockKey(BulkImport $import, EntityDefinition $definition, array $row): string
    {
        return 'bulk-import:'.$import->tenant_id.':'.$import->entity_type.':'
            .hash('sha256', $definition->identity($row['data']));
    }

    public function failed(Throwable $exception): void
    {
        BulkImport::whereKey($this->importId)->update([
            'status' => 'failed',
            'failure_message' => $exception->getMessage(),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\ZohoMigrationFile;
use App\Services\ZohoMigration\ZohoMigrationFileInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InspectZohoMigrationFile implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public readonly int $fileId)
    {
        $this->onQueue('imports');
    }

    public function handle(ZohoMigrationFileInspector $inspector): void
    {
        $file = ZohoMigrationFile::findOrFail($this->fileId);

        try {
            $file->update(['status' => 'detecting', 'failure_message' => null]);
            $inspector->inspectAndStage($file);
        } catch (\Throwable $exception) {
            Log::warning('Zoho migration file inspection failed.', [
                'file_id' => $file->id,
                'batch_id' => $file->batch_id,
                'exception' => $exception::class,
            ]);
            $file->update([
                'status' => 'review_required',
                'failure_message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'The source file could not be inspected. Review the file format and try again.',
            ]);
            $file->batch->stages()->where('stage_key', $file->stage_key)->update([
                'status' => 'failed',
                'rejected_count' => $file->row_count,
                'updated_at' => now(),
            ]);
        }
    }
}

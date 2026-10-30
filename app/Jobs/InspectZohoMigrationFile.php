<?php

namespace App\Jobs;

use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationFile;
use App\Services\ZohoMigration\ZohoMigrationFileInspector;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoStageCatalog;
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

    public function handle(ZohoMigrationFileInspector $inspector, ZohoMigrationValidator $validator, ZohoStageCatalog $stages): void
    {
        $file = ZohoMigrationFile::findOrFail($this->fileId);
        if ($file->status === 'superseded') {
            return;
        }

        try {
            $file->update(['status' => 'detecting', 'failure_message' => null]);
            $inspector->inspectAndStage($file);
            if ($file->fresh()->status === 'superseded') {
                return;
            }
            $validator->validate($file->batch, (string) $file->stage_key, (int) $file->uploaded_by);
        } catch (\Throwable $exception) {
            if ($file->fresh()->status === 'superseded') {
                return;
            }
            Log::warning('Zoho migration file inspection failed.', [
                'file_id' => $file->id,
                'batch_id' => $file->batch_id,
                'exception' => $exception::class,
            ]);
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'The source file could not be inspected. Review the file format and try again.';

            // A stage taking several files keeps its other files: the wrong one is set aside and the rest re-checked.
            $others = $file->batch->files()->where('stage_key', $file->stage_key)->whereKeyNot($file->id)->where('status', 'staged')->exists();
            if (($stages->find((string) $file->stage_key)['multi_source'] ?? false) && $others) {
                $file->update(['status' => 'superseded', 'failure_message' => $message]);
                ZohoMigrationEvent::create([
                    'batch_id' => $file->batch_id,
                    'tenant_id' => $file->tenant_id,
                    'user_id' => $file->uploaded_by,
                    'stage_key' => $file->stage_key,
                    'event_type' => 'file_rejected',
                    'message' => "{$file->original_filename} was not added: {$message} The other files of this step are kept.",
                    'metadata' => ['file_id' => $file->id],
                ]);
                $validator->validate($file->batch, (string) $file->stage_key, (int) $file->uploaded_by);

                return;
            }

            $file->update([
                'status' => 'review_required',
                'failure_message' => $message,
            ]);
            $file->batch->stages()->where('stage_key', $file->stage_key)->update([
                'status' => 'failed',
                'rejected_count' => $file->row_count,
                'updated_at' => now(),
            ]);
        }
    }
}

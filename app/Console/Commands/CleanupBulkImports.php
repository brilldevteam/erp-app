<?php

namespace App\Console\Commands;

use App\Models\BulkImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupBulkImports extends Command
{
    protected $signature = 'bulk-imports:cleanup';
    protected $description = 'Delete bulk import files and records older than seven days';

    public function handle(): int
    {
        BulkImport::where('created_at', '<', now()->subDays(7))
            ->chunkById(100, function ($imports) {
                foreach ($imports as $import) {
                    Storage::disk('local')->deleteDirectory(dirname($import->file_path));
                    $import->delete();
                }
            });

        return self::SUCCESS;
    }
}

<?php

namespace App\Jobs;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\ZohoChartOfAccountsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportZohoMigrationStage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    public function __construct(
        public readonly int $batchId,
        public readonly string $stageKey,
        public readonly int $actorId,
    ) {
        $this->onQueue('imports');
    }

    public function handle(ZohoChartOfAccountsService $chartOfAccounts): void
    {
        $batch = ZohoMigrationBatch::findOrFail($this->batchId);

        try {
            match ($this->stageKey) {
                'chart_of_accounts' => $chartOfAccounts->import($batch, $this->actorId),
                default => throw new \RuntimeException('This migration stage is not executable yet.'),
            };
        } catch (\Throwable $exception) {
            Log::warning('Zoho migration stage import failed.', [
                'batch_id' => $this->batchId,
                'stage_key' => $this->stageKey,
                'exception' => $exception::class,
            ]);
            $batch->stages()->where('stage_key', $this->stageKey)->update(['status' => 'failed']);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $this->actorId,
                'stage_key' => $this->stageKey,
                'event_type' => 'stage_import_failed',
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

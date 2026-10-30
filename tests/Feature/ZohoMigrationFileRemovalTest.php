<?php

namespace Tests\Feature;

use App\Http\Controllers\ZohoMigrationController;
use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoStageCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ZohoMigrationFileRemovalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_upload_step_has_a_guide_and_multi_file_steps_list_each_kind_of_file(): void
    {
        foreach (app(ZohoStageCatalog::class)->all() as $stage) {
            $uploads = $stage['supported'] && !($stage['manual_entry'] ?? false) && $stage['key'] !== 'configuration';
            $this->assertSame($uploads, $stage['upload_guide'] !== null, $stage['key']);
            if ($uploads) {
                $guided = collect($stage['upload_guide']['files'])->pluck('datasets')->flatten()->all();
                $this->assertSame([], array_diff($guided, $stage['datasets']), "{$stage['key']} guides a file the step does not take.");
            }
        }
        $this->assertSame(['Trial Balance report', 'AR Aging Summary report', 'AP Aging Summary report'], array_column(app(ZohoStageCatalog::class)->find('reconciliation')['upload_guide']['files'], 'title'));
    }

    public function test_a_file_removed_before_import_is_deleted_and_the_other_files_are_checked_again(): void
    {
        [$company, $batch] = $this->batch('review_required');
        $ar = $this->file($batch, 'ar_aging');
        $ap = $this->file($batch, 'ap_aging');

        $this->remove($company, $batch, $ap);

        $this->assertNull(ZohoMigrationFile::find($ap->id));
        $this->assertSame(0, ZohoMigrationStagedRow::where('file_id', $ap->id)->count());
        Storage::disk('local')->assertMissing($ap->path);
        $this->assertSame('staged', $ar->fresh()->status);
        $this->assertSame('ap_aging.xlsx was removed before import.', $batch->events()->where('event_type', 'file_removed')->value('message'));
        // Checked again without it: the trial balance is still missing.
        $this->assertContains("Upload Zoho's Trial Balance report; the aging reports alone do not reconcile the books.", $batch->issues()->where('stage_key', 'reconciliation')->pluck('message')->all());

        $this->remove($company, $batch, $ar);
        $this->assertSame(['not_started', 0], [$batch->stages()->where('stage_key', 'reconciliation')->value('status'), $batch->issues()->count()]);
    }

    public function test_imported_files_and_files_of_an_imported_step_cannot_be_removed(): void
    {
        [$company, $batch] = $this->batch('completed');
        $file = $this->file($batch, 'ar_aging');
        $this->assertSame(409, $this->httpStatus(fn () => $this->remove($company, $batch, $file)));

        $batch->stages()->update(['status' => 'review_required']);
        ZohoMigrationStagedRow::where('file_id', $file->id)->update(['status' => 'imported']);
        $this->assertSame(409, $this->httpStatus(fn () => $this->remove($company, $batch, $file)));

        $batch->stages()->update(['status' => 'processing']);
        ZohoMigrationStagedRow::where('file_id', $file->id)->update(['status' => 'staged']);
        $this->assertSame(409, $this->httpStatus(fn () => $this->remove($company, $batch, $file)));
        $this->assertNotNull($file->fresh());
    }

    public function test_removing_a_newer_export_of_an_imported_step_returns_it_to_its_import(): void
    {
        [$company, $batch] = $this->batch('ready', 'income_expenses');
        $this->file($batch, 'expenses', 'superseded', 'imported');
        ZohoMigrationEvent::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'user_id' => $company->id, 'stage_key' => 'income_expenses', 'event_type' => 'stage_imported', 'message' => 'Expenses import completed.', 'metadata' => ['expenses' => 3]]);
        $newer = $this->file($batch, 'expenses');

        $this->remove($company, $batch, $newer);

        $stage = $batch->stages()->where('stage_key', 'income_expenses')->sole();
        $this->assertSame(['completed', ['expenses' => 3]], [$stage->status, $stage->summary]);
    }

    private function batch(string $status, string $stageKey = 'reconciliation'): array
    {
        Storage::fake('local');
        Gate::before(fn () => true);
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id, 'created_by' => $company->id, 'name' => 'File removal test', 'status' => 'active',
            'workflow_mode' => 'standalone', 'accounting_mode' => 'full_history', 'base_currency' => 'QAR', 'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(), 'duplicate_strategy' => 'block', 'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => $stageKey, 'position' => 23, 'status' => $status]);

        return [$company, $batch];
    }

    private function file(ZohoMigrationBatch $batch, string $dataset, string $status = 'staged', string $rowStatus = 'staged'): ZohoMigrationFile
    {
        $stageKey = $dataset === 'expenses' ? 'income_expenses' : 'reconciliation';
        $path = "zoho-migrations/test/{$dataset}-".uniqid().'.xlsx';
        Storage::disk('local')->put($path, 'x');
        $file = ZohoMigrationFile::create(['batch_id' => $batch->id, 'tenant_id' => $batch->tenant_id, 'stage_key' => $stageKey, 'uploaded_by' => $batch->created_by, 'original_filename' => "{$dataset}.xlsx", 'disk' => 'local', 'path' => $path, 'sha256' => hash('sha256', $path), 'size_bytes' => 1, 'mime_type' => 'x', 'status' => $status, 'dataset_type' => $dataset, 'profile_key' => "{$dataset}.v1", 'detection_confidence' => 100, 'headers' => ['customer_name', 'total'], 'row_count' => 1]);
        ZohoMigrationStagedRow::create(['batch_id' => $batch->id, 'file_id' => $file->id, 'tenant_id' => $batch->tenant_id, 'sheet_name' => 'S', 'source_row_number' => 3, 'original_values' => ['customer_name' => 'Waypoint', 'total' => '0'], 'status' => $rowStatus]);

        return $file;
    }

    private function remove(User $user, ZohoMigrationBatch $batch, ZohoMigrationFile $file): void
    {
        $request = Request::create('/', 'DELETE');
        $request->setUserResolver(fn () => $user);
        app(ZohoMigrationController::class)->destroyFile($request, $batch, $file, app(ZohoMigrationValidator::class));
    }

    private function httpStatus(callable $call): int
    {
        try {
            $call();
        } catch (HttpException $exception) {
            return $exception->getStatusCode();
        }

        return 200;
    }
}

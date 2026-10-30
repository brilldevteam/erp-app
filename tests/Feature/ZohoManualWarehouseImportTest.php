<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationStage;
use App\Services\ZohoMigration\ZohoManualPrerequisiteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ZohoManualWarehouseImportTest extends TestCase
{
    use DatabaseTransactions;

    private const HEADER = "name\taddress\tcity\tzip_code\tphone\temail";

    public function test_entered_warehouses_are_created_with_optional_contact_details(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->warehousesBatch();

        $result = app(ZohoManualPrerequisiteService::class)->import($batch, 'warehouses', self::HEADER
            ."\nAl Wakrah Store\tStreet 12\tAl Wakrah\t00000\t+97455550000\tstore@example.com"
            ."\nIndustrial Yard\tStreet 45\tDoha\t00000\t\t", $company->id);

        $this->assertSame(2, $result['imported']);
        $yard = Warehouse::where('created_by', $company->id)->where('name', 'Industrial Yard')->firstOrFail();
        $this->assertNull($yard->phone);
        $this->assertTrue((bool) $yard->is_active);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'warehouses')->value('status'));
    }

    public function test_incomplete_or_duplicate_warehouse_rows_import_nothing(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->warehousesBatch();

        $result = app(ZohoManualPrerequisiteService::class)->import($batch, 'warehouses', self::HEADER
            ."\nStore A\t\tDoha\t00000\t\t"
            ."\nStore B\tStreet 1\tDoha\t00000\t\t"
            ."\nstore b\tStreet 2\tDoha\t00000\t\t", $company->id);

        $this->assertSame(2, $result['rejected']);
        $this->assertSame(0, Warehouse::where('created_by', $company->id)->count());
        $messages = $batch->issues()->where('stage_key', 'warehouses')->pluck('message')->all();
        $this->assertContains('Address is required.', $messages);
        $this->assertContains('Warehouse name is duplicated in rows 3 and 4.', $messages);
    }

    public function test_the_step_can_continue_with_warehouses_already_in_erp(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->warehousesBatch();
        $service = app(ZohoManualPrerequisiteService::class);

        try {
            $service->completeWithoutRows($batch, 'warehouses', $company->id);
            $this->fail('The step continued although ERP has no warehouse.');
        } catch (ValidationException $exception) {
            $this->assertSame(['ERP has no warehouse yet. Add at least one warehouse.'], $exception->errors()['pasted_data']);
        }

        Warehouse::create(['name' => 'Main Warehouse', 'address' => 'Test', 'city' => 'Doha', 'zip_code' => '00000', 'is_active' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $summary = $service->completeWithoutRows($batch, 'warehouses', $company->id);

        $this->assertTrue($summary['existing_records_used']);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'warehouses')->value('status'));
    }

    private function warehousesBatch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Manual warehouses test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'warehouses', 'position' => 6, 'status' => 'not_started']);

        return [$company, $batch];
    }
}

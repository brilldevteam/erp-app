<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationStage;
use App\Services\ZohoMigration\ZohoManualPrerequisiteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;
use Workdo\ProductService\Models\WarehouseStock;

class ZohoManualPrerequisiteImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_confirmed_products_are_imported_before_warehouse_stock_uses_their_skus(): void
    {
        Storage::fake('local');

        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Test',
            'city' => 'Doha',
            'zip_code' => '00000',
            'is_active' => true,
            'creator_id' => $company->id,
            'created_by' => $company->id,
        ]);
        ProductServiceCategory::create(['name' => 'General', 'color' => '#000000', 'creator_id' => $company->id, 'created_by' => $company->id]);
        ProductServiceUnit::create(['unit_name' => 'Piece', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Manual prerequisite test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'default_warehouse_id' => $warehouse->id,
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        foreach (['items' => 9, 'inventory_cutover' => 22] as $stageKey => $position) {
            ZohoMigrationStage::create([
                'batch_id' => $batch->id,
                'tenant_id' => $company->id,
                'stage_key' => $stageKey,
                'position' => $position,
                'status' => 'not_started',
            ]);
        }

        $service = app(ZohoManualPrerequisiteService::class);
        $productResult = $service->import(
            $batch,
            'items',
            "name\tsku\ttype\tcategory\tunit\ttaxes\tsale_price\tpurchase_price\tdescription\nTest Product\tSKU-MANUAL-1\tproduct\tGeneral\tPiece\t\t100\t75\tVerified item",
            $company->id
        );

        $item = ProductServiceItem::where('created_by', $company->id)->where('sku', 'SKU-MANUAL-1')->firstOrFail();
        $this->assertSame(1, $productResult['imported']);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'items')->value('status'));

        $stockResult = $service->import(
            $batch,
            'inventory_cutover',
            "sku\twarehouse\tquantity\nSKU-MANUAL-1\tMain Warehouse\t25",
            $company->id
        );

        $this->assertSame(1, $stockResult['imported']);
        $this->assertEquals(25, WarehouseStock::where('product_id', $item->id)->where('warehouse_id', $warehouse->id)->value('quantity'));
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'inventory_cutover')->value('status'));
    }

    public function test_categories_and_units_are_imported_together(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->catalogBatch();

        $result = app(ZohoManualPrerequisiteService::class)->import(
            $batch,
            'categories_units',
            "record_type\tname\tcolor\ncategory\tConsulting\t#123456\ncategory\tHardware\t\nunit\tPcs\t\nunit\tHours\t",
            $company->id
        );

        $this->assertSame(4, $result['imported']);
        $this->assertSame(['Consulting' => '#123456', 'Hardware' => '#10b981'], ProductServiceCategory::where('created_by', $company->id)->orderBy('name')->pluck('color', 'name')->all());
        $this->assertSame(['Hours', 'Pcs'], ProductServiceUnit::where('created_by', $company->id)->orderBy('unit_name')->pluck('unit_name')->all());
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'categories_units')->value('status'));
    }

    public function test_a_section_may_be_empty_only_when_erp_already_has_those_records(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->catalogBatch();
        $service = app(ZohoManualPrerequisiteService::class);

        try {
            $service->import($batch, 'categories_units', "record_type\tname\tcolor\ncategory\tConsulting\t", $company->id);
            $this->fail('Units were missing but the import was accepted.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(['Add at least one unit.'], $exception->errors()['pasted_data']);
        }
        $this->assertSame(0, ProductServiceCategory::where('created_by', $company->id)->count());

        ProductServiceUnit::create(['unit_name' => 'Box', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $result = $service->import($batch, 'categories_units', "record_type\tname\tcolor\ncategory\tConsulting\t", $company->id);
        $this->assertSame(1, $result['imported']);
    }

    public function test_the_step_can_continue_with_categories_and_units_already_in_erp(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->catalogBatch();
        $service = app(ZohoManualPrerequisiteService::class);
        ProductServiceCategory::create(['name' => 'General', 'color' => '#000000', 'creator_id' => $company->id, 'created_by' => $company->id]);

        try {
            $service->completeWithoutRows($batch, 'categories_units', $company->id);
            $this->fail('The step continued although ERP has no units.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(['Add at least one unit.'], $exception->errors()['pasted_data']);
        }
        $this->assertSame('not_started', $batch->stages()->where('stage_key', 'categories_units')->value('status'));

        ProductServiceUnit::create(['unit_name' => 'Pcs', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $summary = $service->completeWithoutRows($batch, 'categories_units', $company->id);

        $this->assertTrue($summary['existing_records_used']);
        $stage = $batch->stages()->where('stage_key', 'categories_units')->first();
        $this->assertSame('completed', $stage->status);
        $this->assertTrue($stage->summary['existing_records_used']);
    }

    public function test_warehouse_stock_can_be_confirmed_empty_only_when_every_item_is_a_service(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->catalogBatch();
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'inventory_cutover', 'position' => 21, 'status' => 'not_started']);
        $service = app(ZohoManualPrerequisiteService::class);
        $kiosk = ProductServiceItem::forceCreate(['name' => 'Kiosk', 'sku' => 'KIOSK-1', 'type' => 'product', 'creator_id' => $company->id, 'created_by' => $company->id]);

        try {
            $service->completeWithoutRows($batch, 'inventory_cutover', $company->id);
            $this->fail('No stock was confirmed although a stock-tracked item exists.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(['ERP has stock-tracked items (Kiosk); enter their opening quantities instead.'], $exception->errors()['pasted_data']);
        }

        $kiosk->forceFill(['type' => 'service'])->save();
        $summary = $service->completeWithoutRows($batch, 'inventory_cutover', $company->id);

        $this->assertTrue($summary['no_records_used']);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'inventory_cutover')->value('status'));
    }

    public function test_nothing_is_imported_when_the_step_has_no_stage_record(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->catalogBatch();
        $batch->stages()->where('stage_key', 'categories_units')->delete();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            app(ZohoManualPrerequisiteService::class)->import($batch, 'categories_units', "record_type\tname\tcolor\ncategory\tConsulting\t\nunit\tPcs\t", $company->id);
        } finally {
            $this->assertSame(0, ProductServiceCategory::where('created_by', $company->id)->count());
            $this->assertSame(0, ProductServiceUnit::where('created_by', $company->id)->count());
        }
    }

    public function test_services_need_no_unit_but_products_do(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->catalogBatch();
        ProductServiceCategory::create(['name' => 'General', 'color' => '#000000', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $service = app(ZohoManualPrerequisiteService::class);
        $header = "name\tsku\ttype\tcategory\tunit\ttaxes\tsale_price\tpurchase_price\tdescription";

        $rejected = $service->import($batch, 'items', "{$header}\nWidget\tSKU-P-1\tproduct\tGeneral\t\t\t10\t5\t", $company->id);
        $this->assertSame(1, $rejected['rejected']);

        $accepted = $service->import($batch, 'items', "{$header}\nConsulting\tSKU-S-1\tservice\tGeneral\t\t\t100\t0\t", $company->id);
        $this->assertSame(1, $accepted['imported']);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'items')->value('status'));
    }

    private function catalogBatch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Manual categories and units test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        foreach (['categories_units' => 4, 'items' => 5] as $stageKey => $position) {
            ZohoMigrationStage::create([
                'batch_id' => $batch->id,
                'tenant_id' => $company->id,
                'stage_key' => $stageKey,
                'position' => $position,
                'status' => 'not_started',
            ]);
        }

        return [$company, $batch];
    }
}

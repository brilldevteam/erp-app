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
}

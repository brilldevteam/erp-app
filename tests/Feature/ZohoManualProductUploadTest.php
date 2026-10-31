<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationStage;
use App\Services\ZohoMigration\ZohoManualPrerequisiteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceCategory;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\ProductService\Models\ProductServiceUnit;

class ZohoManualProductUploadTest extends TestCase
{
    use DatabaseTransactions;

    private array $files = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->files, 'is_file'));
        parent::tearDown();
    }

    public function test_an_uploaded_workbook_is_previewed_without_writing_then_imported(): void
    {
        Storage::fake('local');
        [$company, $batch] = $this->productsBatch();
        $service = app(ZohoManualPrerequisiteService::class);
        // Columns in a different order, an alias header, numeric cells, and no taxes/description columns.
        $path = $this->xlsx([
            ['Selling Price', 'sku', 'name', 'type', 'category', 'unit', 'purchase_price'],
            [100, 12345, 'Widget', 'product', 'General', 'Pcs', 70.5],
            [250, 'SKU-SVC', 'Consulting', 'service', 'General', '', 0],
        ]);

        $preview = $service->previewUpload($batch, 'items', $path);

        $this->assertSame(['total' => 2, 'valid' => 2, 'invalid' => 0, 'new' => 2, 'existing' => 0], array_diff_key($preview, ['rows' => true]));
        $this->assertSame(0, ProductServiceItem::where('created_by', $company->id)->count());

        $summary = $service->importUpload($batch, 'items', $path, 'products.xlsx', $company->id);

        $this->assertSame(2, $summary['imported']);
        $widget = ProductServiceItem::where('created_by', $company->id)->where('sku', '12345')->firstOrFail();
        $this->assertEquals(100, $widget->sale_price);
        $this->assertEquals(70.5, $widget->purchase_price);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'items')->value('status'));
        $this->assertSame('products.xlsx', $batch->files()->where('stage_key', 'items')->value('original_filename'));
    }

    public function test_invalid_rows_are_reported_in_the_preview(): void
    {
        Storage::fake('local');
        [, $batch] = $this->productsBatch();
        $path = $this->csv("name,sku,type,category,unit,sale_price,purchase_price\nWidget,SKU-1,product,Missing,Pcs,10,5\nGadget,SKU-2,product,General,,10,5\n");

        $preview = app(ZohoManualPrerequisiteService::class)->previewUpload($batch, 'items', $path);

        $this->assertSame(2, $preview['invalid']);
        $this->assertContains('Category was not found for this company.', $preview['rows'][0]['errors']);
        $this->assertContains('Unit is required for the manual product master.', $preview['rows'][1]['errors']);
    }

    public function test_files_missing_required_columns_or_containing_formulas_are_refused(): void
    {
        Storage::fake('local');
        [, $batch] = $this->productsBatch();
        $service = app(ZohoManualPrerequisiteService::class);

        try {
            $service->previewUpload($batch, 'items', $this->csv("name,sku\nWidget,SKU-1\n"));
            $this->fail('A file without the required columns was accepted.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('missing these columns: type, category, unit, sale_price, purchase_price', $exception->errors()['file'][0]);
        }

        $this->expectException(ValidationException::class);
        $service->previewUpload($batch, 'items', $this->xlsx([
            ['name', 'sku', 'type', 'category', 'unit', 'sale_price', 'purchase_price'],
            ['Widget', 'SKU-1', 'product', 'General', 'Pcs', '=10*2', 5],
        ]));
    }

    public function test_the_template_lists_the_erp_names_to_use(): void
    {
        [, $batch] = $this->productsBatch();
        $path = app(ZohoManualPrerequisiteService::class)->uploadTemplate($batch, 'items', 'xlsx');
        $this->files[] = $path;

        $workbook = IOFactory::load($path);
        $this->assertSame(['Import', 'Instructions', 'ERP names'], $workbook->getSheetNames());
        $this->assertSame(['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price', 'description'], $workbook->getSheet(0)->rangeToArray('A1:I1')[0]);
        $this->assertSame(['General', 'Pcs', null], $workbook->getSheet(0)->rangeToArray('D2:F2')[0]);
        $this->assertSame([['category', 'unit', 'taxes'], ['General', 'Pcs', null]], $workbook->getSheetByName('ERP names')->toArray());
    }

    public function test_old_template_placeholders_resolve_when_there_is_one_company_choice(): void
    {
        [$company, $batch] = $this->productsBatch();
        ProductServiceCategory::where('created_by', $company->id)->update(['name' => 'Miscellaneous Items']);
        ProductServiceUnit::where('created_by', $company->id)->update(['unit_name' => 'Each']);

        $preview = app(ZohoManualPrerequisiteService::class)->previewUpload(
            $batch,
            'items',
            $this->csv("name,sku,type,category,unit,sale_price,purchase_price\nPromotion reels,SKU-1793280989075,product,General,Pcs,100,0\n")
        );

        $this->assertSame(1, $preview['valid']);
        $this->assertSame(0, $preview['invalid']);
    }

    private function productsBatch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        ProductServiceCategory::create(['name' => 'General', 'color' => '#000000', 'creator_id' => $company->id, 'created_by' => $company->id]);
        ProductServiceUnit::create(['unit_name' => 'Pcs', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Manual product upload test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'items', 'position' => 5, 'status' => 'not_started']);

        return [$company, $batch];
    }

    private function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        // Strict null comparison so a typed 0 is written as 0, not skipped as empty.
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'zoho-upload-test-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->files[] = $path;

        return $path;
    }

    private function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zoho-upload-test-').'.csv';
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }
}

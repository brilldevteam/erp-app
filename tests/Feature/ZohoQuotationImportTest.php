<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoQuotationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\Quotation\Models\SalesQuotation;

class ZohoQuotationImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoho_estimates_become_quotations_matching_zoho_to_the_cent(): void
    {
        [$company, $batch] = $this->quotationsBatch(['Novera Interiors', 'Takeaway']);
        $this->stage($batch, [
            // Estimate-level discount of 550 spread by Zoho over two lines (6840 + 5520 - 550 = 11810).
            $this->line('EST-1', 'Novera Interiors', 'invoiced', ['Item Name' => 'Exhibition Kiosk Branding', 'Item Desc' => "Vinyl sticker\n5 mm forex", 'Item Price' => '6840', 'Item Total' => '6535.63', 'Total' => '11810', 'Discount Type' => 'entity_level', 'Estimate Date' => '2021-10-27', 'Expiry Date' => '2021-10-30', 'Notes' => 'Thanks for your business.', 'Terms & Conditions' => '50% advance']),
            $this->line('EST-1', 'Novera Interiors', 'invoiced', ['Item Name' => '', 'Item Desc' => 'Installation', 'Item Price' => '5520', 'Item Total' => '5274.37', 'Total' => '11810', 'Discount Type' => 'entity_level', 'Estimate Date' => '2021-10-27', 'Expiry Date' => '2021-10-30', 'Notes' => 'Thanks for your business.', 'Terms & Conditions' => '50% advance']),
            // Item-level 7% discount on 3 × 1000, no expiry date, expired in Zoho.
            $this->line('EST-2', 'takeaway', 'expired', ['Item Name' => 'Social Media Management', 'Quantity' => '3', 'Item Price' => '1000', 'Discount' => '7.00', 'Item Total' => '2790', 'Total' => '2790']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'quotations', $company->id);
        $this->assertSame(['accepted' => 3, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));

        $counts = app(ZohoQuotationService::class)->import($batch, $company->id);

        $this->assertSame(['imported' => 2, 'updated' => 0, 'skipped' => 0, 'lines' => 3, 'converted' => 1], $counts);
        $product = ProductServiceItem::where('created_by', $company->id)->where('sku', ZohoQuotationService::LINE_PRODUCT_SKU)->sole();
        $this->assertSame([ZohoQuotationService::LINE_PRODUCT_NAME, 'service'], [$product->name, $product->type]);

        $first = SalesQuotation::where('created_by', $company->id)->where('quotation_number', 'EST-1')->with('items')->firstOrFail();
        $this->assertSame(['11810.00', '12360.00', '550.00', 'accepted', true], [(string) $first->total_amount, (string) $first->subtotal, (string) $first->discount_amount, $first->status, $first->converted_to_invoice]);
        $this->assertSame(['6535.63', '5274.37'], $first->items->sortBy('id')->pluck('total_amount')->map(fn ($amount) => (string) $amount)->values()->all());
        $this->assertSame("Exhibition Kiosk Branding\nVinyl sticker\n5 mm forex", $first->items->sortBy('id')->first()->description);
        $this->assertSame('Installation', $first->items->sortBy('id')->last()->description);
        $this->assertStringContainsString("Terms & Conditions:\n50% advance", $first->notes);
        $this->assertStringContainsString('Zoho status: Invoiced', $first->notes);
        $this->assertSame([$product->id], $first->items->pluck('product_id')->unique()->values()->all());

        $second = SalesQuotation::where('created_by', $company->id)->where('quotation_number', 'EST-2')->firstOrFail();
        $this->assertSame(['2790.00', 'sent', false], [(string) $second->total_amount, $second->status, $second->converted_to_invoice]);
        $this->assertSame('2022-07-05', $second->due_date->format('Y-m-d'), 'With no Zoho expiry date the quote date is used.');
        $this->assertStringContainsString('Zoho status: Expired', $second->notes);
        $this->assertStringContainsString('No expiry date in Zoho; expiry set to the estimate date.', $second->notes);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'quotations')->value('status'));
    }

    public function test_approved_maps_to_accepted_and_dd_mm_yyyy_dates_use_the_configured_format(): void
    {
        [$company, $batch] = $this->quotationsBatch(['Opera Cafe']);
        $this->stage($batch, [$this->line('EST-3', 'Opera Cafe', 'approved', ['Estimate Date' => '05/07/2022', 'Expiry Date' => '20/07/2022'])]);

        app(ZohoMigrationValidator::class)->validate($batch, 'quotations', $company->id);
        app(ZohoQuotationService::class)->import($batch, $company->id);

        $quote = SalesQuotation::where('created_by', $company->id)->where('quotation_number', 'EST-3')->firstOrFail();
        $this->assertSame(['accepted', '2022-07-05', '2022-07-20'], [$quote->status, $quote->quotation_date->format('Y-m-d'), $quote->due_date->format('Y-m-d')]);
        $this->assertStringContainsString('Zoho status: Approved', $quote->notes);
    }

    public function test_problems_are_reported_and_nothing_is_imported(): void
    {
        [$company, $batch] = $this->quotationsBatch(['Takeaway']);
        SalesQuotation::create(['quotation_number' => 'EST-OLD', 'quotation_date' => '2022-01-01', 'due_date' => '2022-01-01', 'customer_id' => User::where('created_by', $company->id)->where('type', 'client')->value('id'), 'subtotal' => 0, 'tax_amount' => 0, 'discount_amount' => 0, 'total_amount' => 0, 'status' => 'draft', 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [
            $this->line('EST-10', 'Unknown Customer', 'sent'),
            $this->line('EST-11', 'Takeaway', 'on hold'),
            $this->line('EST-12', 'Takeaway', 'sent', ['Quantity' => '1.5', 'Item Total' => '1500', 'Total' => '1500']),
            $this->line('EST-13', 'Takeaway', 'sent', ['Total' => '999']),
            $this->line('EST-OLD', 'Takeaway', 'sent'),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'quotations', $company->id);

        $this->assertSame(5, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'quotations')->pluck('message')->all();
        $this->assertContains("Customer 'Unknown Customer' was not found in ERP. Complete the Customers step first.", $messages);
        $this->assertContains("Estimate Status 'on hold' is not a known Zoho status.", $messages);
        $this->assertContains('Quantity 1.5 is not a whole number; ERP quotation lines store whole quantities only.', $messages);
        $this->assertContains('Line totals add up to 1,000.00 but the Zoho estimate total is 999.00.', $messages);
        $this->assertContains('A quotation with this number already exists in ERP and this migration is configured to block duplicates.', $messages);
        $this->assertSame(1, SalesQuotation::where('created_by', $company->id)->count());
    }

    private function quotationsBatch(array $customers): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        foreach ($customers as $name) {
            User::factory()->create(['type' => 'client', 'name' => $name, 'created_by' => $company->id]);
        }
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Quotation import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'quotations', 'position' => 11, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function line(string $number, string $customer, string $status, array $extra = []): array
    {
        return $extra + [
            'Estimate Date' => '2022-07-05', 'Estimate ID' => 'ID-'.$number, 'Estimate Number' => $number, 'Estimate Status' => $status,
            'Expiry Date' => '', 'Currency Code' => 'QAR', 'Exchange Rate' => '1', 'Discount Type' => 'item_level', 'Total' => '1000',
            'Notes' => '', 'Terms & Conditions' => '', 'Subject' => '', 'Customer Name' => $customer, 'Item Name' => 'Videography',
            'Item Desc' => '', 'Quantity' => '1', 'Discount' => '0.00', 'Item Total' => '1000', 'Item Price' => '1000',
        ];
    }

    private function stage(ZohoMigrationBatch $batch, array $lines): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'quotations',
            'uploaded_by' => $batch->created_by,
            'original_filename' => 'Estimate.csv',
            'disk' => 'local',
            'path' => 'zoho-migrations/test/estimate.csv',
            'sha256' => hash('sha256', (string) microtime(true)),
            'size_bytes' => 1,
            'mime_type' => 'text/csv',
            'status' => 'staged',
            'dataset_type' => 'quotations',
            'profile_key' => 'estimates.v1',
            'detection_confidence' => 100,
            'headers' => array_keys($lines[0]),
            'row_count' => count($lines),
        ]);
        foreach ($lines as $index => $line) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'tenant_id' => $batch->tenant_id,
                'sheet_name' => 'Estimate',
                'source_row_number' => $index + 2,
                'source_external_id' => $line['Estimate ID'],
                'original_values' => $line,
                'status' => 'staged',
            ]);
        }
    }
}

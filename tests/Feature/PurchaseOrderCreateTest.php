<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Workdo\PurchaseOrder\Services\PurchaseOrderService;

class PurchaseOrderCreateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_purchase_order_is_created_and_updated_with_its_currency(): void
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        $vendor = User::factory()->create(['type' => 'vendor', 'created_by' => $company->id]);
        DB::table('settings')->insert(['key' => 'defaultCurrency', 'value' => 'QAR', 'created_by' => $company->id]);
        Auth::login($company);

        $data = [
            'vendor_id' => $vendor->id, 'order_date' => '2026-11-01', 'expected_delivery_date' => '2026-11-09',
            'currency_code' => 'QAR', 'exchange_rate' => 1, 'document_discount_type' => 'fixed', 'document_discount_value' => 0,
            'items' => [['item_name' => 'Screen', 'quantity' => 1, 'unit_price' => 99.92, 'discount_type' => 'fixed', 'discount_value' => 0, 'taxes' => []]],
        ];

        $order = app(PurchaseOrderService::class)->create($data, $company->id, $company->id);

        $this->assertSame(['QAR', '99.92'], [$order->fresh()->currency_code, (string) $order->fresh()->base_amount]);
        $this->assertSame('PO-'.now()->format('Y-m').'-001', $order->purchase_order_number);

        $data['items'][0]['unit_price'] = 150;
        app(PurchaseOrderService::class)->update($order->fresh(), $data);
        $this->assertSame('150.00', (string) $order->fresh()->total_amount);
    }
}

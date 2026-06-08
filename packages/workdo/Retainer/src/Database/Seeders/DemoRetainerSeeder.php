<?php

namespace Workdo\Retainer\Database\Seeders;

use Illuminate\Database\Seeder;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Warehouse;
use Workdo\ProductService\Models\ProductServiceItem;
use Workdo\Retainer\Models\SalesRetainer;
use Workdo\Retainer\Models\SalesRetainerItem;
use Workdo\Retainer\Models\SalesRetainerItemTax;


class DemoRetainerSeeder extends Seeder
{
    public function run($userId): void
    {
      if (SalesRetainer::where('created_by', $userId)->exists()) {
        return;  // All three tables contain user data → skip seeding
      }
        if (!empty($userId)) {
              // Get sample customers, warehouses and products
            $customers  = User::where('type', 'client')->where('created_by', $userId)->pluck('id')->toArray();
            $warehouses = Warehouse::where('created_by', $userId)->pluck('id')->toArray();
            $products   = ProductServiceItem::where('created_by', $userId)->pluck('id')->toArray();

            if (empty($customers) || empty($warehouses) || empty($products)) {
                return;
            }

              // Comprehensive retainer data spanning multiple months with realistic patterns
            $retainerRecords = [
                  // Q1 - Consulting Services
                ['date' => Carbon::now()->subDays(180), 'items' => [1, 2], 'qty' => [1, 1], 'discount' => [0, 5], 'status' => 'accepted', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(175), 'items' => [2, 3], 'qty' => [1, 2], 'discount' => [5, 10], 'status' => 'sent', 'payment_terms' => 'Net 15'],
                ['date' => Carbon::now()->subDays(170), 'items' => [1, 3], 'qty' => [1, 1], 'discount' => [0, 8], 'status' => 'accepted', 'payment_terms' => 'Net 30'],

                  // Q1 - Legal Services
                ['date' => Carbon::now()->subDays(165), 'items' => [2, 4], 'qty' => [1, 2], 'discount' => [0, 12], 'status' => 'draft', 'payment_terms' => 'Net 45'],
                ['date' => Carbon::now()->subDays(160), 'items' => [1, 2], 'qty' => [1, 1], 'discount' => [5, 15], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(155), 'items' => [3, 5], 'qty' => [1, 3], 'discount' => [10, 20], 'status' => 'accepted', 'payment_terms' => 'Net 15'],

                  // Q2 - Marketing Services
                ['date' => Carbon::now()->subDays(150), 'items' => [2, 3], 'qty' => [1, 2], 'discount' => [0, 10], 'status' => 'accepted', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(145), 'items' => [1, 4], 'qty' => [1, 2], 'discount' => [5, 18], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(140), 'items' => [2, 3], 'qty' => [1, 1], 'discount' => [0, 12], 'status' => 'draft', 'payment_terms' => 'Net 45'],

                  // Q2 - IT Services
                ['date' => Carbon::now()->subDays(135), 'items' => [1, 3], 'qty' => [1, 2], 'discount' => [5, 15], 'status' => 'accepted', 'payment_terms' => 'Net 15'],
                ['date' => Carbon::now()->subDays(130), 'items' => [2, 4], 'qty' => [1, 3], 'discount' => [0, 20], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(125), 'items' => [1, 2], 'qty' => [1, 1], 'discount' => [10, 25], 'status' => 'accepted', 'payment_terms' => 'Net 30'],

                  // Q3 - Design Services
                ['date' => Carbon::now()->subDays(120), 'items' => [2, 3], 'qty' => [1, 2], 'discount' => [0, 8], 'status' => 'draft', 'payment_terms' => 'Net 45'],
                ['date' => Carbon::now()->subDays(115), 'items' => [1, 4], 'qty' => [1, 2], 'discount' => [5, 12], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(110), 'items' => [3, 5], 'qty' => [1, 3], 'discount' => [0, 15], 'status' => 'accepted', 'payment_terms' => 'Net 15'],

                  // Q3 - Financial Services
                ['date' => Carbon::now()->subDays(105), 'items' => [1, 2], 'qty' => [1, 1], 'discount' => [5, 18], 'status' => 'accepted', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(100), 'items' => [2, 3], 'qty' => [1, 2], 'discount' => [0, 10], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(95), 'items' => [1, 3], 'qty' => [1, 1], 'discount' => [10, 22], 'status' => 'draft', 'payment_terms' => 'Net 45'],

                  // Q4 - Training Services
                ['date' => Carbon::now()->subDays(90), 'items' => [2, 4], 'qty' => [1, 3], 'discount' => [0, 12], 'status' => 'accepted', 'payment_terms' => 'Net 15'],
                ['date' => Carbon::now()->subDays(85), 'items' => [1, 2], 'qty' => [1, 1], 'discount' => [5, 15], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(80), 'items' => [3, 5], 'qty' => [1, 2], 'discount' => [0, 20], 'status' => 'accepted', 'payment_terms' => 'Net 30'],

                  // Recent Retainers - Mixed Services
                ['date' => Carbon::now()->subDays(30), 'items' => [1, 3], 'qty' => [1, 2], 'discount' => [5, 10], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(20), 'items' => [2, 4], 'qty' => [1, 1], 'discount' => [0, 15], 'status' => 'draft', 'payment_terms' => 'Net 45'],
                ['date' => Carbon::now()->subDays(10), 'items' => [1, 2], 'qty' => [1, 2], 'discount' => [5, 12], 'status' => 'accepted', 'payment_terms' => 'Net 15'],
                ['date' => Carbon::now()->subDays(5), 'items' => [2, 3], 'qty' => [1, 1], 'discount' => [0, 8], 'status' => 'sent', 'payment_terms' => 'Net 30'],
                ['date' => Carbon::now()->subDays(2), 'items' => [1, 3], 'qty' => [1, 2], 'discount' => [10, 18], 'status' => 'draft', 'payment_terms' => 'Net 30']
            ];

            foreach ($retainerRecords as $index => $record) {
                $customerId  = $customers[array_rand($customers)];
                $warehouseId = $warehouses[array_rand($warehouses)];

                  // Get products available in this warehouse
                $warehouseProducts = ProductServiceItem::where('is_active', true)
                    ->where('created_by', $userId)
                    ->whereHas('warehouseStocks', function ($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId)
                            ->where('quantity', '>', 0);
                    })
                    ->pluck('id')
                    ->toArray();

                if (empty($warehouseProducts)) {
                    continue;  // Skip this retainer if no products available in warehouse
                }

                  // Generate retainer number
                $retainerNumber = 'RET-' . $record['date']->format('Y-m') . '-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT);

                  // Create retainer record
                $retainer = SalesRetainer::create([
                    'retainer_number' => $retainerNumber,
                    'customer_id'     => $customerId,
                    'warehouse_id'    => $warehouseId,
                    'retainer_date'   => $record['date']->toDateString(),
                    'due_date'        => Carbon::now()->addDays(rand(15, 60))->toDateString(),
                    'status'          => $record['status'],
                    'payment_terms'   => $record['payment_terms'],
                    'notes'           => 'Professional services retainer agreement',
                    'subtotal'        => 0,
                    'tax_amount'      => 0,
                    'discount_amount' => 0,
                    'total_amount'    => 0,
                    'paid_amount'     => 0,
                    'balance_amount'  => 0,
                    'creator_id'      => $userId,
                    'created_by'      => $userId,
                    'created_at'      => $record['date'],
                    'updated_at'      => $record['date'],
                ]);

                  // Generate items for this retainer
                $itemsCount       = rand($record['items'][0], min($record['items'][1], count($warehouseProducts)));
                $selectedProducts = array_rand(array_flip($warehouseProducts), min($itemsCount, count($warehouseProducts)));
                if (!is_array($selectedProducts)) {
                    $selectedProducts = [$selectedProducts];
                }

                $subtotal      = 0;
                $totalTax      = 0;
                $totalDiscount = 0;

                foreach ($selectedProducts as $productId) {
                    $product = ProductServiceItem::with('warehouseStocks')->find($productId);
                    if (!$product) continue;

                      // Get warehouse stock for validation
                    $warehouseStock = $product->warehouseStocks
                        ->where('warehouse_id', $warehouseId)
                        ->where('quantity', '>', 0)
                        ->first();

                    if (!$warehouseStock) continue;

                    $maxQuantity        = min($warehouseStock->quantity, $record['qty'][1]);
                    $quantity           = rand($record['qty'][0], max($record['qty'][0], $maxQuantity));
                    $unitPrice          = $product->sale_price ?? rand(500, 5000);
                    $discountPercentage = rand($record['discount'][0], $record['discount'][1]);
                    $taxPercentage      = rand(8, 18);

                    $lineTotal      = $quantity * $unitPrice;
                    $discountAmount = ($lineTotal * $discountPercentage) / 100;
                    $afterDiscount  = $lineTotal - $discountAmount;
                    $taxAmount      = ($afterDiscount * $taxPercentage) / 100;
                    $itemTotal      = $afterDiscount + $taxAmount;

                      // Create retainer item
                    $item = SalesRetainerItem::create([
                        'retainer_id'         => $retainer->id,
                        'product_id'          => $productId,
                        'quantity'            => $quantity,
                        'unit_price'          => $unitPrice,
                        'discount_percentage' => $discountPercentage,
                        'discount_amount'     => $discountAmount,
                        'tax_percentage'      => $taxPercentage,
                        'tax_amount'          => $taxAmount,
                        'total_amount'        => $itemTotal,
                        'created_at'          => $record['date'],
                        'updated_at'          => $record['date'],
                    ]);

                      // Add tax details
                    SalesRetainerItemTax::create([
                        'item_id'  => $item->id,
                        'tax_name' => 'GST',
                        'tax_rate' => $taxPercentage,
                    ]);

                    $subtotal      += $lineTotal;
                    $totalTax      += $taxAmount;
                    $totalDiscount += $discountAmount;
                }

                  // Update retainer totals
                $finalTotal = $subtotal + $totalTax - $totalDiscount;
                $retainer->update([
                    'subtotal'        => $subtotal,
                    'tax_amount'      => $totalTax,
                    'discount_amount' => $totalDiscount,
                    'total_amount'    => $finalTotal,
                    'balance_amount'  => $finalTotal,
                ]);
            }
        }
    }
}

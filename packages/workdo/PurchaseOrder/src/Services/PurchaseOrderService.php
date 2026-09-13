<?php

namespace Workdo\PurchaseOrder\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Workdo\PurchaseOrder\Models\PurchaseOrder;

class PurchaseOrderService
{
    public function __construct(private PurchaseOrderCalculator $calculator) {}

    public function create(array $data, int $userId, int $companyId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId, $companyId) {
            $totals = $this->calculator->calculate($data);
            $attributes = $this->attributes($data, $totals);
            $attributes['purchase_order_number'] = $this->nextNumber($companyId);
            $attributes['creator_id'] = $userId;
            $attributes['created_by'] = $companyId;

            $order = PurchaseOrder::create($attributes);
            $this->replaceItems($order, $totals['items']);
            $order->histories()->create([
                'to_status' => 'draft',
                'action' => 'created',
                'user_id' => $userId,
            ]);

            return $order;
        });
    }

    public function update(PurchaseOrder $order,array $data): PurchaseOrder
    {
        abort_unless($order->isEditable(),422,__('Only draft purchase orders can be edited.'));
        return DB::transaction(function()use($order,$data){$totals=$this->calculator->calculate($data);$order->update($this->attributes($data,$totals));$order->items()->delete();$this->replaceItems($order,$totals['items']);return $order;});
    }

    private function attributes(array $d,array $t): array
    {
        return array_merge(
            Arr::only($t, [
                'subtotal',
                'line_discount_amount',
                'document_discount_amount',
                'tax_amount',
                'total_amount',
            ]),
            Arr::only($d, [
                'vendor_id',
                'warehouse_id',
                'vendor_reference',
                'vendor_quotation_number',
                'order_date',
                'expected_delivery_date',
                'currency_code',
                'exchange_rate',
                'billing_address',
                'delivery_address',
                'document_discount_type',
                'document_discount_value',
                'shipping_amount',
                'adjustment_amount',
                'notes',
                'terms',
                'external_source',
                'external_id',
                'external_reference',
            ])
        );
    }

    private function replaceItems(PurchaseOrder $order,array $items): void
    {
        foreach($items as $line){$taxes=$line['taxes'];unset($line['taxes']);$item=$order->items()->create(collect($line)->only(['product_id','item_name','description','unit','quantity','unit_price','discount_type','discount_value','discount_amount','subtotal','tax_amount','total_amount','line_order'])->all());foreach($taxes as $tax)$item->taxes()->create(collect($tax)->only(['tax_id','tax_name','tax_rate','tax_amount'])->all());}
    }

    private function nextNumber(int $companyId): string
    {
        $prefix='PO-'.now()->format('Y-m').'-';
        $last=PurchaseOrder::withTrashed()->where('created_by',$companyId)->where('purchase_order_number','like',$prefix.'%')->lockForUpdate()->orderByDesc('purchase_order_number')->value('purchase_order_number');
        return $prefix.str_pad($last ? ((int)substr($last,-3)+1) : 1,3,'0',STR_PAD_LEFT);
    }
}

<?php

namespace Workdo\ProductService\Listeners;

use App\Events\PostSalesInvoice;
use Workdo\ProductService\Models\WarehouseStock;

class PostSalesInvoiceListener
{
    public function handle(PostSalesInvoice $event)
    {
        $salesInvoice = $event->salesInvoice;

        if ($salesInvoice->type !== 'product') {
            return;
        }

        foreach ($salesInvoice->items()->get() as $item) {
            $stocks = WarehouseStock::query()
                ->where('product_id', $item->product_id)
                ->when($salesInvoice->warehouse_id, fn ($query) => $query->where('warehouse_id', $salesInvoice->warehouse_id))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $required = (float) $item->quantity;
            $available = (float) $stocks->sum('quantity');

            if ($available < $required) {
                throw new \RuntimeException(__('Insufficient stock for one or more invoice items.'));
            }

            foreach ($stocks as $stock) {
                if ($required <= 0) {
                    break;
                }

                $deduction = min((float) $stock->quantity, $required);
                if ($deduction > 0) {
                    $stock->decrement('quantity', $deduction);
                    $required -= $deduction;
                }
            }
        }
    }
}

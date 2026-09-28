<?php

namespace Workdo\PurchaseOrder\Services;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceItemTax;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Workdo\PurchaseOrder\Models\PurchaseOrder;

class PurchaseOrderToInvoiceService
{
    public function convert(PurchaseOrder $order,array $quantities,int $userId): PurchaseInvoice
    {
        return DB::transaction(function()use($order,$quantities,$userId){$order=PurchaseOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if($order->order_status!=='issued')throw ValidationException::withMessages(['purchase_order'=>__('Only issued purchase orders can be converted.')]);
            $items=$order->items()->with(['taxes','allocations'])->lockForUpdate()->get();$selected=[];
            foreach($items as $item){$qty=round((float)($quantities[$item->id]??0),4);if($qty<0||$qty>$item->remaining_quantity)throw ValidationException::withMessages(["quantities.{$item->id}"=>__('Invoice quantity exceeds the remaining quantity.')]);if($qty>0)$selected[]=[$item,$qty];}
            if(!$selected)throw ValidationException::withMessages(['quantities'=>__('Select at least one quantity to invoice.')]);
            $invoice=PurchaseInvoice::create(['purchase_order_reference'=>$order->purchase_order_number,'currency_code'=>$order->currency_code,'exchange_rate'=>$order->exchange_rate,'invoice_date'=>now()->toDateString(),'due_date'=>now()->addDays(30)->toDateString(),'vendor_id'=>$order->vendor_id,'warehouse_id'=>$order->warehouse_id,'payment_terms'=>$order->terms,'notes'=>trim(__('Created from Purchase Order :number',['number'=>$order->purchase_order_number])."\n".$order->notes),'subtotal'=>0,'tax_amount'=>0,'discount_amount'=>0,'total_amount'=>0,'balance_amount'=>0,'status'=>'draft','creator_id'=>$userId,'created_by'=>$order->created_by]);
            $subtotal=$tax=$discount=$total=0;
            foreach($selected as [$poItem,$qty]){$gross=round($qty*(float)$poItem->unit_price,2);$lineDiscount=$poItem->discount_type==='fixed'?round((float)$poItem->discount_amount*((float)$qty/(float)$poItem->quantity),2):round($gross*(float)$poItem->discount_value/100,2);$rate=$poItem->subtotal-$poItem->discount_amount>0?((float)$poItem->tax_amount/((float)$poItem->subtotal-(float)$poItem->discount_amount))*100:0;$lineTax=round(($gross-$lineDiscount)*$rate/100,2);
                $pi=PurchaseInvoiceItem::create(['invoice_id'=>$invoice->id,'product_id'=>$poItem->product_id,'description'=>$poItem->description?:$poItem->item_name,'unit'=>$poItem->unit,'quantity'=>$qty,'unit_price'=>$poItem->unit_price,'discount_percentage'=>$poItem->discount_type==='percentage'?$poItem->discount_value:($gross>0?$lineDiscount/$gross*100:0),'discount_amount'=>$lineDiscount,'tax_percentage'=>$rate,'tax_amount'=>$lineTax,'total_amount'=>$gross-$lineDiscount+$lineTax]);
                foreach($poItem->taxes as $itemTax)PurchaseInvoiceItemTax::create(['item_id'=>$pi->id,'tax_name'=>$itemTax->tax_name,'tax_rate'=>$itemTax->tax_rate]);
                $poItem->allocations()->create(['purchase_invoice_id'=>$invoice->id,'purchase_invoice_item_id'=>$pi->id,'quantity'=>$qty]);$subtotal+=$gross;$discount+=$lineDiscount;$tax+=$lineTax;$total+=$gross-$lineDiscount+$lineTax;}
            $invoice->update(compact('subtotal')+['discount_amount'=>$discount,'tax_amount'=>$tax,'total_amount'=>$total,'balance_amount'=>$total]);
            $order->invoiceLinks()->create(['purchase_invoice_id'=>$invoice->id,'created_by'=>$userId]);$this->refreshBillingStatus($order);$order->histories()->create(['from_status'=>$order->order_status,'to_status'=>$order->order_status,'action'=>'converted_to_invoice','user_id'=>$userId,'metadata'=>['purchase_invoice_id'=>$invoice->id]]);return $invoice;});
    }
    public function refreshBillingStatus(PurchaseOrder $order): void{$ordered=(float)$order->items()->sum('quantity');$billed=(float)DB::table('purchase_order_invoice_items')->join('purchase_order_items','purchase_order_items.id','=','purchase_order_invoice_items.purchase_order_item_id')->where('purchase_order_items.purchase_order_id',$order->id)->sum('purchase_order_invoice_items.quantity');$order->update(['billing_status'=>$billed<=0?'unbilled':($billed+0.0001>=$ordered?'billed':'partially_billed')]);}
}

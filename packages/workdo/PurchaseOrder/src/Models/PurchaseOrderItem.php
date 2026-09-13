<?php
namespace Workdo\PurchaseOrder\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItem extends Model {
    protected $guarded = [];
    protected $appends = ['billed_quantity','remaining_quantity'];
    protected $casts = ['quantity'=>'decimal:4','unit_price'=>'decimal:4','subtotal'=>'decimal:2','discount_amount'=>'decimal:2','tax_amount'=>'decimal:2','total_amount'=>'decimal:2'];
    public function purchaseOrder(){return $this->belongsTo(PurchaseOrder::class);}
    public function product(){return $this->belongsTo(\Workdo\ProductService\Models\ProductServiceItem::class,'product_id');}
    public function taxes(){return $this->hasMany(PurchaseOrderItemTax::class);}
    public function allocations(){return $this->hasMany(PurchaseOrderInvoiceItem::class);}
    public function getBilledQuantityAttribute(): float{return (float)$this->allocations()->sum('quantity');}
    public function getRemainingQuantityAttribute(): float{return max(0,(float)$this->quantity-$this->billed_quantity);}
}

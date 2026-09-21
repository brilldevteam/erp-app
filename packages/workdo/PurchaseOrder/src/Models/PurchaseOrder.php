<?php

namespace Workdo\PurchaseOrder\Models;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $guarded = [];
    protected $casts = [
        'order_date' => 'date', 'expected_delivery_date' => 'date',
        'billing_address' => 'array', 'delivery_address' => 'array',
        'approved_at' => 'datetime', 'issued_at' => 'datetime', 'closed_at' => 'datetime',
        'exchange_rate' => 'decimal:8', 'subtotal' => 'decimal:2',
        'line_discount_amount' => 'decimal:2', 'document_discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2', 'shipping_amount' => 'decimal:2',
        'adjustment_amount' => 'decimal:2', 'total_amount' => 'decimal:2',
    ];

    public function vendor() { return $this->belongsTo(User::class, 'vendor_id'); }
    public function vendorDetails() { return $this->belongsTo(\Workdo\Account\Models\Vendor::class, 'vendor_id', 'user_id'); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function creator() { return $this->belongsTo(User::class, 'creator_id'); }
    public function items() { return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_order'); }
    public function approvals() { return $this->hasMany(PurchaseOrderApproval::class); }
    public function histories() { return $this->hasMany(PurchaseOrderStatusHistory::class)->latest(); }
    public function attachments() { return $this->hasMany(PurchaseOrderAttachment::class); }
    public function invoiceLinks() { return $this->hasMany(PurchaseOrderInvoiceLink::class); }

    public function isEditable(): bool { return $this->order_status === 'draft'; }
}

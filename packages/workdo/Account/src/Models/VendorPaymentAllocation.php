<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PurchaseInvoice;

class VendorPaymentAllocation extends Model
{
    protected $fillable = [
        'payment_id',
        'invoice_id',
        'allocated_amount',
        'carrying_base_amount',
        'settlement_base_amount',
        'exchange_difference',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'carrying_base_amount' => 'decimal:2',
        'settlement_base_amount' => 'decimal:2',
        'exchange_difference' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(VendorPayment::class, 'payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }


}

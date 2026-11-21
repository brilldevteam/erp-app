<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'currency_code', 'exchange_rate', 'base_amount',
        'return_number',
        'return_date',
        'vendor_id',
        'warehouse_id',
        'original_invoice_id',
        'reason',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class, 'return_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function vendorDetails(): BelongsTo
    {
        return $this->belongsTo(\Workdo\Account\Models\Vendor::class, 'vendor_id', 'user_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'original_invoice_id');
    }

    public function debitNote(): HasMany
    {
        return $this->hasMany(DebitNote::class, 'return_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($return) {
            if (empty($return->return_number)) {
                $return->return_number = static::generateReturnNumber();
            }
        });
    }

    public static function generateReturnNumber(): string
    {
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'return_number', 'PR-' . date('Y') . '-' . date('m') . '-', 3);
    }
}

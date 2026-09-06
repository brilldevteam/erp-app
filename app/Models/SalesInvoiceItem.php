<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'discount_type',
        'discount_value',
        'quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'total_amount',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'discount_value' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Workdo\ProductService\Models\ProductServiceItem::class, 'product_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(SalesInvoiceItemTax::class, 'item_id');
    }

    public function calculateAmounts()
    {
        if ($this->discount_type === 'fixed') {
            $this->discount_percentage = 0;
        } else {
            $this->discount_value = 0;
        }
        $amounts = \App\Services\SalesLineAmounts::calculate($this->getAttributes());
        $this->discount_amount = $amounts['discount_amount'];
        $this->tax_amount = $amounts['tax_amount'];
        $this->total_amount = $amounts['total_amount'];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateAmounts();
        });
    }
}

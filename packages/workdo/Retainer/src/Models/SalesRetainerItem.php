<?php

namespace Workdo\Retainer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workdo\ProductService\Models\ProductServiceItem;

class SalesRetainerItem extends Model
{
    protected $fillable = [
        'retainer_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'total_amount'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    public function retainer(): BelongsTo
    {
        return $this->belongsTo(SalesRetainer::class, 'retainer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(SalesRetainerItemTax::class, 'item_id');
    }
}
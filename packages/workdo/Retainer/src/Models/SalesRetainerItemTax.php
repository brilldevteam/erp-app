<?php

namespace Workdo\Retainer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesRetainerItemTax extends Model
{
    protected $fillable = [
        'item_id',
        'tax_name',
        'tax_rate'
    ];

    protected $casts = [
        'tax_rate' => 'decimal:2'
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(SalesRetainerItem::class, 'item_id');
    }
}
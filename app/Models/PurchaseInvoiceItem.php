<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workdo\ProductService\Models\ProductServiceItem;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'account_id',
        'unit',
        'description',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_percentage',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'total_amount'
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductServiceItem::class, 'product_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(\Workdo\Account\Models\ChartOfAccount::class, 'account_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItemTax::class, 'item_id');
    }

    /**
     * Line amounts for a bill line. The discount is either a percentage or a fixed amount; a fixed amount
     * also keeps its equivalent percentage so purchase returns can pro-rate it.
     */
    public static function amounts(array $item): array
    {
        $lineTotal = round((float) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0), 2);
        $fixed = ($item['discount_type'] ?? 'percentage') === 'fixed';
        $discount = $fixed
            ? round(min(max((float) ($item['discount_value'] ?? 0), 0), $lineTotal), 2)
            : round($lineTotal * (float) ($item['discount_percentage'] ?? 0) / 100, 2);
        $tax = round(($lineTotal - $discount) * (float) ($item['tax_percentage'] ?? 0) / 100, 2);

        return [
            'subtotal' => $lineTotal,
            'discount_type' => $fixed ? 'fixed' : 'percentage',
            'discount_value' => $fixed ? $discount : 0,
            'discount_percentage' => $fixed ? ($lineTotal > 0 ? round($discount / $lineTotal * 100, 2) : 0) : (float) ($item['discount_percentage'] ?? 0),
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total_amount' => round($lineTotal - $discount + $tax, 2),
        ];
    }

    public function calculateAmounts()
    {
        $amounts = static::amounts($this->getAttributes());
        unset($amounts['subtotal']);
        foreach ($amounts as $field => $value) {
            $this->{$field} = $value;
        }
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateAmounts();
        });
    }
}

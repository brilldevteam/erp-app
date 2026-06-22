<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoice extends Model
{
    protected $fillable = [
        'quotation_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'customer_id',
        'warehouse_id',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'template_key',
        'document_logo',
        'document_snapshot',
        'sent_at',
        'first_viewed_at',
        'last_viewed_at',
        'last_reminded_at',
        'type',
        'payment_terms',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2'
        ,'document_snapshot' => 'array'
        ,'sent_at' => 'datetime'
        ,'first_viewed_at' => 'datetime'
        ,'last_viewed_at' => 'datetime'
        ,'last_reminded_at' => 'datetime'
    ];

    protected $appends = ['display_status'];

    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class, 'invoice_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(
            \Workdo\Quotation\Models\SalesQuotation::class,
            'quotation_id'
        );
    }

    public function customerDetails(): BelongsTo
    {
        return $this->belongsTo(\Workdo\Account\Models\Customer::class, 'customer_id', 'user_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(\Workdo\Account\Models\CustomerPaymentAllocation::class, 'invoice_id');
    }

    public function salesReturns(): HasMany
    {
        return $this->hasMany(SalesInvoiceReturn::class, 'original_invoice_id');
    }

    public function documentActivities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class, 'document_id')
            ->where('document_type', 'invoice')
            ->latest('created_at');
    }

    public function isOverdue(): bool
    {
        return $this->due_date < now() && $this->status !== 'paid';
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->isOverdue()) {
            return 'overdue';
        }
        return $this->status;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }
        });
    }

    public static function generateInvoiceNumber(): string
    {
        return app(\App\Services\Documents\DocumentNumberService::class)
            ->next('invoice', static::class, 'invoice_number');
    }
}

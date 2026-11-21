<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesProposal extends Model
{
    protected $fillable = [
        'proposal_number',
        'proposal_date',
        'due_date',
        'customer_id',
        'warehouse_id',
        'payment_terms',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'status',
        'converted_to_invoice',
        'invoice_id',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'proposal_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'converted_to_invoice' => 'boolean'
    ];

    protected $appends = ['display_status'];

    public function items(): HasMany
    {
        return $this->hasMany(SalesProposalItem::class, 'proposal_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function customerDetails(): BelongsTo
    {
        return $this->belongsTo(\Workdo\Account\Models\Customer::class, 'customer_id', 'user_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_date < now() && !in_array($this->status, ['accepted', 'rejected']);
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

        static::creating(function ($proposal) {
            if (empty($proposal->proposal_number)) {
                $proposal->proposal_number = static::generateProposalNumber();
            }
        });
    }

    public static function generateProposalNumber(): string
    {
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'proposal_number', 'SP-' . date('Y') . '-' . date('m') . '-', 3);
    }
}

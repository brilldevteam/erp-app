<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class VendorPayment extends Model
{
    protected $fillable = [
        'payment_number',
        'payment_date',
        'payment_mode',
        'vendor_id',
        'bank_account_id',
        'reference_number',
        'payment_amount',
        'currency_code',
        'base_currency_code',
        'exchange_rate',
        'base_amount',
        'status',
        'needs_bank_verification',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'needs_bank_verification' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function vendorDetails(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'user_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(VendorPaymentAllocation::class, 'payment_id');
    }

    public function debitNoteApplications(): HasMany
    {
        return $this->hasMany(DebitNoteApplication::class, 'payment_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = static::generatePaymentNumber();
            }
        });
    }

    public static function generatePaymentNumber(): string
    {
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'payment_number', 'VP-' . date('Y') . '-' . date('m') . '-', 3);
    }
}

<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class CustomerPayment extends Model
{
    protected $appends = [
        'available_deposit',
        'available_deposit_base',
    ];

    protected $fillable = [
        'payment_number',
        'payment_date',
        'payment_mode',
        'customer_id',
        'bank_account_id',
        'reference_number',
        'payment_amount',
        'currency_code',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function customerDetails(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'user_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class, 'payment_id');
    }

    public function creditNoteApplications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class, 'payment_id');
    }

    public function getAvailableDepositAttribute(): float
    {
        $rate = (float) ($this->exchange_rate ?: 1);

        return round($this->available_deposit_base / $rate, 2);
    }

    public function getAvailableDepositBaseAttribute(): float
    {
        $this->loadMissing(['allocations.invoice', 'creditNoteApplications.creditNote']);
        $allocatedBaseAmount = (float) $this->allocations->sum(
            fn ($allocation) => (float) $allocation->allocated_amount * (float) ($allocation->invoice?->exchange_rate ?: 1)
        );
        $creditNoteBaseAmount = (float) $this->creditNoteApplications->sum(
            fn ($application) => (float) $application->applied_amount * (float) ($application->creditNote?->exchange_rate ?: 1)
        );
        $cashAppliedBaseAmount = min(
            (float) $this->base_amount,
            max(0, $allocatedBaseAmount - $creditNoteBaseAmount)
        );

        return round(max(0, (float) $this->base_amount - $cashAppliedBaseAmount), 2);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customerPayment) {
            if (empty($customerPayment->payment_number)) {
                $customerPayment->payment_number = static::generatePaymentNumber();
            }
        });
    }

    public static function generatePaymentNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastPayment = static::where('payment_number', 'like', "CP-{$year}-{$month}-%")
            ->where('created_by', creatorId())
            ->orderBy('payment_number', 'desc')
            ->first();

        if ($lastPayment) {
            $lastNumber = (int) substr($lastPayment->payment_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "CP-{$year}-{$month}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}

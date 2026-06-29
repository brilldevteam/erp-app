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
    ];

    protected $fillable = [
        'payment_number',
        'payment_date',
        'customer_id',
        'bank_account_id',
        'reference_number',
        'payment_amount',
        'status',
        'notes',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2'
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
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
        $allocatedAmount = $this->relationLoaded('allocations')
            ? (float) $this->allocations->sum('allocated_amount')
            : (float) $this->allocations()->sum('allocated_amount');
        $creditNoteAmount = $this->relationLoaded('creditNoteApplications')
            ? (float) $this->creditNoteApplications->sum('applied_amount')
            : (float) $this->creditNoteApplications()->sum('applied_amount');
        $cashAppliedAmount = min(
            (float) $this->payment_amount,
            max(0, $allocatedAmount - $creditNoteAmount)
        );

        return max(0, (float) $this->payment_amount - $cashAppliedAmount);
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

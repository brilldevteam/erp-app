<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_number',
        'expense_date',
        'category_id',
        'bank_account_id',
        'chart_of_account_id',
        'amount',
        'currency_code',
        'exchange_rate',
        'base_amount',
        'description',
        'reference_number',
        'status',
        'needs_bank_verification',
        'approved_by',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:2',
            'needs_bank_verification' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategories::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($expense) {
            if (empty($expense->expense_number)) {
                $expense->expense_number = static::generateExpenseNumber($expense->created_by);
            }
        });
    }

    public static function generateExpenseNumber($createdBy = null): string
    {
        if ($createdBy) {
            $userId = $createdBy;
        } elseif (auth()->check()) {
            $userId = creatorId();
        } else {
            $userId = 1;
        }

        return \App\Support\DocumentNumber::next(static::where('created_by', $userId), 'expense_number', 'EXP-' . date('Y') . '-' . date('m') . '-', 3);
    }
}

<?php

namespace Workdo\PettyCashManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;


class PettyCash extends Model
{
    use HasFactory;

    protected $fillable = [
        'pettycash_number',
        'date',
        'opening_balance',
        'added_amount',
        'total_balance',
        'total_expense',
        'closing_balance',
        'remarks',
        'status',
        'bank_account_id',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date'
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($pettycash) {
            if (empty($pettycash->pettycash_number)) {
                $pettycash->pettycash_number = static::generatePettyCashNumber();
            }
        });
    }

    public static function generatePettyCashNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastPettyCash = static::where('pettycash_number', 'like', "PC-{$year}-{$month}-%")
            ->where('created_by', creatorId())
            ->orderBy('pettycash_number', 'desc')
            ->first();

        if ($lastPettyCash) {
            $lastNumber = (int) substr($lastPettyCash->pettycash_number, -3);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "PC-{$year}-{$month}-" . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function expenses()
    {
        return $this->hasMany(PettyCashExpense::class, 'pettycash_id');
    }


}

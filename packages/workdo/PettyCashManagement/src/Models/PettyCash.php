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
        return \App\Support\DocumentNumber::next(static::where('created_by', creatorId()), 'pettycash_number', 'PC-' . date('Y') . '-' . date('m') . '-', 3);
    }

    public function expenses()
    {
        return $this->hasMany(PettyCashExpense::class, 'pettycash_id');
    }

    public function attachments()
    {
        return $this->hasMany(PettyCashAttachment::class, 'attachable_id')
            ->where('attachable_type', PettyCashAttachment::FUND)->latest('id');
    }


}

<?php

namespace Workdo\PettyCashManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PettyCashCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_id',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
        ];
    }

    /**
     * Accounts a category can post to: the same expense/asset accounts a bill line can use.
     * Empty when the Account module is not active, so categories still work without accounting.
     */
    public static function expenseAccountOptions()
    {
        if (!class_exists(\Workdo\Account\Models\ChartOfAccount::class) || !Module_is_active('Account')) {
            return collect();
        }

        return \Workdo\Account\Models\ChartOfAccount::billAccountOptions(creatorId());
    }

    /** Validation rules for the category's account: required whenever accounting is active. */
    public static function accountRules(): array
    {
        $ids = static::expenseAccountOptions()->pluck('id')->all();

        return $ids === []
            ? ['nullable', 'integer']
            : ['required', 'integer', \Illuminate\Validation\Rule::in($ids)];
    }

    /** Expense account this category posts to (Account module). */
    public function account()
    {
        return $this->belongsTo(\Workdo\Account\Models\ChartOfAccount::class, 'account_id');
    }
}

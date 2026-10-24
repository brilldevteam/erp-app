<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workdo\Account\Models\AccountType;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'account_name',
        'level',
        'normal_balance',
        'opening_balance',
        'current_balance',
        'is_active',
        'is_system_account',
        'description',
        'account_type_id',
        'parent_account_id',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean'
        ];
    }


    public function accountType()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function account_type()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent_account()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function journalEntryItems(): HasMany
    {
        return $this->hasMany(JournalEntryItem::class, 'account_id');
    }

    /**
     * Accounts a bill (purchase invoice) line can be charged to: expenses (5000-5999), inventory (1200),
     * prepaid and other current assets (1300-1399) and fixed assets (1600-1999, excluding accumulated depreciation).
     */
    public static function billAccountOptions($creatorId)
    {
        return static::where('created_by', $creatorId)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name'])
            ->filter(function ($account) {
                $code = (int) $account->account_code;
                if (strlen((string) $account->account_code) !== 4) {
                    return false;
                }
                if ($code >= 1600 && $code <= 1999 && stripos($account->account_name, 'depr') !== false) {
                    return false;
                }

                return ($code >= 5000 && $code <= 5999) || $code === 1200
                    || ($code >= 1300 && $code <= 1399) || ($code >= 1600 && $code <= 1999);
            })
            ->values();
    }

    /**
     * Active sales revenue accounts (4000-4899, Sales Revenue type) an invoice can post its revenue to.
     */
    public function scopeSalesRevenueOptions($query, $creatorId)
    {
        return $query->where('created_by', $creatorId)
            ->where('is_active', true)
            ->whereBetween('account_code', ['4000', '4899'])
            ->whereIn('account_type_id', AccountType::select('id')->where('created_by', $creatorId)->where('code', 'SR'));
    }
}
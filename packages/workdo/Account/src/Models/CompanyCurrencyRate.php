<?php

namespace Workdo\Account\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyCurrencyRate extends Model
{
    protected $fillable = ['created_by', 'currency_code', 'exchange_rate', 'effective_date', 'is_active', 'updated_by'];

    protected $casts = [
        'exchange_rate' => 'decimal:8',
        'effective_date' => 'date',
        'is_active' => 'boolean',
    ];
}

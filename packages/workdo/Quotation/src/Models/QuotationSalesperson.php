<?php

namespace Workdo\Quotation\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationSalesperson extends Model
{
    protected $table = 'quotation_salespersons';

    protected $fillable = ['name', 'email', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

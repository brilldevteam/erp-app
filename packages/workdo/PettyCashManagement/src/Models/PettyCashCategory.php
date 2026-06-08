<?php

namespace Workdo\PettyCashManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PettyCashCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [

        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentActivity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'document_type', 'document_id', 'action', 'actor_type', 'actor_id',
        'actor_name', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceStatusHistory extends Model
{
    protected $fillable = [
        'document_type', 'document_id', 'from_status', 'to_status', 'action',
        'reason', 'metadata', 'actor_id', 'created_by',
    ];

    protected $casts = ['metadata' => 'array'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReminder extends Model
{
    protected $fillable = [
        'invoice_id', 'days_offset', 'is_active', 'last_sent_at', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'invoice_id');
    }
}

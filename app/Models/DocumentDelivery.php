<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentDelivery extends Model
{
    protected $fillable = [
        'document_type', 'document_id', 'delivery_type', 'recipient', 'cc',
        'bcc', 'subject', 'message', 'status', 'failure_reason', 'sent_at',
        'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}

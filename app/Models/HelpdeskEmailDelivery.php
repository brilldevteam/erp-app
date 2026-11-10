<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpdeskEmailDelivery extends Model
{
    protected $fillable = [
        'delivery_key', 'ticket_id', 'event', 'template', 'recipients', 'payload',
        'status', 'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'payload' => 'array', 'sent_at' => 'datetime'];
    }
}

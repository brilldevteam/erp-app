<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskPopupNotification extends Model
{
    protected $fillable = ['user_id', 'ticket_id', 'event', 'message', 'viewed_at', 'dismissed_at'];

    protected function casts(): array
    {
        return ['viewed_at' => 'datetime', 'dismissed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }
}

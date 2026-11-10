<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskStatusHistory extends Model
{
    protected $fillable = ['ticket_id', 'from_status', 'to_status', 'changed_by', 'changed_by_name'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }
}

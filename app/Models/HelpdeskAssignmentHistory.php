<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskAssignmentHistory extends Model
{
    protected $fillable = ['ticket_id', 'from_agent_id', 'to_agent_id', 'changed_by', 'from_agent_name', 'to_agent_name', 'changed_by_name'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(HelpdeskTicket::class, 'ticket_id');
    }
}

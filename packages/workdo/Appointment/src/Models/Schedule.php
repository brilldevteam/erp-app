<?php

namespace Workdo\Appointment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'unique_id',
        'user_id',
        'name',
        'email',
        'phone',
        'date',
        'start_time',
        'end_time',
        'appointment_id',
        'questions',
        'cancel_description',
        'status',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace Workdo\Appointment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentCallback extends Model
{
    protected $fillable = [
        'schedule_id',
        'unique_code',
        'user_id',
        'appointment_id',
        'reason',
        'date',
        'start_time',
        'end_time',
        'status',
        'creator_id',
        'created_by'
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}

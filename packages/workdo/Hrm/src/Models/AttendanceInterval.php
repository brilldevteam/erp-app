<?php

namespace Workdo\Hrm\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceInterval extends Model
{
    protected $fillable = [
        'attendance_id', 'reason', 'details', 'counts_as_work',
        'started_at', 'ended_at', 'created_by_user',
    ];

    protected $casts = [
        'counts_as_work' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}

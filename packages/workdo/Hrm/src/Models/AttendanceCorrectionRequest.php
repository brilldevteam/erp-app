<?php

namespace Workdo\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionRequest extends Model
{
    protected $fillable = [
        'attendance_id', 'requester_id', 'original_clock_in', 'original_clock_out', 'requested_clock_in',
        'requested_clock_out', 'reason', 'status', 'reviewed_by',
        'decision_note', 'reviewed_at', 'created_by',
    ];

    protected $casts = [
        'requested_clock_in' => 'datetime',
        'requested_clock_out' => 'datetime',
        'original_clock_in' => 'datetime',
        'original_clock_out' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

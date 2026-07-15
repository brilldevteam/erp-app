<?php

namespace Workdo\Hrm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\Shift;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'shift_id',
        'date',
        'clock_in',
        'clock_out',
        'break_hour',
        'total_hour',
        'overtime_hours',
        'overtime_amount',
        'status',
        'notes',
        'work_status',
        'elapsed_seconds',
        'unpaid_pause_seconds',
        'paid_outside_seconds',
        'worked_seconds',
        'work_update',
        'is_abnormally_long',
        'is_manual',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'shift_id' => 'integer',
            'date' => 'date',
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
            'break_hour' => 'decimal:2',
            'total_hour' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'overtime_amount' => 'decimal:2',
            'is_abnormally_long' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }



    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }


    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function intervals()
    {
        return $this->hasMany(AttendanceInterval::class)->orderBy('started_at');
    }

    public function correctionRequests()
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function actionLogs()
    {
        return $this->hasMany(AttendanceActionLog::class)->with('actor')->orderBy('created_at');
    }

    /**
     * Process complete attendance - calculate everything automatically.
     */

}

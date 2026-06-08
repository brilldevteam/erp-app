<?php

namespace Workdo\Appointment\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentHour extends Model
{
    protected $fillable = [
        'day_name',
        'start_time',
        'end_time',
        'day_off',
        'creator_id',
        'created_by',
    ];

    protected $casts = [
        'day_off' => 'boolean',
    ];

    public static $weekdays = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public static function dayWiseData($day)
    {
        return AppointmentHour::where('created_by',creatorId())->where('day_name',$day)->first();
    }
}
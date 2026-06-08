<?php

namespace Workdo\Appointment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppointmentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'creator_id',
        'created_by',
    ];
}

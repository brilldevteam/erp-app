<?php

namespace Workdo\Hrm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AttendanceActionLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['attendance_id', 'actor_id', 'action', 'metadata', 'created_by', 'created_at'];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

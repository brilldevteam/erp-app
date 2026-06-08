<?php

namespace Workdo\Appointment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Workdo\Appointment\Models\Schedule;

class AppointmentCallback
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Schedule $schedule
    ) {}
}
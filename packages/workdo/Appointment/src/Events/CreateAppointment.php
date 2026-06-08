<?php

namespace Workdo\Appointment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Workdo\Appointment\Models\Appointment;

class CreateAppointment
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Appointment $appointment
    ) {}
}
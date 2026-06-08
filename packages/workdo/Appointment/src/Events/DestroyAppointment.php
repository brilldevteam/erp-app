<?php

namespace Workdo\Appointment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Appointment\Models\Appointment;

class DestroyAppointment
{
    use Dispatchable;

    public function __construct(
        public Appointment $appointment
    ) {}
}
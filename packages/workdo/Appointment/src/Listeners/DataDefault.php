<?php

namespace Workdo\Appointment\Listeners;

use App\Events\DefaultData;
use Workdo\Appointment\Models\Appointment;

class DataDefault
{
    public function handle(DefaultData $event)
    {
        $company_id = $event->company_id;
        $user_module = $event->user_module ? explode(',', $event->user_module) : [];
        
        if (!empty($user_module) && in_array("Appointment", $user_module)) {
            Appointment::defaultdata($company_id);
        }
    }
}
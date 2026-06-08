<?php

namespace Workdo\Appointment\Listeners;

use App\Events\GivePermissionToRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Workdo\Appointment\Models\Appointment;

class GiveRoleToPermission
{
    public function __construct()
    {
        //
    }

    public function handle(GivePermissionToRole $event)
    {
        $role_id = $event->role_id;
        $rolename = $event->rolename;
        $user_module = $event->user_module ? explode(',', $event->user_module) : [];
        if (!empty($user_module)) {
            if (in_array("Appointment", $user_module)) {
                Appointment::GivePermissionToRoles($role_id, $rolename);
            }
        }
    }
}
<?php

namespace Workdo\ContractTemplate\Listeners;

use App\Events\GivePermissionToRole;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Workdo\ContractTemplate\Models\ContractTemplateUtility;

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
            if (in_array("ContractTemplate", $user_module)) {
                ContractTemplateUtility::givePermissionToRoles($role_id, $rolename);
            }
        }
    }
}
<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Hrm\Events\CreateLeaveApplication;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateLeaveApplicationLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateLeaveApplication $event)
    {
        $leaveApplication = $event->leaveapplication;

        $data = [
            'Leave Type' => $leaveApplication->leave_type_id,
            'Employee' => $leaveApplication->employee_id,
        ];

        WorkflowActionService::processWorkflow('Hrm', 'Leave', $data, $leaveApplication->created_by);
    }
}

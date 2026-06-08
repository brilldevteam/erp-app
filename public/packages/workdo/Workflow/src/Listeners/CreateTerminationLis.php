<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Hrm\Events\CreateTermination;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateTerminationLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateTermination $event)
    {
        $termination = $event->termination;

        $data = [
            'Termination Type' => $termination->termination_type_id,
            'Employee' => $termination->employee_id,
        ];

        WorkflowActionService::processWorkflow('Hrm', 'Terminations', $data, $termination->created_by);
    }
}

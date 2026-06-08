<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Hrm\Events\CreateAward;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateAwardLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateAward $event)
    {
        $award = $event->award;

        $data = [
            'Award Type' => $award->award_type_id,
            'Employee' => $award->employee_id,
        ];

        WorkflowActionService::processWorkflow('Hrm', 'Award', $data, $award->created_by);
    }
}

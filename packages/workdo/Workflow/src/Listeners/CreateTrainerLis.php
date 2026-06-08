<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Training\Events\CreateTrainer;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateTrainerLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateTrainer $event)
    {
        $trainer = $event->trainer;
        $data = [
            'Branch' => $trainer->branch_id,
            'Department' => $trainer->department_id,
            'Experience' => $trainer->experience,
        ];
            WorkflowActionService::processWorkflow('Training', 'Trainers', $data, $trainer->created_by);
    }
}

<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Taskly\Events\CreateProject;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateProjectLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateProject $event)
    {
        $project = $event->project;

        $data = [
            'Team Member' => $project->teamMembers->pluck('id')->toArray(),
            'Budget' => $project->budget,
        ];
        WorkflowActionService::processWorkflow('Taskly', 'Project', $data, $project->created_by);
    }
}

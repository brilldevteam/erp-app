<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Lead\Events\CreateLead;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateLeadLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateLead $event)
    {
        $lead = $event->lead;

        $data = [
            'Email' => $lead->email,
            'Lead User' => $lead->user_id,
            'Pipeline' => $lead->pipeline_id,
        ];

        WorkflowActionService::processWorkflow('Lead', 'Lead', $data, $lead->created_by);
    }
}

<?php

namespace Workdo\Workflow\Events;

use App\Models\EmailTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Workflow\Models\Workflow;
use Workdo\Workflow\Models\WorkflowAction;

class WorkflowEmailAction
{
    use Dispatchable;

    public function __construct(public WorkflowAction $action, public Workflow $workflow)
    {
        EmailTemplate::workflowsendEmail($action, $workflow);
    }
}

<?php

namespace Workdo\Workflow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Workflow\Models\WorkflowAction;

class WorkflowTelegramAction
{
    use Dispatchable;

    public function __construct(
        public WorkflowAction $action,
    ) {}
}

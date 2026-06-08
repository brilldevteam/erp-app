<?php

namespace Workdo\Workflow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Workflow\Models\Workflow;

class DestroyWorkflow
{
    use Dispatchable;

    public function __construct(
        public Workflow $workflow,
    ) {}
}

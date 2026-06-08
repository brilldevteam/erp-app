<?php

namespace Workdo\Workflow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Workdo\Workflow\Models\Workflow;

class UpdateWorkflow
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Workflow $workflow,
    ) {}
}

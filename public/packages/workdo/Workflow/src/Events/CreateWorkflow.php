<?php

namespace Workdo\Workflow\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Workdo\Workflow\Models\Workflow; 

class CreateWorkflow
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Workflow $workflow,
    ) {}
}

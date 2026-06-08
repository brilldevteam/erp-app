<?php

namespace Workdo\Workflow\Listeners;

use App\Events\CreateSalesReturn;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateSalesReturnLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateSalesReturn $event)
    {
        $return = $event->return;

        $data = [
            'Customer' => $return->customer_id,
        ];

        WorkflowActionService::processWorkflow('General', 'Sales Invoice Return', $data, $return->created_by);
    }
}

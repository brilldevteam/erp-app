<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Account\Events\CreateRevenue;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateRevenueLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateRevenue $event)
    {
        $revenue = $event->revenue;

        $data = [
            'Category' => $revenue->category_id,
            'Amount' => $revenue->amount,
        ];

        WorkflowActionService::processWorkflow('Account', 'Revenue', $data, $revenue->created_by);
    }
}

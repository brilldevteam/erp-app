<?php

namespace Workdo\Workflow\Listeners;

use App\Events\CreatePurchaseReturn;
use Workdo\Workflow\Services\WorkflowActionService;

class CreatePurchaseReturnLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreatePurchaseReturn $event)
    {
        $return = $event->return;

        $data = [
            'Vendor' => $return->vendor_id,
        ];

        WorkflowActionService::processWorkflow('General', 'Sales Purchase Return', $data, $return->created_by);
    }
}

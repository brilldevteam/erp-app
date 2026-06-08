<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Pos\Events\CreatePos;
use Workdo\Workflow\Services\WorkflowActionService;

class CreatePosLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreatePos $event)
    {
        $pos = $event->posSale;
        $pos->load('payment');

        $data = [
            'Pos Customer' => $pos->customer_id,
            'Warehouse' => $pos->warehouse_id,
            'Price' => $pos->payment?->discount_amount ?? 0,
        ];

        WorkflowActionService::processWorkflow('Pos', 'POS Order', $data, $pos->created_by);
    }
}

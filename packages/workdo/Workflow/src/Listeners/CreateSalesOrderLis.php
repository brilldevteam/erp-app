<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Sales\Events\CreateSalesOrder;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateSalesOrderLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateSalesOrder $event)
    {
        $salesOrder = $event->salesOrder;
       
        $data = [
            'Warehouse' => $salesOrder->warehouse_id,
            'Quote' => $salesOrder->quote_id,
            'Price' => $salesOrder->total_amount,
        ];
        
        WorkflowActionService::processWorkflow('Sales', 'Sales Order', $data, $salesOrder->created_by);
    }
}

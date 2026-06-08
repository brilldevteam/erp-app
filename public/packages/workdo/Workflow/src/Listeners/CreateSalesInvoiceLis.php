<?php

namespace Workdo\Workflow\Listeners;

use App\Events\CreateSalesInvoice;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateSalesInvoiceLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateSalesInvoice $event)
    {
        $invoice = $event->salesInvoice;

        $data = [
            'Customer' => $invoice->customer_id,
            'Total amount' => $invoice->total_amount,
            'Tax' => $invoice->items->pluck('taxes.*.id')->flatten()->unique()->toArray(),
        ];

        WorkflowActionService::processWorkflow('General', 'Sales Invoice', $data, $invoice->created_by);
    }
}

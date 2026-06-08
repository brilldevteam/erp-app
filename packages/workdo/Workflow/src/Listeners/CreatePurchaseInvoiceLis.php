<?php

namespace Workdo\Workflow\Listeners;

use App\Events\CreatePurchaseInvoice;
use Workdo\Workflow\Services\WorkflowActionService;

class CreatePurchaseInvoiceLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreatePurchaseInvoice $event)
    {
        $invoice = $event->purchaseInvoice;

        $data = [
            'Vendor' => $invoice->vendor_id,
            'Total amount' => $invoice->total_amount,
            'Tax' => $invoice->items->pluck('taxes.*.id')->flatten()->unique()->toArray(),
        ];

        WorkflowActionService::processWorkflow('General', 'Purchase Invoice', $data, $invoice->created_by);
    }
}

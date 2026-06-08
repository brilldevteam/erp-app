<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Account\Events\CreateVendorPayment;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateVendorPaymentLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateVendorPayment $event)
    {
        $vendorPayment = $event->vendorPayment;

        $data = [
            'Vendor' => $vendorPayment->vendor_id,
            'Total Payment' => $vendorPayment->payment_amount,
        ];

        WorkflowActionService::processWorkflow('Account', 'Vendor Payment', $data, $vendorPayment->created_by);
    }
}

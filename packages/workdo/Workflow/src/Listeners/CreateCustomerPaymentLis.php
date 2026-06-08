<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Account\Events\CreateCustomerPayment;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateCustomerPaymentLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateCustomerPayment $event)
    {
        $customerPayment = $event->customerPayment;

        $data = [
            'Customer' => $customerPayment->customer_id,
            'Total Payment' => $customerPayment->payment_amount,
        ];

        WorkflowActionService::processWorkflow('Account', 'Customer Payment', $data, $customerPayment->created_by);
    }
}

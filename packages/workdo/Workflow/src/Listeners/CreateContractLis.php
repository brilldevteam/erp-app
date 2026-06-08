<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Contract\Events\CreateContract;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateContractLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateContract $event)
    {
        $contract = $event->contract;

        $data = [
            'Contract Type' => $contract->type_id,
            'Contract Users' => $contract->user_id,
            'Price' => $contract->value,
        ];

        WorkflowActionService::processWorkflow('Contract', 'Contract', $data, $contract->created_by);
    }
}

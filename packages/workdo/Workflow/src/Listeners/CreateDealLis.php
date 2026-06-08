<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Lead\Events\CreateDeal;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateDealLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateDeal $event)
    {
        $deal = $event->deal;
        $deal->load('clientDeals.client');
        $data = [
            'Price' => $deal->price,
            'Client' => $deal->clientDeals->pluck('client_id')->toArray(),
            'Pipeline' => $deal->pipeline_id,
        ];
        WorkflowActionService::processWorkflow('Lead', 'Deal', $data, $deal->created_by);
    }
}

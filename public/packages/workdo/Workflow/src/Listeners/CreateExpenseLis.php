<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Account\Events\CreateExpense;
use Workdo\Workflow\Services\WorkflowActionService;

class CreateExpenseLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateExpense $event)
    {
        $expense = $event->expense;

        $data = [
            'Category' => $expense->category_id,
            'Amount' => $expense->amount,
        ];

        WorkflowActionService::processWorkflow('Account', 'Expense', $data, $expense->created_by);
    }
}

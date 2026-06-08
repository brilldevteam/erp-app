<?php

namespace Workdo\Workflow\Database\Seeders;

use Illuminate\Database\Seeder;
use Workdo\Workflow\Models\Workflow;
use Workdo\Workflow\Models\WorkflowCondition;
use Workdo\Workflow\Models\WorkflowAction;

class DemoWorkflowSeeder extends Seeder
{
    public function run($userId): void
    {
        if (Workflow::where('created_by', $userId)->exists()) {
            return;
        }

        $workflows = [
            [
                'name' => 'High Value Invoice Alert',
                'module' => 'General',
                'submodule' => 'Sales Invoice',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Total amount', 'operator' => '>', 'value' => '10000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'admin@example.com'], 'message' => 'A high value invoice has been created with amount greater than 10000.']
                ]
            ],
            [
                'name' => 'Project Budget Notification',
                'module' => 'Taskly',
                'submodule' => 'Project',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Budget', 'operator' => '>=', 'value' => '50000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'manager@example.com'], 'message' => 'A new project with budget 50000 or more has been created.']
                ]
            ],
            [
                'name' => 'Large Customer Payment',
                'module' => 'Account',
                'submodule' => 'Customer Payment',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Total Payment', 'operator' => '>', 'value' => '5000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'finance@example.com'], 'message' => 'Customer payment received exceeding 5000.']
                ]
            ],
            [
                'name' => 'High Expense Alert',
                'module' => 'Account',
                'submodule' => 'Expense',
                'is_active' => 0,
                'conditions' => [
                    ['field' => 'Amount', 'operator' => '>', 'value' => '3000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'accounts@example.com'], 'message' => 'An expense exceeding 3000 has been recorded.']
                ]
            ],
            [
                'name' => 'New Lead Notification',
                'module' => 'Lead',
                'submodule' => 'Lead',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Email', 'operator' => '=', 'value' => 'test@example.com']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'sales@example.com'], 'message' => 'A new lead has been created in the system.']
                ]
            ],
            [
                'name' => 'High Value Deal Alert',
                'module' => 'Lead',
                'submodule' => 'Deal',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Price', 'operator' => '>=', 'value' => '100000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'sales@example.com'], 'message' => 'A high value deal worth 100000 or more has been created.']
                ]
            ],
            [
                'name' => 'Employee Award Notification',
                'module' => 'Hrm',
                'submodule' => 'Award',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Award Type', 'operator' => '=', 'value' => '1']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'hr@example.com'], 'message' => 'An employee award has been created.']
                ]
            ],
            [
                'name' => 'Contract High Value Alert',
                'module' => 'Contract',
                'submodule' => 'Contract',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Price', 'operator' => '>', 'value' => '50000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'contracts@example.com'], 'message' => 'A high value contract exceeding 50000 has been created.']
                ]
            ],
            [
                'name' => 'Experienced Trainer Alert',
                'module' => 'Training',
                'submodule' => 'Trainers',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Experience', 'operator' => '>=', 'value' => '10']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'training@example.com'], 'message' => 'A highly experienced trainer with 10+ years has been added.']
                ]
            ],
            [
                'name' => 'Long Stay Booking Alert',
                'module' => 'Holidayz',
                'submodule' => 'Room Booking',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Days', 'operator' => '>=', 'value' => '7']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'hotel@example.com'], 'message' => 'A long stay booking of 7 days or more has been created.']
                ]
            ],
            [
                'name' => 'Premium Room Booking',
                'module' => 'Holidayz',
                'submodule' => 'Room Booking',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Price', 'operator' => '>', 'value' => '5000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'hotel@example.com'], 'message' => 'A premium room booking exceeding 5000 has been made.']
                ]
            ],
            [
                'name' => 'Large Sales Order Alert',
                'module' => 'Sales',
                'submodule' => 'Sales Order',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Price', 'operator' => '>=', 'value' => '25000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'sales@example.com'], 'message' => 'A large sales order worth 25000 or more has been created.']
                ]
            ],
            [
                'name' => 'High Revenue Notification',
                'module' => 'Account',
                'submodule' => 'Revenue',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Amount', 'operator' => '>', 'value' => '15000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'finance@example.com'], 'message' => 'Revenue exceeding 15000 has been recorded.']
                ]
            ],
            [
                'name' => 'Large Vendor Payment',
                'module' => 'Account',
                'submodule' => 'Vendor Payment',
                'is_active' => 0,
                'conditions' => [
                    ['field' => 'Total Payment', 'operator' => '>=', 'value' => '8000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'accounts@example.com'], 'message' => 'Vendor payment of 8000 or more has been processed.']
                ]
            ],
            [
                'name' => 'Purchase Invoice Alert',
                'module' => 'General',
                'submodule' => 'Purchase Invoice',
                'is_active' => 1,
                'conditions' => [
                    ['field' => 'Total amount', 'operator' => '>', 'value' => '20000']
                ],
                'actions' => [
                    ['type' => 'Email', 'config' => ['to' => 'purchase@example.com'], 'message' => 'A purchase invoice exceeding 20000 has been created.']
                ]
            ]
        ];

        foreach ($workflows as $workflowData) {
            $workflow = Workflow::create([
                'name' => $workflowData['name'],
                'module' => $workflowData['module'],
                'submodule' => $workflowData['submodule'],
                'is_active' => $workflowData['is_active'],
                'creator_id' => $userId,
                'created_by' => $userId,
            ]);

            foreach ($workflowData['conditions'] as $conditionData) {
                WorkflowCondition::create([
                    'workflow_id' => $workflow->id,
                    'field' => $conditionData['field'],
                    'operator' => $conditionData['operator'],
                    'value' => $conditionData['value'],
                ]);
            }

            foreach ($workflowData['actions'] as $actionData) {
                WorkflowAction::create([
                    'workflow_id' => $workflow->id,
                    'type' => $actionData['type'],
                    'config' => $actionData['config'],
                    'message' => $actionData['message'],
                ]);
            }
        }
    }
}

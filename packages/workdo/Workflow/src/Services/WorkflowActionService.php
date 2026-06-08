<?php

namespace Workdo\Workflow\Services;

use Workdo\Workflow\Models\Workflow;
use Workdo\Workflow\Events\WorkflowEmailAction;
use Workdo\Workflow\Events\WorkflowTwilioAction;
use Workdo\Workflow\Events\WorkflowTelegramAction;
use Workdo\Workflow\Events\WorkflowSlackAction;
use Workdo\Workflow\Events\WorkflowWhatsAppAPIAction;

class WorkflowActionService
{
    public static function processWorkflow($module, $submodule, $data, $createdBy)
    {
        if (module_is_active('Workflow'))
        {
                $workflows = Workflow::where('module', $module)
                ->where('submodule', $submodule)
                ->where('is_active', 1)
                ->where('created_by', $createdBy)
                ->with(['conditions', 'actions'])
                ->get();
            foreach ($workflows as $workflow) {
                if (self::checkConditions($workflow->conditions, $data)) {
                    foreach ($workflow->actions as $action) {
                        if (module_is_active($action->type) || $action->type == 'Email') {
                            self::dispatchAction($action, $workflow);
                        }
                    }
                }
            }
        }
        else
        {
            return;
        }
    }

    private static function checkConditions($conditions, $data)
    {
        foreach ($conditions as $condition) {
            if (!self::evaluateCondition($condition, $data)) {
                return false;
            }
        }
        return true;
    }

    private static function evaluateCondition($condition, $data)
    {
        $fieldValue = $data[$condition->field] ?? null;
        $isArray = is_array($fieldValue);
        
        switch ($condition->operator) {
            case '=':
                return $isArray ? in_array($condition->value, $fieldValue) : $fieldValue == $condition->value;
            case '!=':
                return $isArray ? !in_array($condition->value, $fieldValue) : $fieldValue != $condition->value;
            case '>':
                return !$isArray && $fieldValue > $condition->value;
            case '<':
                return !$isArray && $fieldValue < $condition->value;
            case '>=':
                return !$isArray && $fieldValue >= $condition->value;
            case '<=':
                return !$isArray && $fieldValue <= $condition->value;
            default:
                return false;
        }
    }

    private static function dispatchAction($action, $workflow)
    {
        switch ($action->type) {
            case 'Email':
                WorkflowEmailAction::dispatch($action, $workflow);
                break;
            case 'Twilio':
                WorkflowTwilioAction::dispatch($action);
                break;
            case 'Telegram':
                WorkflowTelegramAction::dispatch($action);
                break;
            case 'Slack':
                WorkflowSlackAction::dispatch($action);
                break;
            case 'WhatsAppAPI':
                WorkflowWhatsAppAPIAction::dispatch($action);
                break;
        }
    }
}

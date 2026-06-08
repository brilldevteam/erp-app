<?php

namespace Workdo\Workflow\Services;

class WorkflowModuleService
{
    public static function getModules()
    {
        $allModules = [
            [
                'module' => 'General',
                'submodule' => 'Sales Invoice',
                'fields' => [
                    ['name' => 'Customer', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Total amount', 'type' => 'number'],
                    ['name' => 'Tax', 'type' => 'select', 'model_name' => 'ProductServiceTax'],
                ],
            ],
            [
                'module' => 'General',
                'submodule' => 'Sales Invoice Return',
                'fields' => [
                    ['name' => 'Customer', 'type' => 'select', 'model_name' => 'User'],
                ],
            ],
            [
                'module' => 'General',
                'submodule' => 'Purchase Invoice',
                'fields' => [
                    ['name' => 'Vendor', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Total amount', 'type' => 'number'],
                    ['name' => 'Tax', 'type' => 'select', 'model_name' => 'ProductServiceTax'],
                ],
            ],
            [
                'module' => 'General',
                'submodule' => 'Sales Purchase Return',
                'fields' => [
                    ['name' => 'Vendor', 'type' => 'select', 'model_name' => 'User'],
                ],
            ],
            [
                'module' => 'Taskly',
                'submodule' => 'Project',
                'fields' => [
                    ['name' => 'Team Member', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Budget', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Account',
                'submodule' => 'Customer Payment',
                'fields' => [
                    ['name' => 'Customer', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Total Payment', 'type' => 'number'],

                ],
            ],
            [
                'module' => 'Account',
                'submodule' => 'Vendor Payment',
                'fields' => [
                    ['name' => 'Vendor', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Total Payment', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Account',
                'submodule' => 'Revenue',
                'fields' => [
                    ['name' => 'Category', 'type' => 'select', 'model_name' => 'RevenueCategories'],
                    ['name' => 'Amount', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Account',
                'submodule' => 'Expense',
                'fields' => [
                    ['name' => 'Category', 'type' => 'select', 'model_name' => 'ExpenseCategories'],
                    ['name' => 'Amount', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Lead',
                'submodule' => 'Lead',
                'fields' => [
                    ['name' => 'Email', 'type' => 'email'],
                    ['name' => 'Lead User', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Pipeline', 'type' => 'select', 'model_name' => 'Pipeline'],
                ],
            ],
            [
                'module' => 'Lead',
                'submodule' => 'Deal',
                'fields' => [
                    ['name' => 'Price', 'type' => 'number'],
                    ['name' => 'Client', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Pipeline', 'type' => 'select', 'model_name' => 'Pipeline'],
                ],
            ],
            [
                'module' => 'Hrm',
                'submodule' => 'Award',
                'fields' => [
                    ['name' => 'Award Type', 'type' => 'select', 'model_name' => 'AwardType'],
                    ['name' => 'Employee', 'type' => 'select', 'model_name' => 'User'],
                ],
            ],
            [
                'module' => 'Hrm',
                'submodule' => 'Terminations',
                'fields' => [
                    ['name' => 'Termination Type', 'type' => 'select', 'model_name' => 'TerminationType'],
                    ['name' => 'Employee', 'type' => 'select', 'model_name' => 'User'],
                ],
            ],
            [
                'module' => 'Hrm',
                'submodule' => 'Leave',
                'fields' => [
                    ['name' => 'Leave Type', 'type' => 'select', 'model_name' => 'LeaveType'],
                    ['name' => 'Employee', 'type' => 'select', 'model_name' => 'User'],
                ],
            ],
            [
                'module' => 'Pos',
                'submodule' => 'POS Order',
                'fields' => [
                    ['name' => 'Pos Customer', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Warehouse', 'type' => 'select', 'model_name' => 'Warehouse'],
                    ['name' => 'Price', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Contract',
                'submodule' => 'Contract',
                'fields' => [
                    ['name' => 'Contract Type', 'type' => 'select', 'model_name' => 'ContractType'],
                    ['name' => 'Contract Users', 'type' => 'select', 'model_name' => 'User'],
                    ['name' => 'Price', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Training',
                'submodule' => 'Trainers',
                'fields' => [
                    ['name' => 'Branch', 'type' => 'select', 'model_name' => 'Branch'],
                    ['name' => 'Department', 'type' => 'select', 'model_name' => 'Department'],
                    ['name' => 'Experience', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Holidayz',
                'submodule' => 'Room Booking',
                'fields' => [
                    ['name' => 'Customer', 'type' => 'select', 'model_name' => 'HolidayzHotelCustomer'],
                    ['name' => 'Price', 'type' => 'number'],
                    ['name' => 'Days', 'type' => 'number'],
                ],
            ],
            [
                'module' => 'Sales',
                'submodule' => 'Sales Order',
                'fields' => [
                    ['name' => 'Warehouse', 'type' => 'select', 'model_name' => 'Warehouse'],
                    ['name' => 'Quote', 'type' => 'select', 'model_name' => 'SalesQuote'],
                    ['name' => 'Price', 'type' => 'number'],
                ],
            ],
        ];

        return array_values(array_filter($allModules, function($module) {
            $moduleName = $module['module'];
            if ($moduleName === 'General') {
                return true;
            }
            return Module_is_active($moduleName);
        }));
    }

    public static function getModulesWithAlias()
    {
        $modules = self::getModules();
        return array_map(function($module) {
            $module['module_alias'] = ModuleAliasName($module['module']);
            return $module;
        }, $modules);
    }
}

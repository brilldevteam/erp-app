<?php

namespace Workdo\Workflow\Http\Controllers;

use Workdo\Workflow\Http\Requests\StoreWorkflowRequest;
use Workdo\Workflow\Http\Requests\UpdateWorkflowRequest;
use App\Models\User;
use App\Models\Warehouse;
use Workdo\Workflow\Models\Workflow;
use Workdo\Workflow\Models\WorkflowAction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\ExpenseCategories;
use Workdo\Account\Models\RevenueCategories;
use Workdo\Hrm\Models\AwardType;
use Workdo\Hrm\Models\Employee;
use Workdo\Hrm\Models\LeaveType;
use Workdo\Hrm\Models\TerminationType;
use Workdo\Lead\Models\Pipeline;
use Workdo\ProductService\Models\ProductServiceTax;
use Workdo\Workflow\Events\CreateWorkflow;
use Workdo\Workflow\Events\UpdateWorkflow;
use Workdo\Workflow\Models\WorkflowCondition;
use Workdo\Workflow\Services\WorkflowModuleService;
use Workdo\Workflow\Events\DestroyWorkflow;
use Workdo\Contract\Models\ContractType;
use Workdo\Hrm\Models\Branch;
use Workdo\Hrm\Models\Department;
use Workdo\Holidayz\Models\HolidayzHotelCustomer;
use Workdo\Sales\Models\SalesQuote;

class WorkflowController extends Controller
{
    public function index()
    {
        if(Auth::user()->can('manage-workflow')){
            $workflows = Workflow::query()
                ->withCount(['conditions', 'actions'])
                ->where(function($q) {
                    if(Auth::user()->can('manage-any-workflow')) {
                        $q->where('created_by', creatorId());
                    } elseif(Auth::user()->can('manage-own-workflow')) {
                        $q->where('creator_id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->when(request('search'), function($q) {
                    $q->where('name', 'like', '%' . request('search') . '%');
                })
                ->when(request('module') && request('module') !== 'all', fn($q) => $q->where('module', request('module')))
                ->when(request('submodule') && request('submodule') !== 'all', fn($q) => $q->where('submodule', request('submodule')))
                ->when(request('status') && request('status') !== 'all', function($q) {
                    $q->where('is_active', request('status') === 'active' ? 1 : 0);
                })
                ->when(request('sort'), fn($q) => $q->orderBy(request('sort'), request('direction', 'asc')), fn($q) => $q->latest())
                ->paginate(request('per_page', 10))
                ->withQueryString();

            $modules = WorkflowModuleService::getModulesWithAlias();

            return Inertia::render('Workflow/Workflows/Index', [
                'workflows' => $workflows,
                'modules' => $modules,
            ]);
        }else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function create()
    {
        if(Auth::user()->can('create-workflow')){
            $modules = WorkflowModuleService::getModulesWithAlias();
            return Inertia::render('Workflow/Workflows/Create', [
                'modules' => $modules,
            ]);
        }else{
             return redirect()->route('workflow.index')->with('error', __('Permission denied'));
        }
    }

    public function store(StoreWorkflowRequest $request)
    {
        if(Auth::user()->can('create-workflow')){
            $workflow = new Workflow();
            $workflow->name = $request->name;
            $workflow->module = $request->module;
            $workflow->submodule = $request->submodule;
            $workflow->created_by = creatorId();
            $workflow->creator_id = Auth::id();
            $workflow->save();

            // Create workflow conditions
            foreach ($request->conditions as $conditionData) {
                $condition = new WorkflowCondition();
                $condition->workflow_id = $workflow->id;
                $condition->field = $conditionData['field'];
                $condition->operator = $conditionData['operator'];
                $condition->value = $conditionData['value'];
                $condition->save();
            }

            // Create workflow actions
            $actionData = $request->actions;
            $types = $actionData['types'] ?? [];
            $configs = $actionData['configs'] ?? [];

            foreach ($types as $actionType) {
                $config = $configs[$actionType] ?? [];
                $message = $config['message'] ?? '';

                // Remove message from config before storing
                unset($config['message']);

                $action = new WorkflowAction();
                $action->workflow_id = $workflow->id;
                $action->type = $actionType;
                $action->config = $config;
                $action->message = $message;
                $action->save();
            }

            CreateWorkflow::dispatch($request, $workflow);

            return redirect()->route('workflow.index')->with('success', __('The workflow has been created successfully.'));
        }else
        {
            return redirect()->route('workflow.index')->with('error', __('Permission denied'));
        }

    }

    public function edit(Workflow $workflow)
    {
        if(Auth::user()->can('edit-workflow') && ($workflow->created_by == creatorId())){
            $workflow->load(['conditions', 'actions']);

            // Transform actions to match frontend structure
            $actionTypes = $workflow->actions->pluck('type')->toArray();
            $actionConfigs = [];

            foreach ($workflow->actions as $action) {
                $config = $action->config ?? [];
                $config['message'] = $action->message;
                $actionConfigs[$action->type] = $config;
            }

            $workflow->actions_data = [
                'types' => $actionTypes,
                'configs' => $actionConfigs
            ];

            $modules = WorkflowModuleService::getModulesWithAlias();

            return Inertia::render('Workflow/Workflows/Edit', [
                'workflow' => $workflow,
                'modules' => $modules,
            ]);
        }else {
            return redirect()->route('workflow.index')->with('error', __('Permission denied'));
        }
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow)
    {
        if(Auth::user()->can('edit-workflow') && ($workflow->created_by == creatorId())){
            $workflow->name = $request->name;
            $workflow->module = $request->module;
            $workflow->submodule = $request->submodule;
            $workflow->is_active = $request->is_active ?? true;
            $workflow->save();

            // Delete existing conditions and actions
            $workflow->conditions()->delete();
            $workflow->actions()->delete();

            // Create new conditions
            foreach ($request->conditions as $conditionData) {
                $condition = new WorkflowCondition();
                $condition->workflow_id = $workflow->id;
                $condition->field = $conditionData['field'];
                $condition->operator = $conditionData['operator'];
                $condition->value = $conditionData['value'];
                $condition->save();
            }

            // Create new actions
            $actionData = $request->actions;
            $types = $actionData['types'] ?? [];
            $configs = $actionData['configs'] ?? [];

            foreach ($types as $actionType) {
                $config = $configs[$actionType] ?? [];
                $message = $config['message'] ?? '';

                unset($config['message']);

                $action = new WorkflowAction();
                $action->workflow_id = $workflow->id;
                $action->type = $actionType;
                $action->config = $config;
                $action->message = $message;
                $action->save();
            }

            UpdateWorkflow::dispatch($request, $workflow);

            return redirect()->route('workflow.index')->with('success', __('The workflow details are updated successfully.'));
        }else {
            return redirect()->route('workflow.index')->with('error', __('Permission denied'));
        }

    }

    public function destroy(Workflow $workflow)
    {
        if(Auth::user()->can('delete-workflow') && ($workflow->created_by == creatorId())){

            DestroyWorkflow::dispatch($workflow);

            $workflow->delete();
            return redirect()->back()->with('success', __('The workflow has been deleted.'));
        }else {
            return redirect()->route('workflow.index')->with('error', __('Permission denied'));
        }
    }

    public function getFieldValues(Request $request)
    {
        $model = $request->get('model');
        $field = $request->get('field');
            $values = collect([]);

        try {
            if($model == 'User' && $field == 'Team Member')
            {
                $values = User::where('created_by', creatorId())->emp()->select('id', 'name')->get();
            }
            elseif($model == 'User' && $field == 'Customer' || $model == 'User' && $field == 'Client')
            {
                $values = User::where('type', 'client')->where('created_by', creatorId())->select('id', 'name')->get();
            }
            elseif($model == 'ProductServiceTax' && $field == 'Tax')
            {
                $values = ProductServiceTax::where('created_by', '=', creatorId())->select('id', 'tax_name as name')->get();
            }
            elseif($model == 'User' && $field == 'Vendor')
            {
                $values = User::where('type', 'vendor')->where('created_by', creatorId())->select('id', 'name')->get();
            }
            elseif($model == 'RevenueCategories' && $field == 'Category')
            {
                $values = RevenueCategories::where('created_by', creatorId())->where('is_active', true)->select('id', 'category_name as name')->get();
            }
            elseif($model == 'ExpenseCategories' && $field == 'Category')
            {
                $values = ExpenseCategories::where('created_by', creatorId())->where('is_active', true)->select('id', 'category_name as name')->get();
            }
            elseif($model == 'User' && $field == 'Lead User')
            {
                $values = User::where('created_by', '=', creatorId())->emp([],['vendor'])->select('id', 'name')->get();
            }
            elseif($model == 'Pipeline' && $field == 'Pipeline')
            {
                $values = Pipeline::where('created_by', creatorId())->select('id', 'name')->get();
            }
            elseif($model == 'User' && $field == 'Employee')
            {
                $employeeQuery = Employee::where('created_by', creatorId());
                $employeeQuery->where(function ($q) {
                    $q->where('creator_id', Auth::id())->orWhere('user_id', Auth::id());
                });

                $values = User::emp()->where('created_by', creatorId())->whereIn('id', $employeeQuery->pluck('user_id'))->select('id', 'name')->get();
            }
            elseif($model == 'AwardType' && $field == 'Award Type')
            {
                $values = AwardType::where('created_by', creatorId())->select('id', 'name')->get();
            }
            elseif($model == 'TerminationType' && $field == 'Termination Type')
            {
                $values = TerminationType::where('created_by', creatorId())->select('id', 'termination_type  as name')->get();
            }
            elseif($model == 'LeaveType' && $field == 'Leave Type')
            {
                $values = LeaveType::where('created_by', creatorId())->select('id', 'name')->get();
            }
            elseif($model == 'Warehouse' && $field == 'Warehouse')
            {
                $values = Warehouse::where('created_by', creatorId())->where('is_active', true)->select('id', 'name')->get();
            }
            elseif($model == 'User' && $field == 'Pos Customer')
            {
                $values = User::whereHas('roles', function($query) {
                        $query->where('name', 'client');
                    })->where('created_by', creatorId())->select('id', 'name', 'email')->get();
            }
            elseif($model == 'ContractType' && $field == 'Contract Type')
            {
                $values = ContractType::where('created_by', creatorId())->where('is_active', true)->select('id', 'name')->get();
            }
            elseif($model == 'User' && $field == 'Contract Users')
            {
                $values = User::where('created_by', creatorId())->select('id', 'name')->get();
            }   
            elseif ($model == 'Branch' && $field == 'Branch')
            {
                $values = Branch::where('created_by', creatorId())->select('id', 'branch_name as name')->get();
            }  
            elseif ($model == 'Department' && $field == 'Department')
            {
                $values = Department::where('created_by', creatorId())->select('id', 'department_name as name')->get();
            }
            elseif ($model == 'HolidayzHotelCustomer' && $field == 'Customer')
            {
                $values = HolidayzHotelCustomer::where('created_by', creatorId())
                    ->selectRaw("id, CONCAT(first_name, ' ', last_name) as name")
                    ->get();
            }
            elseif ($model == 'SalesQuote' && $field == 'Quote')
            {
                $values = SalesQuote::where('created_by', creatorId())->select('id', 'name')->get();
            } 
            $values = $values->map(fn($item) => ['id' => $item->id, 'name' => $item->name])->values()->toArray();
        } catch (\Exception $e) {
            $values = [];
        }

        return response()->json(['values' => $values]);
    }

    public function getStaffList()
    {
        $staff = User::where('created_by', creatorId())
            ->select('id', 'name', 'email', 'mobile_no')
            ->get();

        return response()->json($staff);
    }
}

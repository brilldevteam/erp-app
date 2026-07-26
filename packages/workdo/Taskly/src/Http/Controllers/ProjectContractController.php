<?php

namespace Workdo\Taskly\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Vendor;
use Workdo\Taskly\Http\Requests\StoreProjectContractRequest;
use Workdo\Taskly\Http\Requests\UpdateProjectContractRequest;
use Workdo\Taskly\Models\ActivityLog;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectContract;

class ProjectContractController extends Controller
{
    public function index()
    {
        if (!Auth::user()->can('manage-project-contractors')) {
            return back()->with('error', __('Permission denied'));
        }

        $allowedSorts = [
            'scope_of_work', 'contract_value', 'work_start_date', 'completion_date', 'created_at',
        ];
        $sort = in_array(request('sort'), $allowedSorts, true) ? request('sort') : 'created_at';
        $direction = request('direction') === 'asc' ? 'asc' : 'desc';

        $type = in_array(request('type', 'main'), ['main', 'subcontractor'], true)
            ? request('type', 'main')
            : 'main';

        $contracts = ProjectContract::query()
            ->with([
                'project:id,name',
                'vendor:id,user_id,company_name,vendor_code',
                'vendor.user:id,name',
                'parentContract:id,vendor_id',
                'parentContract.vendor:id,company_name',
            ])
            ->withSum('purchaseInvoices as amount_paid', 'paid_amount')
            ->where('created_by', creatorId())
            ->whereHas('project', fn ($query) => $query->accessibleTo(Auth::user()))
            ->where('type', $type)
            ->when(request('project_id'), fn ($query, $projectId) => $query->where('project_id', $projectId))
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('scope_of_work', 'like', "%{$search}%")
                        ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(request('per_page', 10))
            ->withQueryString();

        $contracts->through(fn (ProjectContract $contract) => $this->serializeContract($contract));

        $projects = Project::query()
            ->accessibleTo(Auth::user())
            ->orderBy('name')
            ->get(['id', 'name']);

        $vendors = Vendor::where('created_by', creatorId())
            ->with('user:id,name')
            ->orderBy('company_name')
            ->get(['id', 'user_id', 'company_name', 'vendor_code']);

        $mainContracts = ProjectContract::query()
            ->where('created_by', creatorId())
            ->where('type', 'main')
            ->whereHas('project', fn ($query) => $query->accessibleTo(Auth::user()))
            ->with(['project:id,name', 'vendor:id,company_name'])
            ->orderBy('scope_of_work')
            ->get(['id', 'project_id', 'vendor_id', 'scope_of_work']);

        return Inertia::render('Taskly/Contractors/Index', [
            'contracts' => $contracts,
            'projects' => $projects,
            'vendors' => $vendors,
            'mainContracts' => $mainContracts,
            'filters' => [
                ...request()->only(['project_id', 'search', 'per_page', 'sort', 'direction']),
                'type' => $type,
            ],
        ]);
    }

    public function store(StoreProjectContractRequest $request)
    {
        if (!Auth::user()->can('create-project-contractors')) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        $project = $this->accessibleProject((int) $validated['project_id']);

        if (!$project) {
            return back()->withErrors(['project_id' => __('You do not have access to the selected project.')]);
        }

        if ($validated['type'] === 'main') {
            $validated['parent_contract_id'] = null;
        }

        $contract = ProjectContract::create([
            ...$validated,
            'creator_id' => Auth::id(),
            'created_by' => creatorId(),
        ]);

        $this->logActivity($contract, __('Added project contractor :name', ['name' => $contract->vendor->company_name]));

        return back()->with('success', __('The project contractor has been created successfully.'));
    }

    public function update(UpdateProjectContractRequest $request, ProjectContract $projectContract)
    {
        if (!Auth::user()->can('edit-project-contractors') || !$this->canAccess($projectContract)) {
            return back()->with('error', __('Permission denied'));
        }

        $validated = $request->validated();
        if (!$this->accessibleProject((int) $validated['project_id'])) {
            return back()->withErrors(['project_id' => __('You do not have access to the selected project.')]);
        }

        if ($projectContract->subcontractors()->exists()
            && ($validated['type'] !== 'main' || (int) $validated['project_id'] !== $projectContract->project_id)) {
            return back()->withErrors([
                'type' => __('A main contractor with linked subcontractors cannot change its type or project.'),
            ]);
        }

        if ($projectContract->purchaseInvoices()->exists()
            && (int) $validated['vendor_id'] !== $projectContract->vendor_id) {
            return back()->withErrors([
                'vendor_id' => __('The vendor cannot be changed while purchase invoices are linked to this contract.'),
            ]);
        }

        if ($validated['type'] === 'main') {
            $validated['parent_contract_id'] = null;
        }

        $projectContract->update($validated);
        $this->logActivity($projectContract, __('Updated project contractor :name', ['name' => $projectContract->vendor->company_name]));

        return back()->with('success', __('The project contractor has been updated successfully.'));
    }

    public function destroy(ProjectContract $projectContract)
    {
        if (!Auth::user()->can('delete-project-contractors') || !$this->canAccess($projectContract)) {
            return back()->with('error', __('Permission denied'));
        }

        if ($projectContract->purchaseInvoices()->exists()) {
            return back()->with('error', __('This contractor cannot be deleted because purchase invoices are linked to it.'));
        }

        if ($projectContract->subcontractors()->exists()) {
            return back()->with('error', __('This main contractor cannot be deleted while subcontractors are linked to it.'));
        }

        $projectId = $projectContract->project_id;
        $name = $projectContract->vendor->company_name;
        $projectContract->delete();

        ActivityLog::create([
            'user_id' => Auth::id(),
            'user_type' => get_class(Auth::user()),
            'project_id' => $projectId,
            'log_type' => 'Delete Project Contractor',
            'remark' => __('Deleted project contractor :name', ['name' => $name]),
        ]);

        return back()->with('success', __('The project contractor has been deleted successfully.'));
    }

    private function accessibleProject(int $projectId): ?Project
    {
        return Project::query()->accessibleTo(Auth::user())->find($projectId);
    }

    private function canAccess(ProjectContract $contract): bool
    {
        return $contract->created_by === creatorId() && (bool) $this->accessibleProject($contract->project_id);
    }

    private function serializeContract(ProjectContract $contract): array
    {
        $financials = $contract->financialSummary();

        return [
            'id' => $contract->id,
            'project_id' => $contract->project_id,
            'vendor_id' => $contract->vendor_id,
            'type' => $contract->type,
            'parent_contract_id' => $contract->parent_contract_id,
            'scope_of_work' => $contract->scope_of_work,
            ...$financials,
            'work_start_date' => $contract->work_start_date?->format('Y-m-d'),
            'completion_date' => $contract->completion_date?->format('Y-m-d'),
            'project' => $contract->project,
            'vendor' => $contract->vendor,
            'parent_contract' => $contract->parentContract,
        ];
    }

    private function logActivity(ProjectContract $contract, string $remark): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'user_type' => get_class(Auth::user()),
            'project_id' => $contract->project_id,
            'log_type' => 'Project Contractor',
            'remark' => $remark,
        ]);
    }
}

<?php

namespace Workdo\Taskly\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Account\Models\Vendor;
use Workdo\Taskly\Models\Project;
use Workdo\Taskly\Models\ProjectContract;

class StoreProjectContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')->where('created_by', creatorId())],
            'vendor_id' => ['required', 'integer', Rule::exists('vendors', 'id')->where('created_by', creatorId())],
            'type' => ['required', Rule::in(['main', 'subcontractor'])],
            'parent_contract_id' => ['nullable', 'integer', 'exists:project_contracts,id'],
            'scope_of_work' => ['required', 'string', 'max:255'],
            'contract_value' => ['required', 'numeric', 'min:0'],
            'work_start_date' => ['required', 'date'],
            'completion_date' => ['required', 'date', 'after_or_equal:work_start_date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = Project::where('created_by', creatorId())->find($this->integer('project_id'));
            $vendor = Vendor::where('created_by', creatorId())->find($this->integer('vendor_id'));

            if (!$project) {
                $validator->errors()->add('project_id', __('The selected project is not available.'));
            }
            if (!$vendor) {
                $validator->errors()->add('vendor_id', __('The selected vendor is not available.'));
            }

            if ($this->input('type') === 'main' && $this->filled('parent_contract_id')) {
                $validator->errors()->add('parent_contract_id', __('A main contractor cannot have a parent contractor.'));
            }

            if ($this->filled('parent_contract_id')) {
                $currentContract = $this->route('projectContract');
                if ($currentContract && (int) $currentContract->id === $this->integer('parent_contract_id')) {
                    $validator->errors()->add('parent_contract_id', __('A contractor cannot be its own parent contractor.'));
                    return;
                }

                $parent = ProjectContract::where('created_by', creatorId())
                    ->where('type', 'main')
                    ->find($this->integer('parent_contract_id'));

                if (!$parent || $parent->project_id !== $this->integer('project_id')) {
                    $validator->errors()->add('parent_contract_id', __('The parent contractor must be a main contractor from the selected project.'));
                }
            }
        });
    }
}

<?php

namespace Workdo\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'module' => 'required|string',
            'submodule' => 'required|string',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required|string',
            'actions' => 'required|array',
            'actions.types' => 'required|array|min:1',
            'actions.types.*' => 'required|string',
            'actions.configs' => 'required|array',
        ];
    }
}

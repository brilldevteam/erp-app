<?php

namespace Workdo\ContractTemplate\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractTemplateActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'user_id' => 'required|exists:users,id',
            'value' => 'required|numeric|min:0',
            'type_id' => 'required|exists:contract_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:draft,active,archived',
            'description' => 'nullable|string',
            'comments_duplicate' => 'nullable|boolean',
            'notes_duplicate' => 'nullable|boolean',
            'attachments_duplicate' => 'nullable|boolean',
        ];
    }
}
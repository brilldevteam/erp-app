<?php

namespace Workdo\PettyCashManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'         => 'required|date|unique:petty_cashes,date,NULL,id,created_by,' . creatorId(),
            'added_amount' => 'required|numeric',
            'remarks'      => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => __('Date is required.'),
            'date.date' => __('Please enter a valid date.'),
            'date.unique' => __('Petty cash fund already exists for this date.'),
            'added_amount.required' => __('Added amount is required.'),
            'added_amount.numeric' => __('Added amount must be a number.'),
            'added_amount.min' => __('Added amount cannot be negative.'),
        ];
    }
}

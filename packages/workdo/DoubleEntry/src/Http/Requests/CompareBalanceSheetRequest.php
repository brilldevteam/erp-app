<?php

namespace Workdo\DoubleEntry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompareBalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only the current company's balance sheets can be compared.
            'current_period_id' => ['required', Rule::exists('balance_sheets', 'id')->where('created_by', creatorId())],
            'previous_period_id' => ['required', Rule::exists('balance_sheets', 'id')->where('created_by', creatorId())]
        ];
    }
}

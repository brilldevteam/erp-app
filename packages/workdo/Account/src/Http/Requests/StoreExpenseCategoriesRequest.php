<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Account\Models\ChartOfAccount;

class StoreExpenseCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return !$this->filled('gl_account_id')
            || ChartOfAccount::where('created_by', creatorId())->whereKey($this->input('gl_account_id'))->exists();
    }

    public function rules(): array
    {
        return [
            'category_name' => 'required',
            'category_code' => [
                'required',
                Rule::unique('expense_categories', 'category_code')
                    ->where(fn ($query) => $query->where('created_by', creatorId())),
            ],
            'gl_account_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable',
            'is_active' => 'boolean'
        ];
    }
}

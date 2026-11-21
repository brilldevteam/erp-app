<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Account\Models\ChartOfAccount;

class UpdateExpenseCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('expensecategories');

        return (int) $category->created_by === (int) creatorId()
            && (!$this->filled('gl_account_id')
                || ChartOfAccount::where('created_by', creatorId())->whereKey($this->input('gl_account_id'))->exists());
    }

    public function rules(): array
    {
        return [
            'category_name' => 'required',
            'category_code' => [
                'required',
                Rule::unique('expense_categories', 'category_code')
                    ->where(fn ($query) => $query->where('created_by', creatorId()))
                    ->ignore($this->route('expensecategories')->id),
            ],
            'gl_account_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable',
            'is_active' => 'boolean'
        ];
    }
}

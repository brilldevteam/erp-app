<?php

namespace Workdo\PettyCashManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReimbursementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => 'required|exists:users,id',
            'category_id'  => 'required|exists:petty_cash_categories,id',
            'amount'       => 'required',
            'receipt_path' => 'required|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'description'  => 'nullable',
        ];
    }
}

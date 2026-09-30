<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBankTransferRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'transfer_date' => 'required|date',
            // Both accounts must belong to the current company.
            'from_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('created_by', creatorId())],
            'to_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('created_by', creatorId()), 'different:from_account_id'],
            'transfer_amount' => 'required|numeric|min:0.01',
            'transfer_charges' => 'nullable|numeric|min:0',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'required|string|max:500'
        ];
    }

    public function messages()
    {
        return [
            'to_account_id.different' => __('Destination account must be different from source account.'),
            'transfer_amount.min' => __('Transfer amount must be greater than 0.'),
        ];
    }
}
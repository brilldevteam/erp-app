<?php

namespace Workdo\Retainer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesRetainerPaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'payment_date' => 'required|date|before_or_equal:today',
            'customer_id' => 'required|exists:users,id',
            'retainer_id' => 'nullable|exists:sales_retainers,id',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'payment_amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'allocations' => 'nullable|array',
            'allocations.*.retainer_id' => 'required|exists:sales_retainers,id',
            'allocations.*.amount' => 'required|numeric|min:0.01'
        ];
    }

    public function messages()
    {
        return [
            'payment_date.before_or_equal' => __('Payment date cannot be in the future.'),
            'payment_amount.min' => __('Payment amount must be greater than 0.')
        ];
    }
}
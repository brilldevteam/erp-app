<?php

namespace Workdo\Retainer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'customer_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('created_by', creatorId())->where('type', 'client'))],
            'retainer_id' => ['nullable', 'integer', Rule::exists('sales_retainers', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where(fn ($query) => $query->where('created_by', creatorId())->where('is_active', true))],
            'payment_amount' => 'required|numeric|min:0.01',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'allocations' => 'nullable|array',
            'allocations.*.retainer_id' => ['required', 'integer', Rule::exists('sales_retainers', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
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

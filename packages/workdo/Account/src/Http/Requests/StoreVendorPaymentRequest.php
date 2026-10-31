<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreVendorPaymentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'payment_date' => 'required|date|before_or_equal:today',
            'vendor_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('created_by', creatorId())->where('type', 'vendor'))],
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where(fn ($query) => $query->where('created_by', creatorId())->where('is_active', true))],
            'payment_mode' => 'required|in:cash,bank_transfer,cheque',
            'reference_number' => 'required_if:payment_mode,cheque|nullable|string|max:100',
            'payment_amount' => 'required|numeric|min:0',
            'currency_code' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|gt:0',
            'notes' => 'nullable|string',
            'allocations' => 'nullable|array',
            'allocations.*.invoice_id' => ['required', 'integer', Rule::exists('purchase_invoices', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'debit_notes' => 'nullable|array',
            'debit_notes.*.debit_note_id' => ['required', 'integer', Rule::exists('debit_notes', 'id')->where(fn ($query) => $query->where('created_by', creatorId()))],
            'debit_notes.*.amount' => 'required|numeric|min:0.01'
        ];
    }

    public function messages()
    {
        return [
            'payment_date.before_or_equal' => __('Payment date cannot be in the future.'),
            'allocations.*.amount.min' => __('Allocation amount must be greater than 0.'),
            'debit_notes.*.amount.min' => __('Debit note amount must be greater than 0.')
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $allocations = $this->input('allocations', []);
            $debitNotes = $this->input('debit_notes', []);

            if (empty($allocations) && empty($debitNotes)) {
                $validator->errors()->add('allocations', 'At least one invoice allocation or debit note is required.');
            }
        });
    }
}

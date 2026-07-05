<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'journal_date' => 'required|date',
            'reference_type' => 'nullable|string|max:255',
            'description' => 'required|string|max:2000',
            'items' => 'required|array|min:2',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.debit_amount' => 'nullable|numeric|min:0',
            'items.*.credit_amount' => 'nullable|numeric|min:0',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = collect($this->input('items', []));
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($items as $index => $item) {
                $debit = round((float) ($item['debit_amount'] ?? 0), 2);
                $credit = round((float) ($item['credit_amount'] ?? 0), 2);

                if ($debit <= 0 && $credit <= 0) {
                    $validator->errors()->add("items.{$index}.debit_amount", __('Each journal line must have either a debit or credit amount.'));
                }

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add("items.{$index}.credit_amount", __('A journal line cannot have both debit and credit amounts.'));
                }

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (round($totalDebit, 2) <= 0 || round($totalCredit, 2) <= 0) {
                $validator->errors()->add('items', __('Journal entry must include both debit and credit lines.'));
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $validator->errors()->add('items', __('Total debit and total credit must be equal.'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.min' => __('At least two journal lines are required.'),
            'items.*.account_id.required' => __('Please select an account for each journal line.'),
            'items.*.account_id.exists' => __('Selected account does not exist.'),
        ];
    }
}

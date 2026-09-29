<?php

namespace Workdo\DoubleEntry\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBalanceSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start_date' => 'required|date|before_or_equal:balance_sheet_date',
            'balance_sheet_date' => 'required|date',
            'financial_year' => 'required|string|max:4'
        ];
    }
}

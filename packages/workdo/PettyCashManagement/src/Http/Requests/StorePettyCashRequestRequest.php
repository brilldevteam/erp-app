<?php

namespace Workdo\PettyCashManagement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePettyCashRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'          => 'required|exists:users,id',
            'categorie_id'     => 'required|exists:petty_cash_categories,id',
            'requested_amount' => 'required',
            'remarks'          => 'nullable',
        ];
    }
}

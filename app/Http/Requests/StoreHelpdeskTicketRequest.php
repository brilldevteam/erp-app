<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHelpdeskTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'category_id' => 'required|exists:helpdesk_categories,id',
            'company_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('type', 'company')),
            ],
        ];
    }
}

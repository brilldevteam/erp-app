<?php

namespace Workdo\EInvoice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EInvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings.electronic_address' => 'required|string|max:255',
            'settings.company_id' => 'required|string|max:255',
            'settings.electronic_address_schema' => 'required|string|max:255',
            'settings.company_id_schema' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'settings.electronic_address.required' => __('Electronic address is required.'),
            'settings.company_id.required' => __('Company ID is required.'),
            'settings.electronic_address_schema.required' => __('Electronic address schema is required.'),
            'settings.company_id_schema.required' => __('Company ID schema is required.'),
        ];
    }
}

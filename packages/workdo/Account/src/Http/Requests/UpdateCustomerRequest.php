<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Account\Http\Requests\Concerns\ValidatesCustomerAddresses;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    use ValidatesCustomerAddresses;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'company_name' => 'required|string|max:255',
            'contact_person_name' => 'required|string|max:255',
            'portal_access_enabled' => 'boolean',
            'password' => [Rule::requiredIf($this->boolean('portal_access_enabled') && !($this->route('customer')?->user?->is_enable_login)), 'nullable', 'confirmed', 'min:6'],
            'contact_person_email' => ['nullable', 'email', 'max:255', Rule::requiredIf($this->boolean('portal_access_enabled')), Rule::when($this->boolean('portal_access_enabled'), Rule::unique('users', 'email')->ignore($this->route('customer')?->user_id))],
            'contact_person_mobile' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:255',
            'billing_address' => 'required|array',
            'shipping_address' => 'required_if:same_as_billing,false|array',
            'same_as_billing' => 'boolean',
            'notes' => 'nullable|string',
        ] + $this->customerAddressRules();
    }
}

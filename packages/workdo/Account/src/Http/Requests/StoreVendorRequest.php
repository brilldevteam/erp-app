<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workdo\Account\Http\Requests\Concerns\ValidatesCustomerAddresses;

class StoreVendorRequest extends FormRequest
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
            'password' => [Rule::requiredIf($this->boolean('portal_access_enabled')), 'nullable', 'string', 'confirmed', 'min:6'],
            'contact_person_email' => ['nullable', 'email', 'max:255', Rule::requiredIf($this->boolean('portal_access_enabled')), Rule::when($this->boolean('portal_access_enabled'), Rule::unique('users', 'email'))],
            'contact_person_mobile' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'cr_number' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:255',
            'billing_address' => 'nullable|array',
            'same_as_billing' => 'boolean',
            'shipping_address' => 'nullable|array',
            'notes' => 'nullable|string',
            'return_to' => 'nullable|in:project.contractors.index,current',
        ] + $this->customerAddressRules() + \Workdo\Account\Services\PartyAttachmentService::rules();
    }
}

<?php

namespace Workdo\Account\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Workdo\Account\Http\Requests\Concerns\ValidatesCustomerAddresses;

class StoreCustomerRequest extends FormRequest
{
    use ValidatesCustomerAddresses;

    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'company_name' => 'required|string|max:255',
            'contact_person_name' => 'required|string|max:255',
            'contact_person_email' => 'required|email|max:255',
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

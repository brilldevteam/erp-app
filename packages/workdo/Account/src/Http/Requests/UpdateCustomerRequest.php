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
        $customer = $this->route('customer');
        $user = $customer?->user;
        $partyEmail = strtolower(trim((string) $customer?->contact_person_email));
        $userEmail = strtolower(trim((string) $user?->email));
        $currentlyEnabled = $partyEmail !== ''
            && $partyEmail === $userEmail
            && (bool) $user?->is_enable_login
            && !(bool) $user?->is_disable
            && !str_ends_with($userEmail, '@import.local');

        return [
            'company_name' => 'required|string|max:255',
            'contact_person_name' => 'required|string|max:255',
            'portal_access_enabled' => 'boolean',
            'password' => [Rule::requiredIf($this->boolean('portal_access_enabled') && !$currentlyEnabled), 'nullable', 'confirmed', 'min:6'],
            'contact_person_email' => ['nullable', 'email', 'max:255', Rule::requiredIf($this->boolean('portal_access_enabled')), Rule::when($this->boolean('portal_access_enabled'), Rule::unique('users', 'email')->ignore($this->route('customer')?->user_id))],
            'contact_person_mobile' => 'nullable|string|max:255',
            'tax_number' => 'nullable|string|max:255',
            'cr_number' => 'nullable|string|max:255',
            'payment_terms' => 'nullable|string|max:255',
            'billing_address' => 'nullable|array',
            'shipping_address' => 'nullable|array',
            'same_as_billing' => 'boolean',
            'notes' => 'nullable|string',
        ] + $this->customerAddressRules() + \Workdo\Account\Services\PartyAttachmentService::rules();
    }
}

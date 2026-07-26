<?php

namespace Workdo\Account\Http\Requests\Concerns;

use App\Support\CountryAddressValidation;
use Illuminate\Validation\Validator;

trait ValidatesCustomerAddresses
{
    protected function customerAddressRules(): array
    {
        $rules = [];

        foreach (['billing_address', 'shipping_address'] as $prefix) {
            $rules += CountryAddressValidation::rules($prefix);
            $rules += [
                "{$prefix}.name" => 'nullable|string|max:255',
                "{$prefix}.qid_number" => 'nullable|string|max:11',
                "{$prefix}.saudi_identity_number" => 'nullable|string|max:10',
            ];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        foreach (['billing_address', 'shipping_address'] as $prefix) {
            $address = $this->input($prefix);
            if (is_array($address)) {
                $this->merge([$prefix => CountryAddressValidation::normalize($address)]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateCustomerAddress($validator, 'billing_address');

            if (!$this->boolean('same_as_billing')) {
                $this->validateCustomerAddress($validator, 'shipping_address');
            }
        });
    }

    private function validateCustomerAddress(Validator $validator, string $prefix): void
    {
        $address = $this->input($prefix, []);
        if (!is_array($address)) return;

        $required = function (string $field, string $label) use ($validator, $prefix, $address) {
            if (trim((string) ($address[$field] ?? '')) === '') {
                $validator->errors()->add("{$prefix}.{$field}", __(":attribute is required.", ['attribute' => __($label)]));
            }
        };

        $required('name', $prefix === 'billing_address' ? 'Billing Name' : 'Shipping Name');
        $code = strtoupper(trim((string) ($address['country_code'] ?? '')));

        if ($code === 'QA') {
            $required('qid_number', 'QID No.');
            $qidNumber = trim((string) ($address['qid_number'] ?? ''));
            if ($qidNumber !== '' && !preg_match('/^\d{11}$/', $qidNumber)) {
                $validator->errors()->add("{$prefix}.qid_number", __("QID No. must be exactly 11 digits."));
            }
        }

        if ($code === 'SA') {
            $required('saudi_identity_number', 'National ID / Iqama No.');
            $identityNumber = trim((string) ($address['saudi_identity_number'] ?? ''));
            if ($identityNumber !== '' && !preg_match('/^[12]\d{9}$/', $identityNumber)) {
                $validator->errors()->add("{$prefix}.saudi_identity_number", __("National ID / Iqama No. must be 10 digits and start with 1 or 2."));
            }
        }

        CountryAddressValidation::validate($validator, $prefix);
    }
}

<?php

namespace App\Support;

use Illuminate\Validation\Validator;

class CountryAddressValidation
{
    public static function rules(string $prefix, bool $required = false, bool $requireCountryCode = false): array
    {
        return [
            $prefix => [$required ? 'required' : 'nullable', 'array'],
            "{$prefix}.country" => [$required ? 'required' : 'nullable', 'string', 'max:255'],
            "{$prefix}.country_code" => [$requireCountryCode ? 'required' : 'nullable', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            "{$prefix}.address_line_1" => 'nullable|string|max:255',
            "{$prefix}.address_line_2" => 'nullable|string|max:255',
            "{$prefix}.city" => 'nullable|string|max:255',
            "{$prefix}.state" => 'nullable|string|max:255',
            "{$prefix}.zip_code" => 'nullable|string|max:20',
            "{$prefix}.zone_number" => 'nullable|string|max:20',
            "{$prefix}.street_number" => 'nullable|string|max:20',
            "{$prefix}.building_number" => 'nullable|string|max:20',
            "{$prefix}.street_name" => 'nullable|string|max:255',
            "{$prefix}.district" => 'nullable|string|max:255',
            "{$prefix}.secondary_number" => 'nullable|string|max:20',
        ];
    }

    public static function normalize(mixed $address): mixed
    {
        if (!is_array($address)) return $address;

        $address['country_code'] = strtoupper(trim((string) ($address['country_code'] ?? '')));
        $country = strtolower(trim((string) ($address['country'] ?? '')));
        if ($address['country_code'] === '' && $country === 'qatar') $address['country_code'] = 'QA';
        if ($address['country_code'] === '' && in_array($country, ['saudi arabia', 'kingdom of saudi arabia'], true)) $address['country_code'] = 'SA';

        return $address;
    }

    public static function validate(Validator $validator, string $prefix): void
    {
        $address = data_get($validator->getData(), $prefix, []);
        if (!is_array($address)) return;

        $required = function (string $field, string $label) use ($validator, $prefix, $address) {
            if (trim((string) ($address[$field] ?? '')) === '') {
                $validator->errors()->add("{$prefix}.{$field}", __(':attribute is required.', ['attribute' => __($label)]));
            }
        };

        $required('country', 'Country');
        $code = strtoupper(trim((string) ($address['country_code'] ?? '')));

        if ($code === 'QA') {
            foreach (['zone_number' => 'Zone Number', 'street_number' => 'Street Number', 'building_number' => 'Building Number'] as $field => $label) {
                $required($field, $label);
                $value = trim((string) ($address[$field] ?? ''));
                if ($value !== '' && !ctype_digit($value)) {
                    $validator->errors()->add("{$prefix}.{$field}", __(':attribute must contain only numbers.', ['attribute' => __($label)]));
                }
            }
            return;
        }

        if ($code === 'SA') {
            foreach (['building_number' => 'Building Number', 'street_name' => 'Street Name', 'district' => 'District', 'city' => 'City', 'zip_code' => 'Postal Code', 'secondary_number' => 'Secondary Number'] as $field => $label) {
                $required($field, $label);
            }
            foreach (['building_number' => 'Building Number', 'secondary_number' => 'Secondary Number'] as $field => $label) {
                $value = trim((string) ($address[$field] ?? ''));
                if ($value !== '' && !preg_match('/^\d{4}$/', $value)) {
                    $validator->errors()->add("{$prefix}.{$field}", __(':attribute must be exactly 4 digits.', ['attribute' => __($label)]));
                }
            }
            $postalCode = trim((string) ($address['zip_code'] ?? ''));
            if ($postalCode !== '' && !preg_match('/^\d{5}$/', $postalCode)) {
                $validator->errors()->add("{$prefix}.zip_code", __('Postal Code must be exactly 5 digits.'));
            }
            return;
        }

        foreach (['address_line_1' => 'Address Line 1', 'city' => 'City', 'state' => 'State / Province', 'zip_code' => 'ZIP / Postal Code'] as $field => $label) {
            $required($field, $label);
        }
    }
}

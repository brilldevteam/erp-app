<?php

namespace Workdo\Taskly\Http\Requests\Concerns;

use App\Support\CountryAddressValidation;
use Illuminate\Validation\Validator;

trait ValidatesProjectPropertyInformation
{
    protected function projectPropertyInformationRules(): array
    {
        if ($this->input('category') !== 'property') {
            return ['property_information' => 'nullable|array'];
        }

        return CountryAddressValidation::rules('property_information', true, true) + [
            'property_information.plot_number' => 'required|string|max:50',
            'property_information.property_number' => 'required|string|max:50',
            'property_information.location_url' => ['required', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'property_information' => CountryAddressValidation::normalize($this->input('property_information')),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->input('category') === 'property') {
            $validator->after(fn (Validator $validator) => CountryAddressValidation::validate($validator, 'property_information'));
        }
    }
}

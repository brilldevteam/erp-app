<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Workdo\Taskly\Http\Requests\StoreProjectRequest;

class ProjectPropertyInformationValidationTest extends TestCase
{
    public function test_qatar_project_property_information_is_valid(): void
    {
        $validator = $this->validatePropertyInformation([
            'country' => 'Qatar',
            'country_code' => 'QA',
            'zone_number' => '74',
            'street_number' => '598',
            'building_number' => '12',
            'plot_number' => '074080408',
            'property_number' => '403275',
            'location_url' => 'https://maps.google.com/?q=25.2854,51.5310',
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_saudi_project_property_information_is_valid(): void
    {
        $validator = $this->validatePropertyInformation([
            'country' => 'Saudi Arabia',
            'country_code' => 'SA',
            'building_number' => '2929',
            'street_name' => 'Rayhanah Bint Zaid',
            'district' => 'Al Arid',
            'city' => 'Riyadh',
            'zip_code' => '13337',
            'secondary_number' => '8118',
            'plot_number' => 'PLOT-001',
            'property_number' => '000123',
            'location_url' => 'https://maps.google.com/?q=24.7136,46.6753',
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_generic_project_address_uses_generic_required_fields(): void
    {
        $validator = $this->validatePropertyInformation([
            'country' => 'United Arab Emirates',
            'country_code' => 'AE',
            'address_line_1' => '100 Main Street',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'zip_code' => '00000',
            'plot_number' => '100',
            'property_number' => '200',
            'location_url' => 'https://maps.google.com/?q=25.2048,55.2708',
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_project_property_information_rejects_missing_identifiers_and_invalid_map_link(): void
    {
        $validator = $this->validatePropertyInformation([
            'country' => 'Qatar',
            'country_code' => 'QA',
            'zone_number' => '74',
            'street_number' => '598',
            'building_number' => '12',
            'plot_number' => '',
            'property_number' => '',
            'location_url' => 'Doha location',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('property_information.plot_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('property_information.property_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('property_information.location_url', $validator->errors()->toArray());
    }

    private function validatePropertyInformation(array $propertyInformation): \Illuminate\Contracts\Validation\Validator
    {
        $data = ['property_information' => $propertyInformation];
        $request = StoreProjectRequest::create('/projects', 'POST', $data);
        $request->setContainer($this->app);
        $rules = collect($request->rules())
            ->filter(fn ($rule, $key) => $key === 'property_information' || str_starts_with($key, 'property_information.'))
            ->all();
        $validator = Validator::make($data, $rules);
        $request->withValidator($validator);

        return $validator;
    }
}

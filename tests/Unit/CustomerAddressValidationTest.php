<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use Workdo\Account\Http\Requests\StoreCustomerRequest;

class CustomerAddressValidationTest extends TestCase
{
    public function test_generic_country_requires_the_existing_address_fields(): void
    {
        $validator = $this->validateAddresses([
            'billing_address' => [
                'name' => 'Example Trading',
                'country' => 'United Arab Emirates',
                'country_code' => 'AE',
                'address_line_1' => '100 Main Street',
                'city' => 'Dubai',
                'state' => 'Dubai',
                'zip_code' => '00000',
            ],
            'same_as_billing' => true,
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_qatar_uses_zone_street_and_building_numbers(): void
    {
        $validator = $this->validateAddresses([
            'billing_address' => [
                'name' => 'Doha Customer',
                'country' => 'Qatar',
                'country_code' => 'QA',
                'qid_number' => '28463401022',
                'zone_number' => '74',
                'street_number' => '598',
                'building_number' => '12',
            ],
            'same_as_billing' => true,
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_qatar_rejects_missing_or_non_numeric_plate_fields(): void
    {
        $validator = $this->validateAddresses([
            'billing_address' => [
                'name' => 'Doha Customer',
                'country' => 'Qatar',
                'country_code' => 'QA',
                'qid_number' => '28463',
                'zone_number' => 'Zone 74',
                'building_number' => '12',
            ],
            'same_as_billing' => true,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address.qid_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_address.zone_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_address.street_number', $validator->errors()->toArray());
    }

    public function test_saudi_arabia_uses_the_official_national_address_lengths(): void
    {
        $validator = $this->validateAddresses([
            'billing_address' => [
                'name' => 'Riyadh Customer',
                'country' => 'Saudi Arabia',
                'country_code' => 'SA',
                'saudi_identity_number' => '1023456789',
                'building_number' => '2929',
                'street_name' => 'Rayhanah Bint Zaid',
                'district' => 'Al Arid',
                'city' => 'Riyadh',
                'zip_code' => '13337',
                'secondary_number' => '8118',
            ],
            'same_as_billing' => true,
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_saudi_arabia_rejects_invalid_national_address_lengths(): void
    {
        $validator = $this->validateAddresses([
            'billing_address' => [
                'name' => 'Riyadh Customer',
                'country' => 'Saudi Arabia',
                'country_code' => 'SA',
                'saudi_identity_number' => '3023456789',
                'building_number' => '29',
                'street_name' => 'Rayhanah Bint Zaid',
                'district' => 'Al Arid',
                'city' => 'Riyadh',
                'zip_code' => '133',
                'secondary_number' => '81',
            ],
            'same_as_billing' => true,
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('billing_address.saudi_identity_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_address.building_number', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_address.zip_code', $validator->errors()->toArray());
        $this->assertArrayHasKey('billing_address.secondary_number', $validator->errors()->toArray());
    }

    private function validateAddresses(array $data): \Illuminate\Contracts\Validation\Validator
    {
        $request = StoreCustomerRequest::create('/customers', 'POST', $data);
        $request->setContainer($this->app);
        $rules = collect($request->rules())
            ->filter(fn ($rule, $key) => str_starts_with($key, 'billing_address') || str_starts_with($key, 'shipping_address') || $key === 'same_as_billing')
            ->all();
        $validator = Validator::make($data, $rules);
        $request->withValidator($validator);

        return $validator;
    }
}

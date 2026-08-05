<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\User;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\EntityDefinition;
use App\Services\BulkImport\ImportedClientUserService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Events\UpdateCustomer;
use Workdo\Account\Models\Customer;

class CustomerDefinition implements EntityDefinition
{
    use NormalizesImportValues;

    public function key(): string { return 'customers'; }
    public function permission(): string { return 'import-customers'; }
    public function createPermission(): string { return 'create-customers'; }

    public function headers(): array
    {
        return [
            'user_name', 'user_email', 'mobile_no', 'company_name', 'contact_person_name',
            'contact_person_email', 'tax_number', 'payment_terms',
            'billing_name', 'billing_address_line_1', 'billing_address_line_2',
            'billing_city', 'billing_state', 'billing_country', 'billing_zip_code',
            'same_as_billing', 'shipping_name', 'shipping_address_line_1',
            'shipping_address_line_2', 'shipping_city', 'shipping_state',
            'shipping_country', 'shipping_zip_code', 'notes',
        ];
    }

    public function requiredFields(): array
    {
        return ['user_name'];
    }

    public function aliases(): array
    {
        return [
            'user_name' => ['user', 'customer name', 'contact name', 'display name', 'name'],
            'user_email' => ['email', 'email address', 'customer email', 'primary email'],
            'mobile_no' => ['mobile', 'phone', 'phone number', 'mobile number', 'contact phone'],
            'company_name' => ['company', 'organization', 'organisation', 'business name'],
            'contact_person_name' => ['contact person', 'primary contact', 'contact'],
            'contact_person_email' => ['contact email', 'primary contact email'],
            'tax_number' => ['tax id', 'tax registration number', 'vat number', 'trn'],
            'payment_terms' => ['terms', 'payment term'],
            'billing_name' => ['billing attention', 'billing addressee'],
            'billing_address_line_1' => ['billing address', 'billing street', 'billing address 1'],
            'billing_address_line_2' => ['billing address 2', 'billing street 2'],
            'billing_city' => ['billing town'],
            'billing_state' => ['billing province', 'billing region'],
            'billing_country' => ['country'],
            'billing_zip_code' => ['billing zip', 'billing postal code', 'postal code'],
            'same_as_billing' => ['shipping same as billing', 'same address'],
            'shipping_name' => ['shipping attention', 'shipping addressee'],
            'shipping_address_line_1' => ['shipping address', 'shipping street', 'shipping address 1'],
            'shipping_address_line_2' => ['shipping address 2', 'shipping street 2'],
            'shipping_city' => ['shipping town'],
            'shipping_state' => ['shipping province', 'shipping region'],
            'shipping_zip_code' => ['shipping zip', 'shipping postal code'],
            'notes' => ['note', 'remarks', 'comments'],
        ];
    }

    public function example(): array
    {
        return [
            'Jane Smith', 'jane@example.com', '+15551234567', 'Example Trading',
            'Jane Smith', 'jane@example.com', 'TAX-100', 'Net 30',
            'Example Trading', '100 Main Street', '', 'Riyadh', 'Riyadh',
            'Saudi Arabia', '11564', 'yes', '', '', '', '', '', '', '', 'Imported customer',
        ];
    }

    public function instructions(): array
    {
        return [
            'user_email is optional. Customers without an email are imported with login access disabled.',
            'When user_email is blank, duplicate matching uses company_name/user_name.',
            'Only customer name is required; contact and company values default from it.',
            'Billing and shipping addresses are optional for imports.',
            'same_as_billing accepts yes/no, true/false, or 1/0.',
            'mobile_no is optional.',
        ];
    }

    public function prepare(array $row): array
    {
        $name = $this->text($row['user_name'] ?? '');
        $email = strtolower($this->text($row['user_email'] ?? ''));
        $row['user_name'] = $name;
        $row['user_email'] = $email;
        $row['company_name'] = $this->text($row['company_name'] ?? '') ?: $name;
        $row['contact_person_name'] = $this->text($row['contact_person_name'] ?? '') ?: $name;
        $row['contact_person_email'] = $this->nullableText($row['contact_person_email'] ?? null) ?? $email ?: null;
        $row['billing_name'] = $this->text($row['billing_name'] ?? '') ?: $row['company_name'];
        $row['same_as_billing'] = $this->nullableText($row['same_as_billing'] ?? null) ?? 'yes';

        return $row;
    }

    public function identity(array $row): string
    {
        $email = strtolower($this->text($row['user_email'] ?? ''));

        if ($email !== '') {
            return 'email:'.$email;
        }

        return 'customer:'.strtolower($this->text($row['company_name'] ?? $row['user_name'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        foreach (['user_email', 'contact_person_email'] as $field) {
            if (($value = $this->nullableText($row[$field] ?? null)) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is invalid.';
            }
        }

        if (($mobile = $this->nullableText($row['mobile_no'] ?? null))
            && !preg_match('/^\+?[0-9 ()-]{7,20}$/', $mobile)) {
            $errors[] = 'Mobile number format is invalid.';
        }

        $hasShippingData = collect([
            'shipping_name', 'shipping_address_line_1', 'shipping_city',
            'shipping_state', 'shipping_country', 'shipping_zip_code',
        ])->contains(fn ($field) => $this->text($row[$field] ?? '') !== '');
        if (!$this->boolean($row['same_as_billing'] ?? true) && $hasShippingData) {
            foreach ([
                'shipping_name', 'shipping_address_line_1', 'shipping_city',
                'shipping_state', 'shipping_country', 'shipping_zip_code',
            ] as $field) {
                if ($this->text($row[$field] ?? '') === '') {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
                }
            }
        }

        $email = strtolower($this->text($row['user_email'] ?? ''));
        if ($email !== '') {
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user && ($user->created_by !== $tenantId || $user->type !== 'client')) {
                $errors[] = 'User email belongs to another account or an incompatible user type.';
            }
        }

        if (!Role::where('name', 'client')
            ->where('guard_name', 'web')
            ->where('created_by', $tenantId)
            ->exists()) {
            $errors[] = 'The client role is not configured for this company.';
        }

        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        $email = strtolower($this->text($row['user_email'] ?? ''));
        if ($email !== '') {
            return Customer::where('created_by', $tenantId)
                ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]))
                ->exists();
        }

        return Customer::where('created_by', $tenantId)
            ->whereRaw('LOWER(company_name) = ?', [strtolower($this->text($row['company_name'] ?? $row['user_name'] ?? ''))])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $email = strtolower($this->text($row['user_email'] ?? ''));
        $user = $email !== ''
            ? User::where('created_by', $tenantId)
                ->where('type', 'client')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first()
            : Customer::where('created_by', $tenantId)
                ->whereRaw('LOWER(company_name) = ?', [strtolower($this->text($row['company_name'] ?? $row['user_name'] ?? ''))])
                ->first()?->user;

        if (!$user) {
            $user = app(ImportedClientUserService::class)->createImportContact([
                'name' => $this->text($row['user_name']),
                'email' => $email,
                'mobile_no' => $this->nullableText($row['mobile_no'] ?? null),
            ], $tenantId, $actorId);
        }

        $customer = Customer::where('created_by', $tenantId)->where('user_id', $user->id)->first();
        if ($customer && $strategy === 'skip') {
            return 'skipped';
        }

        $userValues = ['name' => $this->text($row['user_name'])];
        if ($this->isMapped($row, 'mobile_no')) {
            $userValues['mobile_no'] = $this->nullableText($row['mobile_no'] ?? null);
        }
        $user->update($userValues);

        $billing = $this->address($row, 'billing');
        $sameAsBilling = $this->boolean($row['same_as_billing'] ?? true);
        $values = [
            'user_id' => $user->id,
            'company_name' => $this->text($row['company_name']),
            'contact_person_name' => $this->text($row['contact_person_name']),
            'contact_person_email' => $this->nullableText($row['contact_person_email'] ?? null),
            'contact_person_mobile' => $this->nullableText($row['mobile_no'] ?? null),
            'tax_number' => $this->nullableText($row['tax_number'] ?? null),
            'payment_terms' => $this->nullableText($row['payment_terms'] ?? null),
            'billing_address' => $billing,
            'shipping_address' => $sameAsBilling ? $billing : $this->address($row, 'shipping'),
            'same_as_billing' => $sameAsBilling,
            'notes' => $this->nullableText($row['notes'] ?? null),
        ];
        $request = new Request($values);

        if ($customer) {
            $updateValues = array_intersect_key($values, array_flip(array_filter([
                'user_id',
                $this->isMapped($row, 'company_name') ? 'company_name' : null,
                $this->isMapped($row, 'contact_person_name') ? 'contact_person_name' : null,
                $this->isMapped($row, 'contact_person_email') ? 'contact_person_email' : null,
                $this->isMapped($row, 'mobile_no') ? 'contact_person_mobile' : null,
                $this->isMapped($row, 'tax_number') ? 'tax_number' : null,
                $this->isMapped($row, 'payment_terms') ? 'payment_terms' : null,
                $this->hasMappedPrefix($row, 'billing_') ? 'billing_address' : null,
                ($this->hasMappedPrefix($row, 'shipping_') || $this->isMapped($row, 'same_as_billing'))
                    ? 'shipping_address' : null,
                $this->isMapped($row, 'same_as_billing') ? 'same_as_billing' : null,
                $this->isMapped($row, 'notes') ? 'notes' : null,
            ])));
            $customer->update($updateValues);
            UpdateCustomer::dispatch(new Request($updateValues), $customer);

            return 'updated';
        }

        $customer = Customer::create($values + [
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        CreateCustomer::dispatch($request, $customer);

        return 'imported';
    }

    private function isMapped(array $row, string $field): bool
    {
        return in_array($field, $row['_mapped_fields'] ?? [], true);
    }

    private function hasMappedPrefix(array $row, string $prefix): bool
    {
        return collect($row['_mapped_fields'] ?? [])
            ->contains(fn ($field) => str_starts_with($field, $prefix));
    }
}

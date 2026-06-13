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
            'user_email is the duplicate key and creates a client user when no compatible user exists.',
            'same_as_billing accepts yes/no, true/false, or 1/0.',
            'Shipping fields are required only when same_as_billing is false.',
            'mobile_no is optional and must include the country code when supplied.',
        ];
    }

    public function identity(array $row): string
    {
        return strtolower($this->text($row['user_email'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ([
            'user_name', 'user_email', 'company_name', 'contact_person_name',
            'contact_person_email', 'billing_name', 'billing_address_line_1',
            'billing_city', 'billing_state', 'billing_country', 'billing_zip_code',
        ] as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        foreach (['user_email', 'contact_person_email'] as $field) {
            if (($value = $this->nullableText($row[$field] ?? null)) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is invalid.';
            }
        }

        if (($mobile = $this->nullableText($row['mobile_no'] ?? null)) && !preg_match('/^\+\d{10,16}$/', $mobile)) {
            $errors[] = 'Mobile number must include country code.';
        }

        if (!$this->boolean($row['same_as_billing'] ?? false)) {
            foreach ([
                'shipping_name', 'shipping_address_line_1', 'shipping_city',
                'shipping_state', 'shipping_country', 'shipping_zip_code',
            ] as $field) {
                if ($this->text($row[$field] ?? '') === '') {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
                }
            }
        }

        $user = User::whereRaw('LOWER(email) = ?', [$this->identity($row)])->first();
        if ($user && ($user->created_by !== $tenantId || $user->type !== 'client')) {
            $errors[] = 'User email belongs to another account or an incompatible user type.';
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
        return Customer::where('created_by', $tenantId)
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$this->identity($row)]))
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $email = $this->identity($row);
        $user = User::where('created_by', $tenantId)
            ->where('type', 'client')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (!$user) {
            $user = app(ImportedClientUserService::class)->create([
                'name' => $this->text($row['user_name']),
                'email' => $email,
                'mobile_no' => $this->nullableText($row['mobile_no'] ?? null),
            ], $tenantId, $actorId);
        }

        $customer = Customer::where('created_by', $tenantId)->where('user_id', $user->id)->first();
        if ($customer && $strategy === 'skip') {
            return 'skipped';
        }

        $user->update([
            'name' => $this->text($row['user_name']),
            'mobile_no' => $this->nullableText($row['mobile_no'] ?? null),
        ]);

        $billing = $this->address($row, 'billing');
        $sameAsBilling = $this->boolean($row['same_as_billing'] ?? false);
        $values = [
            'user_id' => $user->id,
            'company_name' => $this->text($row['company_name']),
            'contact_person_name' => $this->text($row['contact_person_name']),
            'contact_person_email' => $this->text($row['contact_person_email']),
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
            $customer->update($values);
            UpdateCustomer::dispatch($request, $customer);

            return 'updated';
        }

        $customer = Customer::create($values + [
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
        CreateCustomer::dispatch($request, $customer);

        return 'imported';
    }
}

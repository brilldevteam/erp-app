<?php

namespace App\Services\BulkImport\Definitions;

use App\Models\User;
use App\Services\BulkImport\Concerns\NormalizesImportValues;
use App\Services\BulkImport\Definitions\Concerns\ResolvesImportReferences;
use App\Services\BulkImport\EntityDefinition;
use App\Services\BulkImport\ImportedClientUserService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Workdo\Account\Events\CreateVendor;
use Workdo\Account\Events\UpdateVendor;
use Workdo\Account\Models\Vendor;

class VendorDefinition implements EntityDefinition
{
    use NormalizesImportValues;
    use ResolvesImportReferences;

    public function key(): string { return 'vendors'; }
    public function permission(): string { return 'import-vendors'; }
    public function createPermission(): string { return 'create-vendors'; }

    public function headers(): array
    {
        return [
            'vendor_name', 'vendor_email', 'mobile_no', 'company_name',
            'tax_number', 'payment_terms', 'billing_name', 'billing_address_line_1',
            'billing_address_line_2', 'billing_city', 'billing_state', 'billing_country',
            'billing_zip_code', 'same_as_billing', 'shipping_name', 'shipping_address_line_1',
            'shipping_address_line_2', 'shipping_city', 'shipping_state',
            'shipping_country', 'shipping_zip_code', 'notes',
        ];
    }

    public function requiredFields(): array { return ['vendor_name']; }

    public function aliases(): array
    {
        return [
            'vendor_name' => ['vendor', 'supplier name', 'contact name', 'display name', 'name'],
            'vendor_email' => ['email', 'email address', 'primary email', 'vendor email'],
            'mobile_no' => ['phone', 'mobile', 'mobile number', 'phone number'],
            'company_name' => ['company', 'organization', 'organisation'],
            'tax_number' => ['tax id', 'vat number', 'trn', 'tax registration number'],
            'payment_terms' => ['terms', 'payment term'],
            'billing_address_line_1' => ['billing address', 'address', 'street'],
            'billing_city' => ['city'],
            'billing_state' => ['state', 'province'],
            'billing_country' => ['country'],
            'billing_zip_code' => ['zip', 'postal code'],
        ];
    }

    public function example(): array
    {
        return ['ABC Supplies', 'vendor@example.com', '+15551230000', 'ABC Supplies LLC', 'VAT-001', 'Net 30', 'ABC Supplies', '20 Market Road', '', 'Doha', 'Doha', 'Qatar', '00000', 'yes', '', '', '', '', '', '', '', 'Imported from Zoho Books'];
    }

    public function instructions(): array
    {
        return [
            'vendor_email is optional. Imported vendors receive internal accounts with portal login disabled.',
            'When vendor_email is blank, duplicate matching uses company_name/vendor_name.',
            'Only vendor name is required.',
            'Billing and shipping addresses are optional.',
        ];
    }

    public function prepare(array $row): array
    {
        $name = $this->text($row['vendor_name'] ?? '');
        $email = strtolower($this->text($row['vendor_email'] ?? ''));
        $row['vendor_name'] = $name;
        $row['vendor_email'] = $email;
        $row['company_name'] = $this->text($row['company_name'] ?? '') ?: $name;
        $row['billing_name'] = $this->text($row['billing_name'] ?? '') ?: $row['company_name'];
        $row['same_as_billing'] = $this->nullableText($row['same_as_billing'] ?? null) ?? 'yes';

        return $row;
    }

    public function identity(array $row): string
    {
        $email = strtolower($this->text($row['vendor_email'] ?? ''));

        if ($email !== '') {
            return 'email:'.$email;
        }

        return 'vendor:'.strtolower($this->text($row['company_name'] ?? $row['vendor_name'] ?? ''));
    }

    public function validate(array $row, int $tenantId): array
    {
        $errors = [];
        foreach ($this->requiredFields() as $field) {
            if ($this->text($row[$field] ?? '') === '') {
                $errors[] = ucfirst(str_replace('_', ' ', $field)).' is required.';
            }
        }

        if (($email = $this->nullableText($row['vendor_email'] ?? null)) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Vendor email is invalid.';
        }

        if (!Role::where('name', 'vendor')->where('guard_name', 'web')->where('created_by', $tenantId)->exists()) {
            $errors[] = 'The vendor role is not configured for this company.';
        }

        return array_values(array_unique($errors));
    }

    public function duplicate(array $row, int $tenantId): bool
    {
        $email = strtolower($this->text($row['vendor_email'] ?? ''));
        if ($email !== '') {
            return Vendor::where('created_by', $tenantId)
                ->whereRaw('LOWER(contact_person_email) = ?', [$email])
                ->exists();
        }

        return Vendor::where('created_by', $tenantId)
            ->whereRaw('LOWER(company_name) = ?', [strtolower($this->text($row['company_name'] ?? $row['vendor_name'] ?? ''))])
            ->exists();
    }

    public function import(array $row, string $strategy, int $tenantId, int $actorId): string
    {
        $email = strtolower($this->text($row['vendor_email'] ?? ''));
        $vendor = $email !== ''
            ? Vendor::where('created_by', $tenantId)
                ->whereRaw('LOWER(contact_person_email) = ?', [$email])
                ->first()
            : Vendor::where('created_by', $tenantId)
                ->whereRaw('LOWER(company_name) = ?', [strtolower($this->text($row['company_name'] ?? $row['vendor_name'] ?? ''))])
                ->first();
        if ($vendor && $strategy === 'skip') {
            return 'skipped';
        }

        $user = $vendor?->user;

        if (!$user) {
            $user = app(ImportedClientUserService::class)->createImportContact([
                'name' => $this->text($row['company_name'] ?? $row['vendor_name']),
                'email' => $email,
                'mobile_no' => $this->nullableText($row['mobile_no'] ?? null),
            ], $tenantId, $actorId, 'vendor');
        }

        $user->update([
            'name' => $this->text($row['company_name']),
            'mobile_no' => $this->nullableText($row['mobile_no'] ?? null),
        ]);

        $billing = $this->address($row, 'billing');
        $sameAsBilling = $this->boolean($row['same_as_billing'] ?? true);
        $values = [
            'user_id' => $user->id,
            'company_name' => $this->text($row['company_name']),
            'contact_person_name' => $this->text($row['vendor_name']),
            'contact_person_email' => $this->nullableText($row['vendor_email'] ?? null),
            'contact_person_mobile' => $this->nullableText($row['mobile_no'] ?? null),
            'tax_number' => $this->nullableText($row['tax_number'] ?? null),
            'payment_terms' => $this->nullableText($row['payment_terms'] ?? null),
            'billing_address' => $billing,
            'shipping_address' => $sameAsBilling ? $billing : $this->address($row, 'shipping'),
            'same_as_billing' => $sameAsBilling,
            'notes' => $this->nullableText($row['notes'] ?? null),
        ];

        if ($vendor) {
            $vendor->update($values);
            UpdateVendor::dispatch(new Request($values), $vendor);
            return 'updated';
        }

        $vendor = Vendor::create($values + ['creator_id' => $actorId, 'created_by' => $tenantId]);
        CreateVendor::dispatch(new Request($values), $vendor);

        return 'imported';
    }
}

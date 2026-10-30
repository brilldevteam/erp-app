<?php

namespace App\Services\ZohoMigration;

use App\Services\BulkImport\Definitions\VendorDefinition;
use App\Services\BulkImport\EntityDefinition;

/**
 * Zoho contacts as ERP vendors. The vendor definition stores the email only as its match key, so it is
 * passed through and emails must be unique within the file.
 */
class ZohoVendorService extends ZohoContactImportService
{
    public function __construct(ZohoProfileCatalog $profiles, private readonly VendorDefinition $vendors)
    {
        parent::__construct($profiles);
    }

    protected function definition(): EntityDefinition
    {
        return $this->vendors;
    }

    protected function labels(): array
    {
        return ['stage_key' => 'vendors', 'contact_type' => 'vendor', 'singular' => 'vendor', 'plural' => 'vendors', 'other_step' => 'Customers', 'route' => 'account.vendors.index', 'email_field' => 'vendor_email'];
    }

    protected function names(string $displayName, string $person, string $email): array
    {
        // vendor_name becomes the vendor's contact person; company_name carries the Display Name.
        return ['vendor_name' => $person ?: $displayName, 'vendor_email' => $email];
    }

    protected function requiredNameMessage(): string
    {
        return 'Vendor name is required.';
    }

    protected function matchesByEmail(): bool
    {
        return true;
    }
}

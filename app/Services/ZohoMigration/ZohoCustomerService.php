<?php

namespace App\Services\ZohoMigration;

use App\Services\BulkImport\Definitions\CustomerDefinition;
use App\Services\BulkImport\EntityDefinition;

/**
 * Zoho contacts as ERP customers. The email is kept only as the contact person's email: the customer
 * definition matches by email when one is given, and several Zoho customers can share one.
 */
class ZohoCustomerService extends ZohoContactImportService
{
    public function __construct(ZohoProfileCatalog $profiles, private readonly CustomerDefinition $customers)
    {
        parent::__construct($profiles);
    }

    protected function definition(): EntityDefinition
    {
        return $this->customers;
    }

    protected function labels(): array
    {
        return ['stage_key' => 'customers', 'contact_type' => 'customer', 'singular' => 'customer', 'plural' => 'customers', 'other_step' => 'Vendors', 'route' => 'account.customers.index', 'email_field' => 'contact_person_email'];
    }

    protected function names(string $displayName, string $person, string $email): array
    {
        return ['user_name' => $displayName, 'user_email' => '', 'contact_person_name' => $person ?: $displayName, 'contact_person_email' => $email];
    }

    protected function requiredNameMessage(): string
    {
        return 'User name is required.';
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoCustomerService;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Customer;

class ZohoCustomerImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoho_contacts_are_validated_then_imported_as_customers(): void
    {
        [$company, $batch] = $this->customersBatch();
        $this->stage($batch, [
            $this->contact(['Display Name' => 'Novera Interiors', 'Company Name' => 'Novera Interiors', 'Salutation' => 'Mr.', 'First Name' => 'Nabil', 'Last Name' => 'Morssi',
                'EmailID' => 'n.morssi@example.com', 'MobilePhone' => '+97466339947', 'Payment Terms Label' => 'Net 30',
                'Billing Attention' => 'Novera Interiors', 'Billing Address' => "Al shomoukh Tower 10\n10th Floor", 'Billing City' => 'Doha', 'Billing Country' => 'Qatar']),
            $this->contact(['Display Name' => 'Feelix Solution PVT Ltd', 'Currency Code' => 'USD', 'Status' => 'Inactive',
                'Shipping Attention' => 'Feelix Solution PVT Ltd', 'Shipping Address' => 'Mansoura Doha Qatar', 'Shipping City' => 'Doha', 'Shipping Country' => 'Qatar']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'customers', $company->id);
        $this->assertSame(['accepted' => 2, 'rejected' => 0], array_intersect_key($validation, ['accepted' => 0, 'rejected' => 0]));
        $this->assertSame('ready', $batch->stages()->where('stage_key', 'customers')->value('status'));

        $counts = app(ZohoCustomerService::class)->import($batch, $company->id);

        $this->assertSame(['imported' => 2, 'updated' => 0, 'skipped' => 0], $counts);
        $novera = Customer::where('created_by', $company->id)->where('company_name', 'Novera Interiors')->firstOrFail();
        $this->assertSame('Mr. Nabil Morssi', $novera->contact_person_name);
        $this->assertSame('n.morssi@example.com', $novera->contact_person_email);
        $this->assertSame('Net 30', $novera->payment_terms);
        $this->assertSame('Al shomoukh Tower 10, 10th Floor', $novera->billing_address['address_line_1']);
        $feelix = Customer::where('created_by', $company->id)->where('company_name', 'Feelix Solution PVT Ltd')->firstOrFail();
        $this->assertTrue($feelix->same_as_billing, 'An incomplete Zoho shipping address must not fail the ERP shipping rules.');
        $this->assertStringContainsString('Zoho currency: USD', $feelix->notes);
        $this->assertStringContainsString('Zoho status: Inactive', $feelix->notes);
        $this->assertStringContainsString('Zoho shipping address: Feelix Solution PVT Ltd, Mansoura Doha Qatar, Doha, Qatar', $feelix->notes);
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'customers')->value('status'));
    }

    public function test_contacts_sharing_an_email_stay_separate_customers(): void
    {
        [$company, $batch] = $this->customersBatch();
        $this->stage($batch, [
            $this->contact(['Display Name' => 'Al Wathna Trading', 'EmailID' => 'h.seif@example.com']),
            $this->contact(['Display Name' => 'Al Wathna Projects', 'EmailID' => 'h.seif@example.com']),
        ]);

        app(ZohoMigrationValidator::class)->validate($batch, 'customers', $company->id);
        $counts = app(ZohoCustomerService::class)->import($batch, $company->id);

        $this->assertSame(2, $counts['imported']);
        $this->assertSame(2, Customer::where('created_by', $company->id)->where('contact_person_email', 'h.seif@example.com')->count());
    }

    public function test_case_only_duplicates_and_non_customer_contacts_are_rejected(): void
    {
        [$company, $batch] = $this->customersBatch();
        $this->stage($batch, [
            $this->contact(['Display Name' => 'Oishi Sushi']),
            $this->contact(['Display Name' => 'OISHI SUSHI']),
            $this->contact(['Display Name' => 'Paper Supplier', 'Contact Type' => 'vendor']),
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'customers', $company->id);

        $this->assertSame(2, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'customers')->pluck('message')->all();
        $this->assertContains("Display Name 'OISHI SUSHI' matches row 2 ('Oishi Sushi') apart from letter case. ERP treats them as one customer: rename or remove one of them in the file, then upload it again.", $messages);
        $this->assertContains('This Zoho contact is a vendor, not a customer. Import it in the Vendors step instead.', $messages);
        $this->expectExceptionMessage('Resolve all validation issues before importing customers.');
        app(ZohoCustomerService::class)->import($batch, $company->id);
    }

    public function test_rows_passed_by_an_older_check_are_rechecked_before_anything_is_imported(): void
    {
        [$company, $batch] = $this->customersBatch();
        $this->stage($batch, [
            $this->contact(['Display Name' => 'Takeaway']),
            $this->contact(['Display Name' => 'Oishi Sushi']),
            $this->contact(['Display Name' => 'OISHI SUSHI']),
        ]);
        // As left by the generic validator: every row "validated" in its raw normalized shape.
        ZohoMigrationStagedRow::where('batch_id', $batch->id)->update(['status' => 'validated', 'normalized_values' => json_encode(['display name' => 'x'])]);
        $batch->stages()->where('stage_key', 'customers')->update(['status' => 'ready', 'accepted_count' => 3, 'rejected_count' => 0]);

        try {
            app(ZohoCustomerService::class)->import($batch, $company->id);
            $this->fail('Stale rows were imported without the customer checks.');
        } catch (\RuntimeException $exception) {
            $this->assertStringStartsWith("Row 4: Display Name 'OISHI SUSHI' matches row 3", $exception->getMessage());
        }
        $this->assertSame(0, Customer::where('created_by', $company->id)->count());
    }

    public function test_blocked_duplicates_are_reported_before_import(): void
    {
        [$company, $batch] = $this->customersBatch();
        Customer::create(['company_name' => 'Takeaway', 'contact_person_name' => 'Takeaway', 'billing_address' => [], 'shipping_address' => [], 'same_as_billing' => true, 'creator_id' => $company->id, 'created_by' => $company->id]);
        $this->stage($batch, [$this->contact(['Display Name' => 'Takeaway'])]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'customers', $company->id);

        $this->assertSame(1, $validation['rejected']);
        $this->assertSame(['A customer with this name already exists in ERP and this migration is configured to block duplicates.'], $batch->issues()->where('stage_key', 'customers')->pluck('message')->all());
    }

    private function customersBatch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        // Created like User's company setup does: one client role per company, so Role::create's global name check is bypassed.
        $role = new Role();
        $role->name = 'client';
        $role->guard_name = 'web';
        $role->label = 'Client';
        $role->editable = false;
        $role->created_by = $company->id;
        $role->save();
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Customer import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'customers', 'position' => 7, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function contact(array $values): array
    {
        return $values + ['Currency Code' => 'QAR', 'Status' => 'Active', 'Contact Type' => 'customer', 'Payment Terms Label' => 'Due on Receipt', 'Contact ID' => (string) random_int(1000, 999999)];
    }

    private function stage(ZohoMigrationBatch $batch, array $contacts): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'customers',
            'uploaded_by' => $batch->created_by,
            'original_filename' => 'Customer Contacts.xlsx',
            'disk' => 'local',
            'path' => 'zoho-migrations/test/contacts.xlsx',
            'sha256' => hash('sha256', (string) microtime(true)),
            'size_bytes' => 1,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'status' => 'staged',
            'dataset_type' => 'contacts',
            'profile_key' => 'contacts.v1',
            'detection_confidence' => 100,
            'headers' => array_keys($contacts[0]),
            'row_count' => count($contacts),
        ]);
        foreach ($contacts as $index => $contact) {
            ZohoMigrationStagedRow::create([
                'batch_id' => $batch->id,
                'file_id' => $file->id,
                'tenant_id' => $batch->tenant_id,
                'sheet_name' => 'Contacts',
                'source_row_number' => $index + 2,
                'source_external_id' => $contact['Contact ID'],
                'original_values' => $contact,
                'status' => 'staged',
            ]);
        }
    }
}

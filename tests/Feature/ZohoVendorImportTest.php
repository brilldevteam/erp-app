<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationFile;
use App\Models\ZohoMigrationStage;
use App\Models\ZohoMigrationStagedRow;
use App\Services\ZohoMigration\ZohoMigrationValidator;
use App\Services\ZohoMigration\ZohoVendorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Workdo\Account\Models\Vendor;

class ZohoVendorImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zoho_vendors_are_validated_then_imported(): void
    {
        [$company, $batch] = $this->vendorsBatch();
        $this->stage($batch, [
            // Zoho's vendor export has no Contact Type column.
            ['Contact ID' => '1', 'Display Name' => 'Sign Plus Advertising', 'Salutation' => 'Mr.', 'First Name' => 'Ravi', 'Last Name' => 'Kumar', 'EmailID' => 'Sales@SignPlus.example',
                'MobilePhone' => '+97455550000', 'Payment Terms Label' => 'Net 15', 'Billing Address' => "Building 5\nIndustrial Area", 'Billing City' => 'Doha', 'Currency Code' => 'QAR', 'Status' => 'Active'],
            ['Contact ID' => '2', 'Display Name' => 'Print Media', 'Currency Code' => 'QAR', 'Status' => 'Active'],
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'vendors', $company->id);
        $this->assertSame(0, $validation['rejected']);

        $counts = app(ZohoVendorService::class)->import($batch, $company->id);

        $this->assertSame(['imported' => 2, 'updated' => 0, 'skipped' => 0], $counts);
        $sign = Vendor::where('created_by', $company->id)->where('company_name', 'Sign Plus Advertising')->firstOrFail();
        $this->assertSame('Mr. Ravi Kumar', $sign->contact_person_name);
        $this->assertSame('sales@signplus.example', $sign->contact_person_email);
        $this->assertSame('Net 15', $sign->payment_terms);
        $this->assertSame('Building 5, Industrial Area', $sign->billing_address['address_line_1']);
        $this->assertSame('Print Media', Vendor::where('created_by', $company->id)->where('company_name', 'Print Media')->value('contact_person_name'));
        $this->assertSame('completed', $batch->stages()->where('stage_key', 'vendors')->value('status'));
    }

    public function test_shared_emails_and_customer_contacts_are_flagged_and_nothing_imports(): void
    {
        [$company, $batch] = $this->vendorsBatch();
        $this->stage($batch, [
            ['Contact ID' => '1', 'Display Name' => 'Telet Technologies', 'EmailID' => 'accounts@example.com'],
            ['Contact ID' => '2', 'Display Name' => 'Mobi Commerce', 'EmailID' => 'ACCOUNTS@example.com'],
            ['Contact ID' => '3', 'Display Name' => 'Takeaway', 'Contact Type' => 'customer'],
        ]);

        $validation = app(ZohoMigrationValidator::class)->validate($batch, 'vendors', $company->id);

        $this->assertSame(2, $validation['rejected']);
        $messages = $batch->issues()->where('stage_key', 'vendors')->pluck('message')->all();
        $this->assertContains("Email accounts@example.com is also used by row 2 ('Telet Technologies'). ERP matches vendors by email, so they would merge into one: give each its own email, or clear one, then upload the file again.", $messages);
        $this->assertContains('This Zoho contact is a customer, not a vendor. Import it in the Customers step instead.', $messages);
        $this->assertSame(0, Vendor::where('created_by', $company->id)->count());
    }

    private function vendorsBatch(): array
    {
        $company = User::factory()->create(['type' => 'company', 'created_by' => null]);
        // Created like User's company setup does: one vendor role per company.
        $role = new Role();
        $role->name = 'vendor';
        $role->guard_name = 'web';
        $role->label = 'Vendor';
        $role->editable = false;
        $role->created_by = $company->id;
        $role->save();
        $batch = ZohoMigrationBatch::create([
            'tenant_id' => $company->id,
            'created_by' => $company->id,
            'name' => 'Vendor import test',
            'status' => 'active',
            'workflow_mode' => 'standalone',
            'accounting_mode' => 'rebuild',
            'base_currency' => 'QAR',
            'source_date_format' => 'dd/MM/yyyy',
            'inventory_cutover_at' => now(),
            'duplicate_strategy' => 'block',
            'ai_enabled' => false,
        ]);
        ZohoMigrationStage::create(['batch_id' => $batch->id, 'tenant_id' => $company->id, 'stage_key' => 'vendors', 'position' => 8, 'status' => 'uploaded']);

        return [$company, $batch];
    }

    private function stage(ZohoMigrationBatch $batch, array $contacts): void
    {
        $file = ZohoMigrationFile::create([
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'vendors',
            'uploaded_by' => $batch->created_by,
            'original_filename' => 'Vendors.csv',
            'disk' => 'local',
            'path' => 'zoho-migrations/test/vendors.csv',
            'sha256' => hash('sha256', (string) microtime(true)),
            'size_bytes' => 1,
            'mime_type' => 'text/csv',
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
                'sheet_name' => 'Vendors',
                'source_row_number' => $index + 2,
                'source_external_id' => $contact['Contact ID'],
                'original_values' => $contact,
                'status' => 'staged',
            ]);
        }
    }
}

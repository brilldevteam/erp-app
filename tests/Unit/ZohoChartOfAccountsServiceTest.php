<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoChartOfAccountsService;
use App\Services\ZohoMigration\ZohoProfileCatalog;
use PHPUnit\Framework\TestCase;

class ZohoChartOfAccountsServiceTest extends TestCase
{
    public function test_it_maps_the_original_zoho_export_without_requiring_a_rebuilt_template(): void
    {
        $service = new ZohoChartOfAccountsService(new ZohoProfileCatalog());

        $asset = $service->prepare([
            'Account Name' => 'Cash In Hand',
            'Account Code' => '1020101',
            'Description' => 'balance sheet',
            'Account Type' => 'Cash',
            'Account Status' => 'Active',
            'Parent Account' => '',
        ]);
        $liability = $service->prepare([
            'Account Name' => 'Credit Card',
            'Account Code' => '2050',
            'Account Type' => 'Credit Card',
            'Account Status' => 'Inactive',
            'Parent Account' => 'Current Liabilities',
        ]);

        self::assertSame('debit', $asset['normal_balance']);
        self::assertSame('Current Assets', $asset['account_type']);
        self::assertTrue($asset['is_active']);
        self::assertSame('credit', $liability['normal_balance']);
        self::assertSame('Current Liabilities', $liability['account_type']);
        self::assertFalse($liability['is_active']);
        self::assertSame('Current Liabilities', $liability['parent_account']);
    }

    public function test_execution_accepts_the_processing_state_used_by_the_queue(): void
    {
        self::assertContains('processing', ZohoChartOfAccountsService::EXECUTABLE_STATUSES);
    }

    public function test_import_transaction_captures_the_stage_it_completes(): void
    {
        $source = file_get_contents(__DIR__.'/../../app/Services/ZohoMigration/ZohoChartOfAccountsService.php');

        self::assertStringContainsString(
            'function () use ($rows, $definition, $batch, $actorId, $stage)',
            $source
        );
    }
}

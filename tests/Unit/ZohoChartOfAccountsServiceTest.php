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
        self::assertTrue($asset['is_active']);
        self::assertSame('credit', $liability['normal_balance']);
        self::assertFalse($liability['is_active']);
        self::assertSame('Current Liabilities', $liability['parent_account']);
    }
}

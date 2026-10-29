<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoStageCatalog;
use PHPUnit\Framework\TestCase;

class ZohoStageCatalogTest extends TestCase
{
    public function test_catalog_has_the_complete_strict_migration_sequence(): void
    {
        $stages = (new ZohoStageCatalog())->all();

        $this->assertCount(24, $stages);
        $this->assertSame(range(1, 24), array_column($stages, 'position'));
        $this->assertSame('configuration', $stages[0]['key']);
        $this->assertSame('chart_of_accounts', $stages[1]['key']);
        $this->assertSame('reconciliation', $stages[23]['key']);
        $this->assertSame('configuration', $stages[1]['previous_key']);
        $this->assertSame('accounting', $stages[23]['previous_key']);
    }

    public function test_only_verified_profiles_are_enabled(): void
    {
        $catalog = new ZohoStageCatalog();

        $this->assertTrue($catalog->find('sales_invoices')['supported']);
        $this->assertContains('sales_invoices', $catalog->find('sales_invoices')['datasets']);
        $this->assertNull($catalog->find('currencies'));
        $this->assertNull($catalog->find('does_not_exist'));
    }

    public function test_missing_zoho_master_data_uses_confirmed_manual_entry_stages(): void
    {
        $catalog = new ZohoStageCatalog();

        $this->assertTrue($catalog->find('items')['supported']);
        $this->assertTrue($catalog->find('items')['manual_entry']);
        $this->assertTrue($catalog->find('inventory_cutover')['supported']);
        $this->assertTrue($catalog->find('inventory_cutover')['manual_entry']);
    }
}

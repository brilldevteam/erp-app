<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoStageCatalog;
use PHPUnit\Framework\TestCase;

class ZohoStageCatalogTest extends TestCase
{
    public function test_catalog_has_the_complete_strict_migration_sequence(): void
    {
        $stages = (new ZohoStageCatalog())->all();

        $this->assertCount(23, $stages);
        $this->assertSame(range(1, 23), array_column($stages, 'position'));
        $this->assertSame('configuration', $stages[0]['key']);
        $this->assertSame('chart_of_accounts', $stages[1]['key']);
        $this->assertSame(['taxes', 'categories_units', 'items'], array_column(array_slice($stages, 2, 3), 'key'));
        $this->assertSame('categories_units', $stages[4]['previous_key']);
        $this->assertSame('reconciliation', $stages[22]['key']);
        $this->assertSame('configuration', $stages[1]['previous_key']);
        $this->assertSame('accounting', $stages[22]['previous_key']);
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

        foreach (['taxes', 'categories_units', 'warehouses'] as $stageKey) {
            $this->assertTrue($catalog->find($stageKey)['supported']);
            $this->assertTrue($catalog->find($stageKey)['manual_entry']);
        }
        $this->assertNull($catalog->find('units'));
        $this->assertNull($catalog->find('categories'));
        $this->assertTrue($catalog->find('items')['supported']);
        $this->assertTrue($catalog->find('items')['manual_entry']);
        $this->assertTrue($catalog->find('inventory_cutover')['supported']);
        $this->assertTrue($catalog->find('inventory_cutover')['manual_entry']);
    }
}

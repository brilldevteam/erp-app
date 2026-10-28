<?php

namespace Tests\Unit;

use App\Services\ZohoMigration\ZohoStageCatalog;
use PHPUnit\Framework\TestCase;

class ZohoStageCatalogTest extends TestCase
{
    public function test_catalog_has_the_complete_strict_migration_sequence(): void
    {
        $stages = (new ZohoStageCatalog())->all();

        $this->assertCount(25, $stages);
        $this->assertSame(range(1, 25), array_column($stages, 'position'));
        $this->assertSame('configuration', $stages[0]['key']);
        $this->assertSame('reconciliation', $stages[24]['key']);
        $this->assertSame('configuration', $stages[1]['previous_key']);
        $this->assertSame('accounting', $stages[24]['previous_key']);
    }

    public function test_only_verified_profiles_are_enabled(): void
    {
        $catalog = new ZohoStageCatalog();

        $this->assertTrue($catalog->find('sales_invoices')['supported']);
        $this->assertContains('sales_invoices', $catalog->find('sales_invoices')['datasets']);
        $this->assertFalse($catalog->find('currencies')['supported']);
        $this->assertNull($catalog->find('does_not_exist'));
    }
}

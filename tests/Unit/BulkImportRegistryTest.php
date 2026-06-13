<?php

namespace Tests\Unit;

use App\Services\BulkImport\BulkImportRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BulkImportRegistryTest extends TestCase
{
    public function test_registry_contains_only_supported_entities(): void
    {
        $this->assertSame(
            ['customers', 'product-service-items'],
            array_keys((new BulkImportRegistry())->all())
        );
    }

    public function test_registry_rejects_unknown_entities(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new BulkImportRegistry())->get('sales-invoices');
    }
}

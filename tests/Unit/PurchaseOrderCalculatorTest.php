<?php

namespace Tests\Unit;

use Tests\TestCase;
use Workdo\PurchaseOrder\Services\PurchaseOrderCalculator;

class PurchaseOrderCalculatorTest extends TestCase
{
    public function test_it_calculates_fractional_lines_discounts_taxes_and_charges(): void
    {
        $result = app(PurchaseOrderCalculator::class)->calculate([
            'items' => [[
                'item_name' => 'Custom material', 'quantity' => 2.5, 'unit_price' => 100,
                'discount_type' => 'percentage', 'discount_value' => 10,
                'taxes' => [['tax_name' => 'VAT', 'tax_rate' => 5]],
            ]],
            'document_discount_type' => 'fixed', 'document_discount_value' => 5,
            'shipping_amount' => 10, 'adjustment_amount' => -2,
        ]);

        $this->assertSame(250.0, $result['subtotal']);
        $this->assertSame(25.0, $result['line_discount_amount']);
        $this->assertSame(11.25, $result['tax_amount']);
        $this->assertSame(239.25, $result['total_amount']);
    }
}

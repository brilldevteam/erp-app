<?php

namespace Tests\Unit;

use App\Services\SalesLineAmounts;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SalesLineAmountsTest extends TestCase
{
    public function test_percentage_discount_is_applied_before_tax(): void
    {
        $this->assertSame([
            'subtotal' => 1000.0,
            'discount_amount' => 100.0,
            'tax_amount' => 45.0,
            'total_amount' => 945.0,
        ], SalesLineAmounts::calculate([
            'quantity' => 2, 'unit_price' => 500, 'discount_type' => 'percentage',
            'discount_percentage' => 10, 'tax_percentage' => 5,
        ]));
    }

    public function test_fixed_discount_is_applied_once_to_the_line_before_tax(): void
    {
        $this->assertSame([
            'subtotal' => 1000.0,
            'discount_amount' => 120.0,
            'tax_amount' => 44.0,
            'total_amount' => 924.0,
        ], SalesLineAmounts::calculate([
            'quantity' => 2, 'unit_price' => 500, 'discount_type' => 'fixed',
            'discount_value' => 120, 'discount_percentage' => 90, 'tax_percentage' => 5,
        ]));
    }

    public function test_fixed_discount_cannot_exceed_the_line_subtotal(): void
    {
        $this->expectException(ValidationException::class);
        SalesLineAmounts::calculate([
            'quantity' => 1, 'unit_price' => 100, 'discount_type' => 'fixed',
            'discount_value' => 120, 'tax_percentage' => 0,
        ]);
    }

    public function test_catalogue_html_is_converted_to_safe_multiline_text(): void
    {
        $this->assertSame("First line\nSecond line", SalesLineAmounts::description('<p>First line</p><div>Second <b>line</b></div>'));
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Workdo\Account\Services\CurrencyConversionService;

class CurrencyConversionServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once dirname(__DIR__, 2).'/packages/workdo/Account/src/Services/CurrencyConversionService.php';
    }

    #[DataProvider('conversionCases')]
    public function test_it_converts_and_rounds_transaction_amounts($amount, $rate, string $expected): void
    {
        $this->assertSame($expected, (new CurrencyConversionService())->toBase($amount, $rate));
    }

    public static function conversionCases(): array
    {
        return [
            'base currency' => [100, 1, '100.00'],
            'foreign currency' => ['125.50', '3.64000000', '456.82'],
            'rounds at ledger precision' => ['0.01', '3.33333333', '0.03'],
        ];
    }
}

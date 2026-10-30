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

    public function test_prices_entered_in_base_currency_keep_their_base_total(): void
    {
        $service = new class extends CurrencyConversionService {
            public function baseCurrency(?int $companyId = null): string
            {
                return 'QAR';
            }
        };

        $items = $service->lineItemsFromBase([['unit_price' => 1000, 'quantity' => 1]], 'USD', '3.65', 1);
        $total = round($items[0]['unit_price'], 2);
        $values = $service->transactionValuesFromBase(['currency_code' => 'USD', 'exchange_rate' => '3.65'], $total, 1000, 1);

        $this->assertSame(273.97, $total);
        $this->assertSame(['3.65003468', '1000.00'], [$values['exchange_rate'], $values['base_amount']]);
        $this->assertSame('1000.00', $service->toBase($total, $values['exchange_rate']));
        $this->assertSame('1.00000000', $service->transactionValuesFromBase(['currency_code' => 'QAR', 'exchange_rate' => 1], 1000, 1000, 1)['exchange_rate']);
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

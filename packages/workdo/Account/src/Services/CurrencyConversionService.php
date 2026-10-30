<?php

namespace Workdo\Account\Services;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use Workdo\Account\Models\CompanyCurrencyRate;

class CurrencyConversionService
{
    public function baseCurrency(?int $companyId = null): string
    {
        return strtoupper((string) (company_setting('defaultCurrency', $companyId ?: creatorId()) ?: 'USD'));
    }

    public function rate(string $currencyCode, CarbonInterface|string|null $date = null, ?int $companyId = null): string
    {
        $companyId ??= creatorId();
        $currencyCode = strtoupper($currencyCode);
        if ($currencyCode === $this->baseCurrency($companyId)) {
            return '1.00000000';
        }

        $rate = CompanyCurrencyRate::query()
            ->where('created_by', $companyId)
            ->where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->whereDate('effective_date', '<=', $date ?: now()->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->value('exchange_rate');

        if (!$rate) {
            throw ValidationException::withMessages([
                'currency_code' => __('No active exchange rate is configured for :currency.', ['currency' => $currencyCode]),
            ]);
        }

        return number_format((float) $rate, 8, '.', '');
    }

    public function validateRate(string $currencyCode, mixed $exchangeRate, ?int $companyId = null): string
    {
        $currencyCode = strtoupper($currencyCode);
        $rate = number_format((float) $exchangeRate, 8, '.', '');
        if ((float) $rate <= 0) {
            throw ValidationException::withMessages(['exchange_rate' => __('Exchange rate must be greater than zero.')]);
        }
        if ($currencyCode === $this->baseCurrency($companyId) && abs((float) $rate - 1.0) > 0.00000001) {
            throw ValidationException::withMessages(['exchange_rate' => __('The base currency exchange rate must be 1.')]);
        }
        return $rate;
    }

    public function toBase(mixed $amount, mixed $exchangeRate): string
    {
        return number_format(round((float) $amount * (float) $exchangeRate, 2), 2, '.', '');
    }

    public function transactionValues(array $data, string $amountKey, ?int $companyId = null): array
    {
        $currency = strtoupper((string) ($data['currency_code'] ?? $this->baseCurrency($companyId)));
        $rate = $this->validateRate($currency, $data['exchange_rate'] ?? $this->rate($currency, null, $companyId), $companyId);

        return [
            'currency_code' => $currency,
            'base_currency_code' => $this->baseCurrency($companyId),
            'exchange_rate' => $rate,
            'base_amount' => $this->toBase($data[$amountKey] ?? 0, $rate),
        ];
    }

    public function lineItemsFromBase(array $items, string $currencyCode, mixed $exchangeRate, ?int $companyId = null): array
    {
        $currencyCode = strtoupper($currencyCode);
        if ($currencyCode === $this->baseCurrency($companyId)) {
            return $items;
        }

        $rate = (float) $this->validateRate($currencyCode, $exchangeRate, $companyId);

        return array_map(function (array $item) use ($rate): array {
            $item['unit_price'] = round((float) ($item['unit_price'] ?? 0) / $rate, 6);
            if (($item['discount_type'] ?? 'percentage') === 'fixed') {
                $item['discount_value'] = round((float) ($item['discount_value'] ?? 0) / $rate, 6);
            }

            return $item;
        }, $items);
    }
}

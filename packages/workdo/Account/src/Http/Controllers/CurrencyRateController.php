<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Account\Models\CompanyCurrencyRate;
use Workdo\Account\Services\CurrencyConversionService;

class CurrencyRateController extends Controller
{
    public function index(CurrencyConversionService $currency): \Inertia\Response
    {
        $rates = CompanyCurrencyRate::query()
            ->where('created_by', creatorId())
            ->orderBy('currency_code')
            ->orderByDesc('effective_date')
            ->get();

        return Inertia::render('Account/SystemSetup/CurrencyRates/Index', [
            'rates' => $rates,
            'currencies' => config('default_currency.currencies', []),
            'baseCurrency' => $currency->baseCurrency(),
        ]);
    }

    public function latest(Request $request, CurrencyConversionService $currency): array
    {
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        return [
            'currency_code' => strtoupper($validated['currency_code']),
            'base_currency' => $currency->baseCurrency(),
            'exchange_rate' => $currency->rate($validated['currency_code'], $validated['date'] ?? null),
        ];
    }

    public function store(Request $request, CurrencyConversionService $currency)
    {
        $baseCurrency = $currency->baseCurrency();
        $validated = $request->validate([
            'currency_code' => ['required', 'string', 'size:3', Rule::notIn([$baseCurrency])],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => ['required', 'date'],
            'is_active' => ['boolean'],
        ]);

        $validated['currency_code'] = strtoupper($validated['currency_code']);
        $validated['exchange_rate'] = $currency->validateRate($validated['currency_code'], $validated['exchange_rate']);
        $validated['created_by'] = creatorId();
        $validated['updated_by'] = auth()->id();

        CompanyCurrencyRate::updateOrCreate(
            ['created_by' => creatorId(), 'currency_code' => $validated['currency_code'], 'effective_date' => $validated['effective_date']],
            $validated
        );

        return back()->with('success', __('Exchange rate saved successfully.'));
    }

    public function destroy(CompanyCurrencyRate $currencyRate)
    {
        abort_unless((int) $currencyRate->created_by === (int) creatorId(), 403);
        $currencyRate->delete();
        return back()->with('success', __('Exchange rate deleted successfully.'));
    }
}

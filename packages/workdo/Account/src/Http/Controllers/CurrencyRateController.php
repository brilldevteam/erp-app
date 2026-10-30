<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Workdo\Account\Models\CompanyCurrencyRate;
use Workdo\Account\Services\CurrencyConversionService;
use Workdo\Account\Models\ChartOfAccount;
use App\Models\Setting;

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
            'accounts' => ChartOfAccount::query()->where('created_by', creatorId())->orderBy('account_code')->get(['id', 'account_code', 'account_name']),
            'exchangeAccounts' => [
                'gain_account_id' => (string) (company_setting('exchangeGainAccountId', creatorId()) ?: ''),
                'loss_account_id' => (string) (company_setting('exchangeLossAccountId', creatorId()) ?: ''),
            ],
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

    public function updateExchangeAccounts(Request $request)
    {
        abort_unless(auth()->user()->can('manage-chart-of-accounts'), 403);
        $accountIds = ChartOfAccount::query()->where('created_by', creatorId())->pluck('id')->all();
        $validated = $request->validate([
            'gain_account_id' => ['required', 'integer', Rule::in($accountIds)],
            'loss_account_id' => ['required', 'integer', Rule::in($accountIds), 'different:gain_account_id'],
        ]);

        foreach (['exchangeGainAccountId' => $validated['gain_account_id'], 'exchangeLossAccountId' => $validated['loss_account_id']] as $key => $value) {
            Setting::updateOrCreate(['key' => $key, 'created_by' => creatorId()], ['value' => (string) $value]);
        }

        return back()->with('success', __('Exchange gain and loss accounts saved successfully.'));
    }
}

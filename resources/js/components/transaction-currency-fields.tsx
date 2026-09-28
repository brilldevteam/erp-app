import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/ui/input-error';
import { Checkbox } from '@/components/ui/checkbox';

type Currency = { code: string; name: string; symbol: string };

export default function TransactionCurrencyFields({
    currencyCode,
    exchangeRate,
    amount,
    transactionDate,
    onCurrencyChange,
    onRateChange,
    errors = {},
    disabled = false,
}: {
    currencyCode: string;
    exchangeRate: string | number;
    amount: string | number;
    transactionDate?: string;
    onCurrencyChange: (value: string) => void;
    onRateChange: (value: string) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
}) {
    const { t } = useTranslation();
    const page = usePage().props as any;
    const currencies: Currency[] = page.currencies || [];
    const baseCurrency = page.companyAllSetting?.defaultCurrency || 'USD';
    const [rateFocused, setRateFocused] = useState(false);
    const [multiCurrencyEnabled, setMultiCurrencyEnabled] = useState(
        Boolean(currencyCode && currencyCode !== baseCurrency),
    );

    useEffect(() => {
        if (!multiCurrencyEnabled && currencyCode !== baseCurrency) {
            onCurrencyChange(baseCurrency);
            onRateChange('1.00');
        }
    }, [baseCurrency, currencyCode, multiCurrencyEnabled]);

    const toggleMultiCurrency = (checked: boolean) => {
        setMultiCurrencyEnabled(checked);
        if (!checked) {
            onCurrencyChange(baseCurrency);
            onRateChange('1.00');
        }
    };

    const selectCurrency = async (value: string) => {
        onCurrencyChange(value);
        if (value === baseCurrency) {
            onRateChange('1.00000000');
            return;
        }
        const query = new URLSearchParams({ currency_code: value, ...(transactionDate ? { date: transactionDate } : {}) });
        const response = await fetch(`${route('account.currency-rates.latest')}?${query.toString()}`);
        if (response.ok) {
            const result = await response.json();
            onRateChange(result.exchange_rate);
        } else {
            onRateChange('');
        }
    };

    const converted = Number(amount || 0) * Number(exchangeRate || 0);
    const formattedRate = Number(exchangeRate || 0).toFixed(2);

    return <div className="space-y-3 md:col-span-2">
        <div className="flex items-center gap-2">
            <Checkbox
                id="multi-currency-transaction"
                checked={multiCurrencyEnabled}
                disabled={disabled}
                onCheckedChange={(checked) => toggleMultiCurrency(checked === true)}
            />
            <Label htmlFor="multi-currency-transaction" className="cursor-pointer">
                {t('Multi-Currency Transaction')}
            </Label>
        </div>

        {multiCurrencyEnabled && <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <Label required>{t('Currency')}</Label>
                <Select value={currencyCode || baseCurrency} onValueChange={selectCurrency} disabled={disabled}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>{currencies.filter((currency) => currency.code !== baseCurrency).map((currency) => <SelectItem key={currency.code} value={currency.code}>{currency.name} ({currency.code})</SelectItem>)}</SelectContent>
                </Select>
                <InputError message={errors.currency_code} />
            </div>
            <div>
                <Label required>{t('Exchange Rate')}</Label>
                <Input
                    type="number"
                    min="0.01"
                    step="0.01"
                    value={rateFocused ? exchangeRate : formattedRate}
                    disabled={disabled || currencyCode === baseCurrency}
                    onFocus={() => {
                        onRateChange(formattedRate);
                        setRateFocused(true);
                    }}
                    onBlur={() => {
                        onRateChange(formattedRate);
                        setRateFocused(false);
                    }}
                    onChange={(event) => onRateChange(event.target.value)}
                />
                <p className="mt-1 text-xs text-muted-foreground">1 {currencyCode || baseCurrency} = {Number(exchangeRate || 0).toFixed(2)} {baseCurrency}</p>
                <InputError message={errors.exchange_rate} />
            </div>
            <div className="rounded border bg-muted/30 px-3 py-2 text-sm md:col-span-2">
                {t('Base-currency equivalent')}: <strong>{baseCurrency} {converted.toFixed(2)}</strong>
            </div>
        </div>}
    </div>;
}

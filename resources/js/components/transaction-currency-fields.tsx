import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/ui/input-error';

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
    const baseCurrency = page.auth?.user?.settings?.defaultCurrency || page.company_settings?.defaultCurrency || 'USD';

    useEffect(() => {
        if (!currencyCode) onCurrencyChange(baseCurrency);
    }, [baseCurrency, currencyCode]);

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

    return <>
        <div>
            <Label required>{t('Currency')}</Label>
            <Select value={currencyCode || baseCurrency} onValueChange={selectCurrency} disabled={disabled}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{currencies.map((currency) => <SelectItem key={currency.code} value={currency.code}>{currency.name} ({currency.code})</SelectItem>)}</SelectContent>
            </Select>
            <InputError message={errors.currency_code} />
        </div>
        <div>
            <Label required>{t('Exchange Rate')}</Label>
            <Input type="number" min="0.00000001" step="0.00000001" value={exchangeRate} disabled={disabled || currencyCode === baseCurrency} onChange={(event) => onRateChange(event.target.value)} />
            <p className="mt-1 text-xs text-muted-foreground">1 {currencyCode || baseCurrency} = {Number(exchangeRate || 0).toFixed(8)} {baseCurrency}</p>
            <InputError message={errors.exchange_rate} />
        </div>
        <div className="md:col-span-2 rounded border bg-muted/30 px-3 py-2 text-sm">
            {t('Base-currency equivalent')}: <strong>{baseCurrency} {converted.toFixed(2)}</strong>
        </div>
    </>;
}

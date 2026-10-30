import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import InputError from '@/components/ui/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

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
    const [currencyOpen, setCurrencyOpen] = useState(false);
    const [currencyQuery, setCurrencyQuery] = useState('');
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

    const selectCurrency = (value: string) => {
        onCurrencyChange(value);
        if (value === baseCurrency) {
            onRateChange('1.00000000');
            return;
        }
        // A transaction rate is an auditable snapshot, so users enter the agreed rate explicitly.
        onRateChange('');
    };

    const converted = Number(amount || 0) * Number(exchangeRate || 0);
    const formattedRate = Number(exchangeRate || 0).toFixed(2);
    const availableCurrencies = currencies.filter((currency) => currency.code !== baseCurrency);
    const selectedCurrency = availableCurrencies.find((currency) => currency.code === currencyCode);
    const normalizedQuery = currencyQuery.trim().toLowerCase();
    const filteredCurrencies = normalizedQuery
        ? availableCurrencies.filter((currency) => `${currency.name} ${currency.code}`.toLowerCase().includes(normalizedQuery))
        : availableCurrencies;

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
                <Popover open={currencyOpen} onOpenChange={(open) => {
                    setCurrencyOpen(open);
                    if (!open) setCurrencyQuery('');
                }}>
                    <PopoverTrigger asChild>
                        <Button
                            type="button"
                            variant="outline"
                            role="combobox"
                            aria-expanded={currencyOpen}
                            disabled={disabled}
                            className="w-full justify-between font-normal"
                        >
                            <span className={cn('truncate', !selectedCurrency && 'text-muted-foreground')}>
                                {selectedCurrency ? `${selectedCurrency.name} (${selectedCurrency.code})` : t('Select currency')}
                            </span>
                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent align="start" className="w-[var(--radix-popover-trigger-width)] p-0">
                        <Command>
                            <CommandInput
                                value={currencyQuery}
                                onChange={(event) => setCurrencyQuery(event.target.value)}
                                placeholder={t('Search currency by name or code')}
                            />
                            <CommandList>
                                {filteredCurrencies.length === 0 ? <CommandEmpty>{t('No currency found')}</CommandEmpty> : <CommandGroup>
                                    {filteredCurrencies.map((currency) => <CommandItem
                                        key={currency.code}
                                        onSelect={() => {
                                            selectCurrency(currency.code);
                                            setCurrencyOpen(false);
                                            setCurrencyQuery('');
                                        }}
                                        className="gap-2"
                                    >
                                        <Check className={cn('h-4 w-4', currency.code === currencyCode ? 'opacity-100' : 'opacity-0')} />
                                        <span className="flex-1 truncate">{currency.name}</span>
                                        <span className="text-xs font-medium text-muted-foreground">{currency.code}</span>
                                    </CommandItem>)}
                                </CommandGroup>}
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
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

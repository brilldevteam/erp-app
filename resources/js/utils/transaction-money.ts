import { formatCurrency } from '@/utils/helpers';

export function formatTransactionMoney(amount: number | string, currencyCode: string): string {
    const value = Number(amount) || 0;
    const currency = (currencyCode || 'USD').toUpperCase();

    if (currency === 'QAR') {
        return formatCurrency(value);
    }

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency,
            currencyDisplay: 'code',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    } catch {
        return `${currency} ${value.toFixed(2)}`;
    }
}

export function baseToTransactionAmount(
    amount: number | string,
    currencyCode: string,
    baseCurrencyCode: string,
    exchangeRate: number | string,
): number {
    const value = Number(amount) || 0;
    if ((currencyCode || '').toUpperCase() === (baseCurrencyCode || '').toUpperCase()) {
        return value;
    }

    const rate = Number(exchangeRate) || 0;
    return rate > 0 ? value / rate : 0;
}

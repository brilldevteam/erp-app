export function formatTransactionMoney(amount: number | string, currencyCode: string): string {
    const value = Number(amount) || 0;
    const currency = (currencyCode || 'USD').toUpperCase();

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

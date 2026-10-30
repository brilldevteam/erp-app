type Props = {
    amount: number | string;
    currencyCode?: string | null;
    baseAmount?: number | string | null;
    baseCurrencyCode?: string | null;
    className?: string;
};

const money = (amount: number | string, currency: string) => new Intl.NumberFormat(undefined, {
    style: 'currency', currency, minimumFractionDigits: 2, maximumFractionDigits: 2,
}).format(Number(amount || 0));

export default function DualCurrencyAmount({ amount, currencyCode, baseAmount, baseCurrencyCode, className }: Props) {
    const transactionCurrency = currencyCode || baseCurrencyCode || 'USD';
    const baseCurrency = baseCurrencyCode || transactionCurrency;
    const showBase = transactionCurrency !== baseCurrency && baseAmount !== null && baseAmount !== undefined;

    return <span className={className}>
        <span className="block">{money(amount, transactionCurrency)}</span>
        {showBase && <span className="block text-xs font-normal text-muted-foreground">{money(baseAmount, baseCurrency)}</span>}
    </span>;
}

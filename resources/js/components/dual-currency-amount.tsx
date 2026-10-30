import { usePage } from '@inertiajs/react';
import { formatTransactionMoney } from '@/utils/transaction-money';

type Props = {
    amount: number | string;
    currencyCode?: string | null;
    baseAmount?: number | string | null;
    baseCurrencyCode?: string | null;
    className?: string;
    baseFirst?: boolean;
};

export default function DualCurrencyAmount({ amount, currencyCode, baseAmount, baseCurrencyCode, className, baseFirst = false }: Props) {
    const companyBaseCurrency = ((usePage().props as any).companyAllSetting?.defaultCurrency || 'QAR').toUpperCase();
    const transactionCurrency = (currencyCode || baseCurrencyCode || companyBaseCurrency).toUpperCase();
    const baseCurrency = (baseCurrencyCode || companyBaseCurrency).toUpperCase();
    const showBase = transactionCurrency !== baseCurrency && baseAmount !== null && baseAmount !== undefined;
    const primaryAmount = baseFirst && showBase ? baseAmount : amount;
    const primaryCurrency = baseFirst && showBase ? baseCurrency : transactionCurrency;
    const secondaryAmount = baseFirst ? amount : baseAmount;
    const secondaryCurrency = baseFirst ? transactionCurrency : baseCurrency;

    return <span className={className}>
        <span className="block">{formatTransactionMoney(primaryAmount, primaryCurrency)}</span>
        {showBase && <span className="block text-xs font-normal text-muted-foreground">{formatTransactionMoney(secondaryAmount!, secondaryCurrency)}</span>}
    </span>;
}

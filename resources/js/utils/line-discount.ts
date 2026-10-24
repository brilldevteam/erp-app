import { formatCurrency } from '@/utils/helpers';

type DiscountLine = {
    discount_type?: string | null;
    discount_percentage?: number | string | null;
    discount_amount?: number | string | null;
};

/**
 * How a line discount is shown on documents: a fixed (cash) discount shows only its amount,
 * a percentage discount shows only its percentage. Lines saved with an amount but no
 * percentage (e.g. imported from Zoho) count as fixed. Returns null when there is no discount.
 */
export function lineDiscountLabel(item: DiscountLine, money: (amount: number) => string = formatCurrency): string | null {
    const amount = Number(item.discount_amount) || 0;
    if (amount <= 0) {
        return null;
    }

    const percentage = Number(item.discount_percentage) || 0;
    return item.discount_type === 'fixed' || percentage <= 0 ? money(amount) : `${percentage}%`;
}

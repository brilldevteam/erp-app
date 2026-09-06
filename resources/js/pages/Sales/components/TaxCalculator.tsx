import React from 'react';

interface TaxCalculation {
    subtotal: number;
    taxAmount: number;
    discountAmount: number;
    total: number;
}

interface Props {
    items: Array<{
        quantity: number;
        unit_price: number;
        discount_amount: number;
        tax_amount: number;
        total_amount: number;
    }>;
}

export function useTaxCalculator(items: Props['items']): TaxCalculation {
    return React.useMemo(() => {
        const subtotal = items.reduce((sum, item) => {
            return sum + (item.quantity * item.unit_price);
        }, 0);
        
        const discountAmount = items.reduce((sum, item) => sum + Number(item.discount_amount || 0), 0);
        const taxAmount = items.reduce((sum, item) => sum + Number(item.tax_amount || 0), 0);
        const total = items.reduce((sum, item) => sum + Number(item.total_amount || 0), 0);

        return {
            subtotal,
            taxAmount,
            discountAmount,
            total
        };
    }, [items]);
}

export function calculateLineItemAmounts(
    quantity: number,
    unitPrice: number,
    discountPercentage: number = 0,
    taxPercentage: number = 0,
    discountType: 'percentage' | 'fixed' = 'percentage',
    discountValue: number = 0
) {
    const round = (value: number) => Math.round((value + Number.EPSILON) * 100) / 100;
    const lineTotal = round(quantity * unitPrice);
    const discountAmount = round(discountType === 'fixed' ? Number(discountValue) : (lineTotal * discountPercentage) / 100);
    const afterDiscount = lineTotal - discountAmount;
    const taxAmount = round((afterDiscount * taxPercentage) / 100);
    const totalAmount = round(afterDiscount + taxAmount);

    return {
        discountAmount,
        taxAmount,
        totalAmount
    };
}

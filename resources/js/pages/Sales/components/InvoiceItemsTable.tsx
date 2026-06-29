import React from 'react';
import { useTranslation } from 'react-i18next';
import { InvoiceTaxOption, SalesInvoiceItem } from '../types';
import ProductSelector from './ProductSelector';
import { calculateLineItemAmounts } from './TaxCalculator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { Trash2 } from 'lucide-react';
import { formatCurrency } from '@/utils/helpers';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Props {
    items: SalesInvoiceItem[];
    onChange: (items: SalesInvoiceItem[]) => void;
    errors: any;
    products?: Array<{id: number; name: string; description?: string; sale_price: number; unit?: string; stock_quantity?: number; taxes?: Array<{id: number; tax_name: string; rate: number}>}>;
    taxTypes?: InvoiceTaxOption[];
    showAddButton?: boolean;
    invoiceType?: string;
}

export default function InvoiceItemsTable({ items, onChange, errors, products = [], taxTypes = [], showAddButton = true, invoiceType = 'product' }: Props) {
    const { t } = useTranslation();

    const addItem = () => {
        const newItem: SalesInvoiceItem = {
            product_id: 0,
            quantity: 1,
            unit_price: 0,
            discount_percentage: 0,
            discount_amount: 0,
            tax_percentage: 0,
            tax_amount: 0,
            total_amount: 0,
            taxes: []
        };
        onChange([...items, newItem]);
    };

    const removeItem = (index: number) => {
        const newItems = items.filter((_, i) => i !== index);
        onChange(newItems);
    };

    const updateItem = (index: number, field: keyof SalesInvoiceItem, value: any) => {
        const newItems = [...items];
        newItems[index] = { ...newItems[index], [field]: value };

        const item = newItems[index];

        const calculations = calculateLineItemAmounts(
            item.quantity,
            item.unit_price,
            item.discount_percentage,
            item.tax_percentage
        );

        item.discount_amount = calculations.discountAmount;
        item.tax_amount = calculations.taxAmount;
        item.total_amount = calculations.totalAmount;

        onChange(newItems);
    };

    const getSelectedTaxId = (item: SalesInvoiceItem) => {
        const currentTax = item.taxes?.[0];
        if (!currentTax) return undefined;

        return taxTypes.find((tax) =>
            tax.tax_name === currentTax.tax_name && Number(tax.rate) === Number(currentTax.tax_rate)
        )?.id.toString();
    };

    const handleTaxSelect = (index: number, taxId: string) => {
        const newItems = [...items];
        const item = { ...newItems[index] };
        const selectedTax = taxTypes.find((tax) => tax.id.toString() === taxId);

        item.taxes = selectedTax ? [{
            id: selectedTax.id,
            tax_name: selectedTax.tax_name,
            tax_rate: Number(selectedTax.rate)
        }] : [];
        item.tax_percentage = selectedTax ? Number(selectedTax.rate) : 0;

        const calculations = calculateLineItemAmounts(
            item.quantity,
            item.unit_price,
            item.discount_percentage,
            item.tax_percentage
        );

        item.discount_amount = calculations.discountAmount;
        item.tax_amount = calculations.taxAmount;
        item.total_amount = calculations.totalAmount;
        newItems[index] = item;
        onChange(newItems);
    };

    const handleProductSelect = (index: number, productId: number, product?: any) => {
        const newItems = [...items];
        const totalTaxRate = product?.taxes?.reduce((sum: number, tax: any) => sum + Number(tax.rate), 0) || 0;
        const taxes = product?.taxes?.map((tax: any) => ({
            id: tax.id,
            tax_name: tax.tax_name,
            tax_rate: tax.rate
        })) || [];

        newItems[index] = {
            ...newItems[index],
            product_id: productId,
            unit_price: Number(product?.sale_price) || 0,
            tax_percentage: Number(totalTaxRate) || 0,
            taxes: taxes
        };

        const item = newItems[index];
        item.quantity = Number(item.quantity) || 1;
        item.discount_percentage = Number(item.discount_percentage) || 0;

        const calculations = calculateLineItemAmounts(
            item.quantity,
            item.unit_price,
            item.discount_percentage,
            item.tax_percentage
        );

        item.discount_amount = Number(calculations.discountAmount) || 0;
        item.tax_amount = Number(calculations.taxAmount) || 0;
        item.total_amount = Number(calculations.totalAmount) || 0;

        onChange(newItems);
    };

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr className="border-b border-border">
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {invoiceType === 'service' ? t('Service') : t('Product')} <span className="text-red-500">*</span>
                            </th>
                            {invoiceType === 'product' && (
                                <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                    {t('Qty')} <span className="text-red-500">*</span>
                                </th>
                            )}
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Unit Price')} <span className="text-red-500">*</span>
                            </th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Discount')} %
                            </th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Tax')}
                            </th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Total')}
                            </th>
                            <th className="px-4 py-3 text-center text-sm font-semibold text-foreground">
                                {t('Action')}
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {items.map((item, index) => (
                            <tr key={index}>
                                <td className="px-4 py-4">
                                    <ProductSelector
                                        products={products}
                                        value={item.product_id}
                                        itemType={invoiceType}
                                        onChange={(productId, product) => handleProductSelect(index, productId, product)}
                                    />
                                    {products.find((product) => product.id === item.product_id)?.description && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {products.find((product) => product.id === item.product_id)?.description}
                                        </p>
                                    )}
                                    <InputError message={errors[`items.${index}.product_id`]} />
                                </td>
                                {invoiceType === 'product' && (
                                    <td className="px-4 py-4">
                                        {(() => {
                                            const product = products.find(p => p.id === item.product_id);
                                            const hasStockLimit = product?.stock_quantity !== undefined;
                                            const maxQty = hasStockLimit ? product.stock_quantity : undefined;
                                            return (
                                                <div>
                                                    <Input
                                                        type="number"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value) || 0)}
                                                        className="w-20 text-sm"
                                                        min="1"
                                                        max={maxQty}
                                                        step="1"
                                                        required
                                                    />
                                                    {product && hasStockLimit && (
                                                        <div className="text-xs text-muted-foreground mt-1">
                                                            {t('Stock')}: {product.stock_quantity}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })()}
                                        <InputError message={errors[`items.${index}.quantity`]} />
                                    </td>
                                )}
                                <td className="px-4 py-4">
                                    <Input
                                        type="number"
                                        value={item.unit_price}
                                        onChange={(e) => updateItem(index, 'unit_price', parseFloat(e.target.value) || 0)}
                                        className="w-24 text-sm"
                                        min="0"
                                        step="0.01"
                                        required
                                    />
                                    <InputError message={errors[`items.${index}.unit_price`]} />
                                </td>
                                <td className="px-4 py-4">
                                    <Input
                                        type="number"
                                        value={item.discount_percentage}
                                        onChange={(e) => updateItem(index, 'discount_percentage', parseFloat(e.target.value) || 0)}
                                        className="w-20 text-sm"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                    />
                                </td>
                                <td className="px-4 py-4">
                                    <Select
                                        value={getSelectedTaxId(item) ?? ''}
                                        onValueChange={(value) => handleTaxSelect(index, value)}
                                    >
                                        <SelectTrigger className="w-40">
                                            <SelectValue placeholder=" " />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">{t('No Tax')}</SelectItem>
                                            {taxTypes.map((tax) => (
                                                <SelectItem key={tax.id} value={tax.id.toString()}>
                                                    {tax.tax_name} ({tax.rate}%)
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </td>
                                <td className="px-4 py-4">
                                    <span className="text-sm font-medium">
                                        {formatCurrency(item.total_amount)}
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-center">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeItem(index)}
                                        className="text-red-600 hover:text-red-800 h-8 w-8 p-0"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {showAddButton && (
                <div className="flex justify-start">
                    <Button
                        type="button"
                        onClick={addItem}
                        variant="default"
                        size="sm"
                    >
                        + {t('Add Item')}
                    </Button>
                </div>
            )}

            <InputError message={errors.items} />
        </div>
    );
}

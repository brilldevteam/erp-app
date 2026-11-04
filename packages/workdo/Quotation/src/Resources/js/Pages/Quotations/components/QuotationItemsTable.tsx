import SalesLineDiscount from '@/components/sales-line-discount';
import { Textarea } from '@/components/ui/textarea';
import React from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { QuotationItem } from '../types';
import ProductSelector from './ProductSelector';
import { calculateLineItemAmounts } from './TaxCalculator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { ListPlus, Plus, Trash2 } from 'lucide-react';
import { getCurrencySymbolForCode } from '@/utils/helpers';
import { baseToTransactionAmount, formatTransactionMoney } from '@/utils/transaction-money';
import TaxField, { TaxOption } from '@/components/tax-field';

interface Props {
    items: QuotationItem[];
    onChange: (items: QuotationItem[]) => void;
    errors: any;
    products?: Array<{id: number; name: string; description?: string; sale_price: number; unit?: string; stock_quantity?: number; taxes?: Array<{id: number; tax_name: string; rate: number}>}>;
    taxTypes?: TaxOption[];
    showAddButton?: boolean;
    onClearError?: (field: string) => void;
    currencyCode?: string;
    baseCurrencyCode?: string;
    exchangeRate?: string | number;
    canCreateItem?: boolean;
    onCreateItem?: () => void;
    onAddMultipleItems?: () => void;
    canManageTaxes?: boolean;
    canCreateTaxes?: boolean;
    canEditTaxes?: boolean;
    canDeleteTaxes?: boolean;
}

export default function QuotationItemsTable({ items, onChange, errors, products = [], taxTypes = [], showAddButton = true, onClearError, currencyCode = 'USD', baseCurrencyCode = 'USD', exchangeRate = 1, canCreateItem = false, onCreateItem, onAddMultipleItems, canManageTaxes = false, canCreateTaxes = false, canEditTaxes = false, canDeleteTaxes = false }: Props) {
    const { t } = useTranslation();
    const currencySymbol = getCurrencySymbolForCode(currencyCode, usePage().props);
    const [availableTaxes, setAvailableTaxes] = React.useState(taxTypes);

    React.useEffect(() => setAvailableTaxes(taxTypes), [taxTypes]);

    const addItem = () => {
        const newItem: QuotationItem = {
            product_id: null,
            item_name: '',
            quantity: 1,
            unit_price: 0,
            description: '',
            discount_type: 'percentage',
            discount_value: 0,
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

    const updateItem = (index: number, field: keyof QuotationItem, value: any) => {
        const newItems = [...items];
        newItems[index] = { ...newItems[index], [field]: value };

        const item = newItems[index];
        if (field === 'discount_type') {
            item.discount_percentage = 0;
            item.discount_value = 0;
        }

        if (item.tax_percentage === 0 && Number(item.product_id) > 0) {
            const product = products.find(p => p.id === item.product_id);
            if (product?.taxes?.length) {
                item.tax_percentage = product.taxes.reduce((sum, tax) => sum + tax.rate, 0);
            }
        }

        const calculations = calculateLineItemAmounts(
            item.quantity,
            item.unit_price,
            item.discount_percentage,
            item.tax_percentage,
            item.discount_type,
            item.discount_value
        );

        item.discount_amount = calculations.discountAmount;
        item.tax_amount = calculations.taxAmount;
        item.total_amount = calculations.totalAmount;

        onChange(newItems);
        const isValid = field === 'description'
            ? String(value).trim().length > 0
            : field === 'quantity'
                ? Number(value) > 0
                : field === 'unit_price'
                    ? Number(value) >= 0
                    : value !== null && value !== undefined && value !== '';
        if (isValid) {
            onClearError?.(`items.${index}.${field}`);
        }
    };

    const handleProductSelect = (index: number, productId: number, product?: any) => {
        const newItems = [...items];
        const totalTaxRate = product?.taxes?.reduce((sum: number, tax: any) => sum + Number(tax.rate), 0) || 0;
        const taxes = product?.taxes?.map((tax: any) => ({
            tax_name: tax.tax_name,
            tax_rate: tax.rate
        })) || [];

        newItems[index] = {
            ...newItems[index],
            product_id: productId,
            item_name: product?.name || newItems[index].item_name || '',
            description: product?.description || '',
            unit_price: baseToTransactionAmount(Number(product?.sale_price) || 0, currencyCode, baseCurrencyCode, exchangeRate),
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
            item.tax_percentage,
            item.discount_type,
            item.discount_value
        );

        item.discount_amount = Number(calculations.discountAmount) || 0;
        item.tax_amount = Number(calculations.taxAmount) || 0;
        item.total_amount = Number(calculations.totalAmount) || 0;

        onChange(newItems);
        if (productId > 0) {
            onClearError?.(`items.${index}.product_id`);
        }
    };

    const getSelectedTaxId = (item: QuotationItem) => {
        const currentTax = item.taxes?.[0];
        if (!currentTax) return 'none';
        if (currentTax.id) return String(currentTax.id);

        return availableTaxes.find((tax) =>
            tax.tax_name === currentTax.tax_name && Number(tax.rate) === Number(currentTax.tax_rate)
        )?.id.toString() ?? 'none';
    };

    const handleTaxSelect = (index: number, selectedTax: TaxOption | null) => {
        const newItems = [...items];
        const item = { ...newItems[index] };

        item.taxes = selectedTax ? [{
            id: selectedTax.id,
            tax_name: selectedTax.tax_name,
            tax_rate: Number(selectedTax.rate),
        }] : [];
        item.tax_percentage = selectedTax ? Number(selectedTax.rate) : 0;

        const calculations = calculateLineItemAmounts(
            item.quantity,
            item.unit_price,
            item.discount_percentage,
            item.tax_percentage,
            item.discount_type,
            item.discount_value
        );

        item.discount_amount = calculations.discountAmount;
        item.tax_amount = calculations.taxAmount;
        item.total_amount = calculations.totalAmount;
        newItems[index] = item;
        onChange(newItems);
    };

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead>
                        <tr className="border-b border-border">
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Item Details')} <span className="text-red-500">*</span>
                            </th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Qty')} <span className="text-red-500">*</span>
                            </th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Unit Price')} ({currencySymbol}) <span className="text-red-500">*</span>
                            </th>
                            <th className="px-4 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Discount')}
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
                            <tr key={index} className="align-top">
                                <td className="px-4 py-4">
                                    <ProductSelector
                                        products={products}
                                        value={Number(item.product_id) || 0}
                                        manualValue={item.item_name || ''}
                                        currencyCode={currencyCode}
                                        baseCurrencyCode={baseCurrencyCode}
                                        exchangeRate={exchangeRate}
                                        canCreateItem={canCreateItem}
                                        onCreateItem={onCreateItem}
                                        onChange={(productId, product) => handleProductSelect(index, productId, product)}
                                        onManualChange={(value) => {
                                            const nextItems = [...items];
                                            nextItems[index] = { ...nextItems[index], product_id: null, item_name: value };
                                            onChange(nextItems);
                                            if (value.trim()) {
                                                onClearError?.(`items.${index}.item_name`);
                                                onClearError?.(`items.${index}.product_id`);
                                            }
                                        }}
                                    />
                                    <InputError message={errors[`items.${index}.item_name`] || errors[`items.${index}.product_id`]} />
                                    <Textarea aria-label={t('Item description')} placeholder={t('Description')}
                                        className="mt-2 min-w-[260px] min-h-[100px]" rows={4} maxLength={20000}
                                        value={item.description ?? ''}
                                        onChange={e => updateItem(index, 'description', e.target.value)} />
                                    <InputError message={errors[`items.${index}.description`]} />
                                </td>
                                <td className="px-4 py-4">
                                    {(() => {
                                        const product = products.find(p => p.id === item.product_id);
                                        const hasStockLimit = product?.stock_quantity !== undefined;
                                        const maxQty = hasStockLimit && Number(product.stock_quantity) > 0 ? product.stock_quantity : undefined;
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
                                    <SalesLineDiscount item={item} onChange={(field, value) => updateItem(index, field, value)} />
                                    <InputError message={errors[`items.${index}.discount_percentage`] || errors[`items.${index}.discount_value`] || errors[`items.${index}.discount_type`]} />
                                </td>
                                <td className="px-4 py-4">
                                    <TaxField
                                        value={getSelectedTaxId(item)}
                                        taxes={availableTaxes}
                                        onChange={(tax) => handleTaxSelect(index, tax)}
                                        onTaxesChange={setAvailableTaxes}
                                        canManage={canManageTaxes}
                                        canCreate={canCreateTaxes}
                                        canEdit={canEditTaxes}
                                        canDelete={canDeleteTaxes}
                                    />
                                </td>
                                <td className="px-4 py-4">
                                    <span className="text-sm font-medium">
                                        {formatTransactionMoney(item.total_amount, currencyCode)}
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
                <div className="flex flex-wrap justify-start gap-2">
                    <Button
                        type="button"
                        onClick={addItem}
                        variant="outline"
                        size="sm"
                    >
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add New Row')}
                    </Button>
                    {onAddMultipleItems && <Button type="button" onClick={onAddMultipleItems} variant="outline" size="sm">
                        <ListPlus className="mr-2 h-4 w-4" />
                        {t('Add Multiple Items')}
                    </Button>}
                </div>
            )}

            <InputError message={errors.items} />
        </div>
    );
}

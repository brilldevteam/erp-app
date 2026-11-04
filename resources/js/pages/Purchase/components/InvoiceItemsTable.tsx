import React from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BillAccountOption, PurchaseInvoiceItem } from '../types';
import ProductSelector from './ProductSelector';
import { calculateLineItemAmounts } from './TaxCalculator';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { InputError } from '@/components/ui/input-error';
import { ListPlus, Plus, Trash2 } from 'lucide-react';
import { baseToTransactionAmount, formatTransactionMoney } from '@/utils/transaction-money';
import SalesLineDiscount from '@/components/sales-line-discount';
import { getCurrencySymbolForCode } from '@/utils/helpers';
import TaxField, { TaxOption } from '@/components/tax-field';

interface Props {
    items: PurchaseInvoiceItem[];
    onChange: (items: PurchaseInvoiceItem[]) => void;
    errors: any;
    products?: Array<{id: number; name: string; purchase_price?: number; unit?: string; type?: string; taxes?: Array<{id: number; tax_name: string; rate: number}>}>;
    accounts?: BillAccountOption[];
    taxTypes?: TaxOption[];
    showAddButton?: boolean;
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

export default function InvoiceItemsTable({ items, onChange, errors, products = [], accounts = [], taxTypes = [], showAddButton = true, currencyCode = 'USD', baseCurrencyCode = 'USD', exchangeRate = 1, canCreateItem = false, onCreateItem, onAddMultipleItems, canManageTaxes = false, canCreateTaxes = false, canEditTaxes = false, canDeleteTaxes = false }: Props) {
    const { t } = useTranslation();
    const currencySymbol = getCurrencySymbolForCode(currencyCode, usePage().props);
    const inventoryAccountId = accounts.find(account => account.account_code === '1200')?.id ?? null;
    const [availableTaxes, setAvailableTaxes] = React.useState(taxTypes);

    React.useEffect(() => setAvailableTaxes(taxTypes), [taxTypes]);
    const addItem = () => {
        const newItem: PurchaseInvoiceItem = {
            product_id: null,
            item_name: '',
            description: '',
            quantity: 1,
            unit_price: 0,
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

    const updateItem = (index: number, field: keyof PurchaseInvoiceItem, value: any) => {
        const newItems = [...items];
        newItems[index] = { ...newItems[index], [field]: value };

        // Recalculate amounts
        const item = newItems[index];
        if (field === 'discount_type') {
            item.discount_percentage = 0;
            item.discount_value = 0;
        }

        // If tax_percentage is 0 but product has taxes, recalculate tax_percentage
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
            // Stock products are bought into Inventory unless another account is chosen; services keep the chosen account
            account_id: product?.type === 'product' ? (newItems[index].account_id || inventoryAccountId) : (newItems[index].account_id ?? null),
            unit_price: baseToTransactionAmount(Number(product?.purchase_price) || 0, currencyCode, baseCurrencyCode, exchangeRate),
            unit: product?.unit || newItems[index].unit,
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
    };

    const getSelectedTaxId = (item: PurchaseInvoiceItem) => {
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
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground min-w-[200px]">
                                {t('Item Details')} <span className="text-red-500">*</span>
                            </th>
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Account')} <span className="text-red-500">*</span>
                            </th>
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Qty')} <span className="text-red-500">*</span>
                            </th>
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Unit Price')} ({currencySymbol}) <span className="text-red-500">*</span>
                            </th>
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Discount')}
                            </th>
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Tax')}
                            </th>
                            <th className="px-2 py-3 text-left text-sm font-semibold text-foreground">
                                {t('Total')}
                            </th>
                            <th className="px-2 py-3 text-center text-sm font-semibold text-foreground">
                                {t('Action')}
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                        {items.map((item, index) => (
                            <tr key={index} className="[&>td]:align-top">
                                <td className="px-2 py-4 min-w-[200px]">
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
                                        }}
                                    />
                                    <textarea
                                        value={item.description || ''}
                                        onChange={(event) => updateItem(index, 'description', event.target.value)}
                                        placeholder={t('Item description')}
                                        rows={3}
                                        className="mt-2 min-h-20 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError message={errors[`items.${index}.item_name`] || errors[`items.${index}.product_id`]} />
                                    <InputError message={errors[`items.${index}.description`]} />
                                </td>
                                <td className="px-2 py-4">
                                    <Select
                                        value={item.account_id ? String(item.account_id) : ''}
                                        onValueChange={(value) => updateItem(index, 'account_id', Number(value))}
                                    >
                                        <SelectTrigger className="w-44 text-sm">
                                            <SelectValue placeholder={t('Select account')} />
                                        </SelectTrigger>
                                        <SelectContent searchable={true}>
                                            {accounts.map(account => (
                                                <SelectItem key={account.id} value={String(account.id)}>
                                                    {account.account_code} - {account.account_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors[`items.${index}.account_id`]} />
                                </td>
                                <td className="px-2 py-4">
                                    <Input
                                        type="number"
                                        value={item.quantity}
                                        onChange={(e) => updateItem(index, 'quantity', parseInt(e.target.value) || 0)}
                                        className="w-20 text-sm"
                                        min="1"
                                        step="1"
                                        required
                                    />
                                    <InputError message={errors[`items.${index}.quantity`]} />
                                </td>
                                <td className="px-2 py-4">
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
                                <td className="px-2 py-4">
                                    <SalesLineDiscount item={item} onChange={(field, value) => updateItem(index, field, value)} />
                                    <InputError message={errors[`items.${index}.discount_percentage`] || errors[`items.${index}.discount_value`] || errors[`items.${index}.discount_type`]} />
                                </td>
                                <td className="px-2 py-4">
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
                                <td className="px-2 py-4">
                                    <span className="text-sm font-medium whitespace-nowrap">
                                        {formatTransactionMoney(item.total_amount, currencyCode)}
                                    </span>
                                </td>
                                <td className="px-2 py-4 text-center">
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

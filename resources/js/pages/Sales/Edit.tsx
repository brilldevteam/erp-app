import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useFormFields } from '@/hooks/useFormFields';
import { InvoiceCustomerOption, InvoiceTaxOption, SalesInvoice, SalesInvoiceItem } from './types';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import InvoiceItemsTable from './components/InvoiceItemsTable';
import ProductPickerDialog, { QuotationProduct } from '@/components/product-picker-dialog';
import { useTaxCalculator, calculateLineItemAmounts } from './components/TaxCalculator';
import { formatCurrency } from '@/utils/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { InputError } from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Separator } from '@/components/ui/separator';
import { CalendarDays, Package } from 'lucide-react';

interface EditProps {
    invoice: SalesInvoice;
    customers: InvoiceCustomerOption[];
    taxes: InvoiceTaxOption[];
    warehouses: Array<{id: number; name: string; address: string}>;
    documentTemplates: Array<{ id: number; name: string; is_default: boolean }>;
    productCatalog: {
        categories: Array<{ id: number; name: string }>;
        units: Array<{ id: number; unit_name: string }>;
        taxes: Array<{ id: number; tax_name: string; rate: number }>;
    };
    auth: { user: { permissions?: string[] } };
    [key: string]: any;
}

export default function Edit() {
    const { t } = useTranslation();
    const { invoice, customers, taxes = [], warehouses, documentTemplates = [], productCatalog, auth } = usePage<EditProps>().props;
    const [availableProducts, setAvailableProducts] = useState<QuotationProduct[]>([]);
    const [isProductPickerOpen, setIsProductPickerOpen] = useState(false);
    const [productsLoading, setProductsLoading] = useState(false);

    useFlashMessages();

    const { data, setData, put, processing, errors, clearErrors } = useForm({
        ...invoice,
        customer_id: invoice.customer_id.toString(),
        warehouse_id: invoice.warehouse_id?.toString() || '',
        document_template_id: invoice.document_template_id?.toString() || '',
        type: invoice.type || 'product',
        items: (invoice.items || []).map(item => {
            const calculations = calculateLineItemAmounts(
                item.quantity,
                item.unit_price,
                item.discount_percentage,
                item.tax_percentage,
                item.discount_type,
                item.discount_value
            );
            return {
                ...item,
                taxes: item.taxes || [],
                discount_amount: calculations.discountAmount,
                tax_amount: calculations.taxAmount,
                total_amount: calculations.totalAmount
            };
        }) as SalesInvoiceItem[]
    });

    // Load products for the current warehouse on component mount
    useEffect(() => {
        if (data.type === 'product') {
            handleWarehouseChange(data.warehouse_id || 'none');
        } else if (data.type === 'service') {
            loadServices();
        }
    }, []);

    const handleWarehouseChange = async (value: string) => {
        const warehouseId = value === 'none' ? '' : value;
        setData('warehouse_id', warehouseId);
        clearErrors('warehouse_id');

        try {
            setProductsLoading(true);
            const query = warehouseId ? `?warehouse_id=${warehouseId}` : '';
            const response = await fetch(route('sales-invoices.warehouse.products') + query);
            if (!response.ok) throw new Error(`Invoice product request failed with status ${response.status}`);
            setAvailableProducts(await response.json());
        } catch (error) {
            console.error('Failed to fetch invoice products:', error);
            setAvailableProducts([]);
        } finally {
            setProductsLoading(false);
        }
    };

    const loadServices = async () => {
        try {
            setProductsLoading(true);
            const response = await fetch(route('sales-invoices.services'));
            if (!response.ok) throw new Error(`Invoice service request failed with status ${response.status}`);
            const services = await response.json();
            setAvailableProducts(services);
        } catch (error) {
            console.error('Failed to fetch services:', error);
            setAvailableProducts([]);
        } finally {
            setProductsLoading(false);
        }
    };

    const addProducts = (products: QuotationProduct[]) => {
        const selectedItems = products.map(product => {
            const taxPercentage = product.taxes?.reduce((sum, tax) => sum + Number(tax.rate), 0) || 0;
            const price = Number(product.sale_price) || 0;
            const amounts = calculateLineItemAmounts(1, price, 0, taxPercentage, 'percentage', 0);
            return {
                product_id: product.id, quantity: 1, unit_price: price, description: product.description || '',
                discount_type: 'percentage' as const, discount_value: 0, discount_percentage: 0,
                discount_amount: amounts.discountAmount, tax_percentage: taxPercentage,
                tax_amount: amounts.taxAmount, total_amount: amounts.totalAmount,
                taxes: product.taxes?.map(tax => ({ id: tax.id, tax_name: tax.tax_name, tax_rate: Number(tax.rate) })) || [],
            } as SalesInvoiceItem;
        });
        setData(current => ({
            ...current,
            items: [...current.items.filter(item => item.product_id > 0), ...selectedItems],
        }));
        const productErrors = Object.keys(errors).filter(key => /^items\.\d+\.product_id$/.test(key));
        if (productErrors.length) clearErrors(...productErrors as any);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('sales-invoices.update', invoice.id));
    };

    const totals = useTaxCalculator(data.items);

    // Recurring fields hook
    const recurringFields = useFormFields('salesInvoiceEditFields', data, setData, errors, 'edit', invoice);

    // Commission plan fields hook
    const commissionFields = useFormFields('commissionPlanBtn', data, setData, errors, 'edit');

    // Sage fields hook
    const sageFields = useFormFields('salesInvoiceFields', data, setData, errors, 'edit', t);

    // Custom fields hook
    const customFields = useFormFields('getCustomFields', { ...data, module: 'General', sub_module: 'Sales Invoice', id: invoice.id }, setData, errors, 'edit', t);
    return (
        <AuthenticatedLayout
            breadcrumbs={[
                {label: t('Sales Invoice'), url: route('sales-invoices.index')},
                {label: t('Edit Sales Invoice')}
            ]}
            pageTitle={t('Edit Sales Invoice')}
        >
            <Head title={t('Edit Sales Invoice')} />

            <div>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <CalendarDays className="h-5 w-5" />
                                {t('Sales Invoice Details')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <Label htmlFor="invoice_date" required>
                                        {t('Invoice Date')}
                                    </Label>
                                    <DatePicker
                                        id="invoice_date"
                                        value={data.invoice_date}
                                        onChange={(value) => {
                                            setData('invoice_date', value);
                                            if (value) clearErrors('invoice_date');
                                        }}
                                        required
                                    />
                                    <InputError message={errors.invoice_date} />
                                </div>

                                <div>
                                    <Label htmlFor="due_date" required>
                                        {t('Due Date')}
                                    </Label>
                                    <DatePicker
                                        id="due_date"
                                        value={data.due_date}
                                        onChange={(value) => {
                                            setData('due_date', value);
                                            if (value) clearErrors('due_date');
                                        }}
                                        required
                                    />
                                    <InputError message={errors.due_date} />
                                </div>

                                <div>
                                    <Label htmlFor="customer_id" required>
                                        {t('Customer')}
                                    </Label>
                                    <Select value={data.customer_id} onValueChange={(value) => {
                                        setData('customer_id', value);
                                        if (value) clearErrors('customer_id');
                                    }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select Customer')} />
                                        </SelectTrigger>
                                        <SelectContent searchable>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer.id} value={customer.id.toString()}>
                                                    {customer.company_name || customer.name}
                                                    {customer.contact_person_name && customer.contact_person_name !== customer.company_name ? ` — ${customer.contact_person_name}` : ''}
                                                    {customer.email ? ` — ${customer.email}` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.customer_id} />
                                </div>

                                {data.type === 'product' && (
                                    <div>
                                        <Label htmlFor="warehouse_id">
                                            {t('Warehouse')}
                                        </Label>
                                        <Select value={data.warehouse_id || 'none'} onValueChange={handleWarehouseChange}>
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('Select Warehouse')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">{t('No Warehouse')}</SelectItem>
                                                {warehouses.map((warehouse) => (
                                                    <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                                                        {warehouse.name} - {warehouse.address}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.warehouse_id} />
                                    </div>
                                )}

                                <div>
                                    <Label htmlFor="document_template_id">
                                        {t('Template')}
                                    </Label>
                                    <Select value={data.document_template_id || 'default'} onValueChange={(value) => {
                                        setData('document_template_id', value === 'default' ? '' : value);
                                        clearErrors('document_template_id');
                                    }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Default Template')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="default">{t('Default Template')}</SelectItem>
                                            {documentTemplates.map((template) => (
                                                <SelectItem key={template.id} value={template.id.toString()}>
                                                    {template.name}{template.is_default ? ` (${t('Default')})` : ''}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.document_template_id} />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                <div>
                                    <Label htmlFor="payment_terms">
                                        {t('Payment Terms')}
                                    </Label>
                                    <Input
                                        id="payment_terms"
                                        value={data.payment_terms}
                                        onChange={(e) => setData('payment_terms', e.target.value)}
                                        placeholder={t('e.g., Net 30')}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="subject">{t('Subject')}</Label>
                                    <Input id="subject" value={data.subject || ''} onChange={(e) => {
                                        setData('subject', e.target.value);
                                        if (e.target.value.trim()) clearErrors('subject');
                                    }} placeholder={t('e.g., Event Coverage')} />
                                    <InputError message={errors.subject} />
                                </div>

                                <div>
                                    <Label htmlFor="notes">
                                        {t('Notes')}
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        rows={2}
                                        placeholder={t('Additional notes...')}
                                    />
                                </div>
                            </div>

                            {/* Recurring Sales Invoice */}
                                <div className="mt-6">
                                    {recurringFields.map((field) => (
                                        <div key={field.id} className="mb-4">{field.component}</div>
                                    ))}
                                </div>
                             {/* Commission Plan Fields */}
                             <div className="mt-6">
                                {commissionFields.map((field) => (
                                    <div key={field.id}>
                                        {field.component}
                                    </div>
                                ))}
                            </div>

                            {/* Sage Fields */}
                            <div className="mt-6">
                                {sageFields.map((field) => (
                                    <div key={field.id}>
                                        {field.component}
                                    </div>
                                ))}
                            </div>

                            {/* Custom Fields */}
                            {customFields.length > 0 && (
                                <div className="mt-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {customFields.map((field) => (
                                            <div key={field.id}>
                                                {field.component}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Package className="h-5 w-5" />
                                    {t('Sales Invoice Items')}
                                </CardTitle>
                                <Button
                                    type="button"
                                    onClick={() => setIsProductPickerOpen(true)}
                                    variant="default"
                                    size="sm"
                                >
                                    + {t(data.type === 'service' ? 'Add Service' : 'Add Product')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <InvoiceItemsTable
                                items={data.items}
                                onChange={(items) => setData('items', items)}
                                errors={errors}
                                products={availableProducts}
                                taxTypes={taxes}
                                showAddButton={false}
                                invoiceType={data.type}
                                onClearError={(field) => clearErrors(field as any)}
                            />

                            <div className="mt-6 flex justify-end">
                                <div className="w-80 bg-muted/30 rounded-lg p-4">
                                    <h3 className="font-semibold mb-3">{t('Invoice Summary')}</h3>
                                    <div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">{t('Subtotal')}</span>
                                            <span className="font-medium">{formatCurrency(totals.subtotal)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">{t('Discount')}</span>
                                            <span className="font-medium text-red-600">-{formatCurrency(totals.discountAmount)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-muted-foreground">{t('Tax')}</span>
                                            <span className="font-medium">{formatCurrency(totals.taxAmount)}</span>
                                        </div>
                                        <Separator className="my-2" />
                                        <div className="flex justify-between">
                                            <span className="font-semibold">{t('Total')}</span>
                                            <span className="font-bold text-lg">{formatCurrency(totals.total)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <ProductPickerDialog
                        open={isProductPickerOpen}
                        onOpenChange={setIsProductPickerOpen}
                        products={availableProducts}
                        categories={productCatalog?.categories || []}
                        units={productCatalog?.units || []}
                        taxes={productCatalog?.taxes || taxes}
                        warehouses={warehouses}
                        warehouseId={data.warehouse_id}
                        defaultProductType={data.type as 'product' | 'service'}
                        catalogMode={data.type === 'service' ? 'service' : 'stock'}
                        canCreateProduct={auth.user.permissions?.includes('create-product-service-item') || false}
                        loading={productsLoading}
                        onAdd={addProducts}
                        onProductCreated={product => setAvailableProducts(current => [...current, product])}
                    />



                    <div className="flex justify-between items-center">
                        <div className="text-sm text-muted-foreground">
                            {data.items.length} {t('items added')}
                        </div>
                        <div className="flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.history.back()}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || data.items.length === 0}
                            >
                                {processing ? t('Updating...') : t('Update')}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useFormFields } from '@/hooks/useFormFields';
import { SalesInvoiceItem } from './types';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import InvoiceItemsTable from './components/InvoiceItemsTable';
import { useTaxCalculator } from './components/TaxCalculator';
import { formatCurrency } from '@/utils/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { InputError } from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { DatePicker } from '@/components/ui/date-picker';
import { Separator } from '@/components/ui/separator';
import { CalendarDays, Building2, User, FileText, Package } from 'lucide-react';

interface CreateProps {
    customers: Array<{ id: number; name: string; email: string }>;
    warehouses: Array<{ id: number; name: string; address: string }>;
    documentTemplates: Array<{ id: number; name: string; is_default: boolean }>;
    initialProducts?: Array<{
        id: number;
        name: string;
        description?: string;
        sale_price: number;
        unit?: string;
        stock_quantity?: number;
        taxes?: Array<{ id: number; tax_name: string; rate: number }>;
    }>;
    initialInvoice?: {
        invoice_number: string;
        quotation_id: number;
        quotation_number: string;
        quotation_date: string;
        invoice_date: string;
        due_date: string;
        customer_id: string;
        warehouse_id: string;
        document_template_id?: string;
        type: 'product' | 'service';
        payment_terms: string;
        notes: string;
        items: SalesInvoiceItem[];
    };
    [key: string]: any;
}

export default function Create() {
    const { t } = useTranslation();
    const { customers, warehouses, documentTemplates = [], initialInvoice, initialProducts = [] } = usePage<CreateProps>().props;
    const [availableProducts, setAvailableProducts] = useState<any[]>(initialProducts);

    useFlashMessages();
    const { data, setData, post, processing, errors } = useForm({
        quotation_id: initialInvoice?.quotation_id ?? null,
        invoice_date: initialInvoice?.invoice_date ?? new Date().toISOString().split('T')[0],
        due_date: initialInvoice?.due_date ?? '',
        customer_id: initialInvoice?.customer_id ?? '',
        warehouse_id: initialInvoice?.warehouse_id ?? '',
        document_template_id: initialInvoice?.document_template_id ?? documentTemplates.find((template) => template.is_default)?.id?.toString() ?? '',
        type: initialInvoice?.type ?? 'product',
        payment_terms: initialInvoice?.payment_terms ?? '',
        notes: initialInvoice?.notes ?? '',
        items: initialInvoice?.items ?? [{
            product_id: 0,
            quantity: 1,
            unit_price: 0,
            discount_percentage: 0,
            discount_amount: 0,
            tax_percentage: 0,
            tax_amount: 0,
            total_amount: 0
        }] as SalesInvoiceItem[]
    });

    // Calendar sync fields
    const calendarFields = useFormFields('getCalendarSyncFields', data, setData, errors, 'create', t, 'Sales');

    useEffect(() => {
        loadAvailableProducts(data.type, data.warehouse_id);
    }, []);

    const mergeInitialProducts = (products: any[]) => {
        const merged = new Map<number, any>();
        [...initialProducts, ...products].forEach((product) => merged.set(product.id, product));
        return Array.from(merged.values());
    };

    const loadAvailableProducts = async (type: string, warehouseId: string) => {
        try {
            const url = type === 'service'
                ? route('sales-invoices.services')
                : route('sales-invoices.warehouse.products') + (warehouseId ? `?warehouse_id=${warehouseId}` : '');
            const response = await fetch(url);
            setAvailableProducts(mergeInitialProducts(await response.json()));
        } catch (error) {
            console.error('Failed to fetch invoice items:', error);
            setAvailableProducts(initialProducts);
        }
    };

    const handleWarehouseChange = async (value: string, resetItems = true) => {
        const warehouseId = value === 'none' ? '' : value;
        setData('warehouse_id', warehouseId);
        await loadAvailableProducts('product', warehouseId);

        if (resetItems) {
            setData('items', [{
                product_id: 0,
                quantity: 1,
                unit_price: 0,
                discount_percentage: 0,
                discount_amount: 0,
                tax_percentage: 0,
                tax_amount: 0,
                total_amount: 0
            }]);
        }
    };

    const handleTypeChange = async (type: 'product' | 'service') => {
        setData('type', type);

        if (type === 'service') {
            setData('warehouse_id', '');
            await loadAvailableProducts('service', '');
        } else {
            setData('warehouse_id', '');
            await loadAvailableProducts('product', '');
        }

        // Reset items when type changes
        setData('items', [{
            product_id: 0,
            quantity: 1,
            unit_price: 0,
            discount_percentage: 0,
            discount_amount: 0,
            tax_percentage: 0,
            tax_amount: 0,
            total_amount: 0
        }]);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('sales-invoices.store'));
    };

    const totals = useTaxCalculator(data.items);

    // Recurring fields hook
    const recurringFields = useFormFields('salesInvoiceCreateFields', data, setData, errors, 'create');

    // Commission plan fields hook
    const commissionFields = useFormFields('commissionPlanBtn', data, setData, errors, 'create');

    // Sage fields hook
    const sageFields = useFormFields('salesInvoiceFields', data, setData, errors, 'create', t);

    // Custom fields hook
    const customFields = useFormFields('getCustomFields', { ...data, module: 'General', sub_module: 'Sales Invoice' }, setData, errors, 'create', t);

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Sales Invoice'), url: route('sales-invoices.index') },
                { label: t('Create Sales Invoice') }
            ]}
            pageTitle={t('Create Sales Invoice')}
        >
            <Head title={t('Create Sales Invoice')} />

            <div>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <CalendarDays className="h-5 w-5" />
                                    {t('Sales Invoice Details')}
                                </CardTitle>
                                <div className="flex items-center gap-2">
                                    <RadioGroup
                                        value={data.type}
                                        onValueChange={(value) => handleTypeChange(value as 'product' | 'service')}
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="product" id="type-product" />
                                            <Label htmlFor="type-product" className="cursor-pointer font-normal">{t('Product Wise')}</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="service" id="type-service" />
                                            <Label htmlFor="type-service" className="cursor-pointer font-normal">{t('Service Wise')}</Label>
                                        </div>
                                    </RadioGroup>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {initialInvoice && (
                                <div className="mb-5 grid gap-3 rounded-lg border border-primary/20 bg-primary/5 p-4 sm:grid-cols-3">
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Invoice Number')}</p>
                                        <p className="font-semibold">{initialInvoice.invoice_number}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Quotation Reference')}</p>
                                        <p className="font-semibold">{initialInvoice.quotation_number}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground">{t('Quotation Date')}</p>
                                        <p className="font-semibold">{initialInvoice.quotation_date}</p>
                                    </div>
                                </div>
                            )}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <Label htmlFor="invoice_date" required>
                                        {t('Invoice Date')}
                                    </Label>
                                    <DatePicker
                                        id="invoice_date"
                                        value={data.invoice_date}
                                        onChange={(value) => setData('invoice_date', value)}
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
                                        onChange={(value) => setData('due_date', value)}
                                        required
                                    />
                                    <InputError message={errors.due_date} />
                                </div>

                                <div>
                                    <Label htmlFor="customer_id" required>
                                        {t('Customer')}
                                    </Label>
                                    <Select value={data.customer_id} onValueChange={(value) => setData('customer_id', value)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select Customer')} />
                                        </SelectTrigger>
                                        <SelectContent searchable>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer.id} value={customer.id.toString()}>
                                                    {customer.name} - {customer.email}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.customer_id} />
                                    <InputError message={(errors as any).quotation_id} />
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
                                    <Select value={data.document_template_id || 'default'} onValueChange={(value) => setData('document_template_id', value === 'default' ? '' : value)}>
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

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
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

                            {/* Calendar Sync Field */}
                            <div className="mt-6">
                                {/* Calendar Sync Fields */}
                                {calendarFields.map((field) => (
                                    <div className='m-3' key={field.id}>
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
                                    onClick={() => {
                                        const newItem = {
                                            product_id: 0,
                                            quantity: 1,
                                            unit_price: 0,
                                            discount_percentage: 0,
                                            discount_amount: 0,
                                            tax_percentage: 0,
                                            tax_amount: 0,
                                            total_amount: 0
                                        };
                                        setData('items', [...data.items, newItem]);
                                    }}
                                    variant="default"
                                    size="sm"
                                >
                                    + {t('Add Item')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <InvoiceItemsTable
                                items={data.items}
                                onChange={(items) => setData('items', items)}
                                errors={errors}
                                products={availableProducts}
                                showAddButton={false}
                                invoiceType={data.type}
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
                                {processing ? t('Creating...') : t('Create')}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

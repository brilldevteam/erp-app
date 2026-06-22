import React, { useState, useEffect } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { QuotationItem } from './types';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import QuotationItemsTable from './components/QuotationItemsTable';
import { useTaxCalculator } from './components/TaxCalculator';
import { formatCurrency } from '@/utils/helpers';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { InputError } from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog } from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Separator } from '@/components/ui/separator';
import { CalendarDays, Package } from 'lucide-react';
import CreateCustomer from '../../../../../../Account/src/Resources/js/Pages/Customers/Create';
import CreateWarehouse from '@/pages/warehouses/create';

interface CreateProps {
    customers: Array<{id: number; name: string; email: string}>;
    customerUsers: Array<{id: number; name: string; email: string; mobile_no?: string}>;
    warehouses: Array<{id: number; name: string; address: string}>;
    documentTemplates: Array<{ id: number; name: string; is_default: boolean }>;
    auth: {
        user: {
            permissions?: string[];
        };
    };
    [key: string]: any;
}

export default function Create() {
    const { t } = useTranslation();
    const { customers, customerUsers, warehouses, documentTemplates = [], auth } = usePage<CreateProps>().props;
    const [availableProducts, setAvailableProducts] = useState([]);
    const [isCustomerModalOpen, setIsCustomerModalOpen] = useState(false);
    const [isWarehouseModalOpen, setIsWarehouseModalOpen] = useState(false);
    const noWarehouseValue = 'none';
    const addCustomerValue = 'add-new-customer';
    const addWarehouseValue = 'add-new-warehouse';

    useFlashMessages();
    const { data, setData, post, processing, errors } = useForm({
        invoice_date: new Date().toISOString().split('T')[0],
        due_date: '',
        customer_id: '',
        warehouse_id: '',
        document_template_id: documentTemplates.find((template) => template.is_default)?.id?.toString() ?? '',
        payment_terms: '',
        notes: '',
        items: [{
            product_id: 0,
            quantity: 1,
            unit_price: 0,
            discount_percentage: 0,
            discount_amount: 0,
            tax_percentage: 0,
            tax_amount: 0,
            total_amount: 0
        }] as QuotationItem[]
    });

    const emptyItem = (): QuotationItem => ({
        product_id: 0,
        quantity: 1,
        unit_price: 0,
        discount_percentage: 0,
        discount_amount: 0,
        tax_percentage: 0,
        tax_amount: 0,
        total_amount: 0
    });

    const loadProducts = async (warehouseId: string) => {
        const query = warehouseId ? `?warehouse_id=${warehouseId}` : '';

        try {
            const response = await fetch(route('quotations.warehouse.products') + query);
            if (!response.ok) {
                throw new Error(`Product request failed with status ${response.status}`);
            }
            setAvailableProducts(await response.json());
        } catch (error) {
            console.error('Failed to fetch quotation products:', error);
            setAvailableProducts([]);
        }
    };

    useEffect(() => {
        loadProducts('');
    }, []);

    const handleWarehouseChange = (value: string) => {
        if (value === addWarehouseValue) {
            setIsWarehouseModalOpen(true);
            return;
        }

        const warehouseId = value === noWarehouseValue ? '' : value;
        setData(data => ({
            ...data,
            warehouse_id: warehouseId,
            items: [emptyItem()]
        }));
        loadProducts(warehouseId);
    };

    const handleWarehouseCreated = (warehouse?: { id: number; name: string; address: string }) => {
        setIsWarehouseModalOpen(false);
        if (warehouse) {
            handleWarehouseChange(warehouse.id.toString());
        }
    };



    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('quotations.store'));
    };

    const handleCustomerChange = (value: string) => {
        if (value === addCustomerValue) {
            setIsCustomerModalOpen(true);
            return;
        }

        setData('customer_id', value);
    };

    const handleCustomerCreated = (userId?: number) => {
        setIsCustomerModalOpen(false);
        if (userId) {
            setData('customer_id', userId.toString());
        }
    };

    const totals = useTaxCalculator(data.items);

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                {label: t('Quotations'), url: route('quotations.index')},
                {label: t('Create Quotation')}
            ]}
            pageTitle={t('Create Quotation')}
        >
            <Head title={t('Create Quotation')} />

            <div>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <CalendarDays className="h-5 w-5" />
                                {t('Quotation Details')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <Label htmlFor="invoice_date" required>
                                        {t('Quotation Date')}
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
                                    <Select value={data.customer_id} onValueChange={handleCustomerChange}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select Customer')} />
                                        </SelectTrigger>
                                        <SelectContent searchable>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer.id} value={customer.id.toString()}>
                                                    {customer.name} - {customer.email}
                                                </SelectItem>
                                            ))}
                                            {auth.user.permissions?.includes('create-customers') && (
                                                <SelectItem
                                                    value={addCustomerValue}
                                                    className="mt-1 border-t border-border pt-2 font-medium text-primary focus:text-primary"
                                                >
                                                    + {t('Add New Customer')}
                                                </SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.customer_id} />
                                </div>

                                <div>
                                    <Label htmlFor="warehouse_id">
                                        {t('Warehouse')}
                                    </Label>
                                    <Select value={data.warehouse_id || noWarehouseValue} onValueChange={handleWarehouseChange}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select Warehouse')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={noWarehouseValue}>
                                                {t('No Warehouse')}
                                            </SelectItem>
                                            {warehouses.map((warehouse) => (
                                                <SelectItem key={warehouse.id} value={warehouse.id.toString()}>
                                                    {warehouse.name} - {warehouse.address}
                                                </SelectItem>
                                            ))}
                                            {auth.user.permissions?.includes('create-warehouses') && (
                                                <SelectItem
                                                    value={addWarehouseValue}
                                                    className="mt-1 border-t border-border pt-2 font-medium text-primary focus:text-primary"
                                                >
                                                    + {t('Add New Warehouse')}
                                                </SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.warehouse_id} />
                                </div>

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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Package className="h-5 w-5" />
                                    {t('Quotation Items')}
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
                            <QuotationItemsTable
                                items={data.items}
                                onChange={(items) => setData('items', items)}
                                errors={errors}
                                products={availableProducts}
                                showAddButton={false}
                            />

                            <div className="mt-6 flex justify-end">
                                <div className="w-80 bg-muted/30 rounded-lg p-4">
                                    <h3 className="font-semibold mb-3">{t('Quotation Summary')}</h3>
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

            <Dialog open={isCustomerModalOpen} onOpenChange={setIsCustomerModalOpen}>
                {isCustomerModalOpen && (
                    <CreateCustomer
                        onSuccess={handleCustomerCreated}
                        users={customerUsers}
                        auth={{ user: { permissions: auth.user.permissions ?? [] } }}
                        returnToCurrentPage
                    />
                )}
            </Dialog>

            <Dialog open={isWarehouseModalOpen} onOpenChange={setIsWarehouseModalOpen}>
                {isWarehouseModalOpen && (
                    <CreateWarehouse
                        onSuccess={handleWarehouseCreated}
                        quotationContext
                    />
                )}
            </Dialog>
        </AuthenticatedLayout>
    );
}

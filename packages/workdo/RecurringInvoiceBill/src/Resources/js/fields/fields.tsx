import React, { useState, useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { getCompanySetting, isPackageActive } from '@/utils/helpers';
import { usePage } from '@inertiajs/react';

const RecurringFormComponent = ({ data, setData, invoiceType = 'sales' }: any) => {
    const { t } = useTranslation();

    const recurringTypes = {
        'no'        : 'No',
        '1 day'     : 'Every 1 Day',
        '2 day'     : 'Every 2 Day',
        '3 day'     : 'Every 3 Day',
        '1 week'    : 'Every 1 Week',
        '2 week'    : 'Every 2 Week',
        '1 month'   : 'Every 1 Month',
        '2 month'   : 'Every 2 Month',
        '3 month'   : 'Every 3 Month',
        '6 month'   : 'Every 6 Month',
        '1 year'    : 'Every 1 Year',
        'custom'    : 'Custom',
    };

    const dayTypes = {
        'day'       : 'Day(s)',
        'week'      : 'Week(s)',
        'month'     : 'Month(s)',
        'year'      : 'Year(s)',
    };

    const getTitle = () => {
        return invoiceType === 'sales'
            ? 'Recurring Sales Invoice?'
            : 'Recurring Purchase Invoice?';
    };

    return (
        <Card>
            <CardHeader>
                <h5 className="flex items-center gap-2 text-sm font-medium">
                    <RefreshCw className="h-4 w-4" />
                    {getTitle()}
                </h5>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <Label htmlFor="recurring_duration">{t('Recurring Frequency')}</Label>
                        <Select
                            value={data.recurring_duration || 'no'}
                            onValueChange={(value) => setData('recurring_duration', value)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder={t('Select frequency')} />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(recurringTypes).map(([key, label]) => (
                                    <SelectItem key={key} value={key}>
                                        {label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {data.recurring_duration !== 'no' && (
                        <>
                            {!data.unlimited_cycles && (
                                <div>
                                    <Label htmlFor="cycles">{t('Number of Cycles')}</Label>
                                    <Input
                                        id="cycles"
                                        type="number"
                                        value={data.cycles || 1}
                                        onChange={(e) => setData('cycles', parseInt(e.target.value))}
                                        min="1"
                                    />
                                </div>
                            )}
                            <div className="flex items-center space-x-2">
                                <div className="flex-1">
                                    <Label htmlFor="unlimited_cycles">{t('Unlimited Cycles')}</Label>
                                    <div className="flex items-center h-10">
                                        <Switch
                                            id="unlimited_cycles"
                                            checked={data.unlimited_cycles || false}
                                            onCheckedChange={(checked) => setData('unlimited_cycles', checked)}
                                        />
                                    </div>
                                </div>
                            </div>
                        </>
                    )}
                </div>

                {data.recurring_duration === 'custom' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <Label htmlFor="count">{t('Count')}</Label>
                            <Input
                                id="count"
                                type="number"
                                value={data.count || 1}
                                onChange={(e) => setData('count', parseInt(e.target.value))}
                                min="1"
                            />
                        </div>
                        <div>
                            <Label htmlFor="day_type">{t('Period')}</Label>
                            <Select
                                value={data.day_type || 'month'}
                                onValueChange={(value) => setData('day_type', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(dayTypes).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

export const purchaseInvoiceCreateFields = (data: any, setData: any, errors: any, mode: string = 'create') => {
    const { props } = usePage();
    const { auth } = props as any;
    const recurringEnabled = getCompanySetting('recurring_sales_purchase_invoices');

    if (isPackageActive('RecurringInvoiceBill') && recurringEnabled === 'on' && auth.user?.permissions?.includes('manage-recurring-invoice-bill')) {
        // Initialize immediately for create mode to prevent flashing
        if (!data.hasOwnProperty('recurring_duration')) {
            setData(prev => ({
                ...prev,
                recurring_duration: 'no',
                cycles: 1,
                count: 1,
                day_type: 'month',
                unlimited_cycles: false,
            }));
        }

        return [{
            id: 'recurring-purchase-invoice-fields',
            order: 10,
            component: (
                <RecurringFormComponent
                    key="recurring-purchase-invoice-fields"
                    data={data}
                    setData={setData}
                    invoiceType="purchase"
                />
            )
        }];
    }

    return [];
};

export const purchaseInvoiceEditFields = (data: any, setData: any, errors: any, mode: string, invoice: any) => {
    const { props } = usePage();
    const { auth } = props as any;
    const recurringEnabled = getCompanySetting('recurring_sales_purchase_invoices');

    if (isPackageActive('RecurringInvoiceBill') && recurringEnabled === 'on' && auth.user?.permissions?.includes('manage-recurring-invoice-bill')) {
        // Initialize immediately for edit mode to prevent flashing
        if (!data.hasOwnProperty('recurring_duration')) {
            setData(prev => ({
                ...prev,
                recurring_duration: invoice.recurring_data?.recurring_duration || 'no',
                cycles: invoice.recurring_data?.cycles || 1,
                count: invoice.recurring_data?.count || 1,
                day_type: invoice.recurring_data?.day_type || 'month',
                unlimited_cycles: invoice.recurring_data?.unlimited_cycles || false,
            }));
        }

        return [{
            id: 'recurring-purchase-invoice-fields',
            order: 10,
            component: (
                <RecurringFormComponent
                    key="recurring-purchase-invoice-fields"
                    data={data}
                    setData={setData}
                    invoiceType="purchase"
                />
            )
        }];
    }

    return [];
};

export const salesInvoiceCreateFields = (data: any, setData: any, errors: any, mode: string = 'create') => {
    const { props } = usePage();
    const { auth } = props as any;
    const recurringEnabled = getCompanySetting('recurring_sales_purchase_invoices');

    if (isPackageActive('RecurringInvoiceBill') && recurringEnabled === 'on' && auth.user?.permissions?.includes('manage-recurring-invoice-bill')) {
        // Initialize immediately for create mode to prevent flashing
        if (!data.hasOwnProperty('recurring_duration')) {
            setData(prev => ({
                ...prev,
                recurring_duration: 'no',
                cycles: 1,
                count: 1,
                day_type: 'month',
                unlimited_cycles: false,
            }));
        }

        return [{
            id: 'recurring-sales-invoice-fields',
            order: 10,
            component: (
                <RecurringFormComponent
                    key="recurring-sales-invoice-fields"
                    data={data}
                    setData={setData}
                    invoiceType="sales"
                />
            )
        }];
    }

    return [];
};

export const salesInvoiceEditFields = (data: any, setData: any, errors: any, mode: string, invoice: any) => {
    const { props } = usePage();
    const { auth } = props as any;
    const recurringEnabled = getCompanySetting('recurring_sales_purchase_invoices');

    if (isPackageActive('RecurringInvoiceBill') && recurringEnabled === 'on' && auth.user?.permissions?.includes('manage-recurring-invoice-bill')) {
        // Initialize immediately for edit mode to prevent flashing
        if (!data.hasOwnProperty('recurring_duration')) {
            setData(prev => ({
                ...prev,
                recurring_duration: invoice.recurring_data?.recurring_duration || 'no',
                cycles: invoice.recurring_data?.cycles || 1,
                count: invoice.recurring_data?.count || 1,
                day_type: invoice.recurring_data?.day_type || 'month',
                unlimited_cycles: invoice.recurring_data?.unlimited_cycles || false,
            }));
        }

        return [{
            id: 'recurring-sales-invoice-fields',
            order: 10,
            component: (
                <RecurringFormComponent
                    key="recurring-sales-invoice-fields"
                    data={data}
                    setData={setData}
                    invoiceType="sales"
                />
            )
        }];
    }

    return [];
};

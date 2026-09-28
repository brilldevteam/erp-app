import { Fragment, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Printer, FileText, Columns3, Check, ChevronsUpDown } from 'lucide-react';
import { formatDate, formatCurrency } from '@/utils/helpers';
import { cn } from '@/lib/utils';
import NoRecordsFound from '@/components/no-records-found';
import axios from 'axios';

interface AgingAmounts {
    current: number;
    '1_30_days': number;
    '31_60_days': number;
    '61_90_days': number;
    over_90_days: number;
    total: number;
}

interface AgingInvoice extends AgingAmounts { id: number; invoice_number: string; }
interface AgingCustomer extends AgingAmounts { customer_name: string; account_code?: string | null; invoices: AgingInvoice[]; }
interface AgingData {
    aging_summary: AgingAmounts;
    customers: AgingCustomer[];
    as_of_date: string;
    filter_options: { customers: Array<{ id: number; name: string }> };
}

type ColumnKey = 'account_code' | 'customer' | 'invoice_number' | 'current' | '1_30_days' | '31_60_days' | '61_90_days' | 'over_90_days' | 'total';
const defaultColumns: ColumnKey[] = ['account_code', 'customer', 'invoice_number', 'current', '1_30_days', '31_60_days', '61_90_days', 'over_90_days', 'total'];
const amountColumns: ColumnKey[] = ['current', '1_30_days', '31_60_days', '61_90_days', 'over_90_days', 'total'];

export default function InvoiceAging({ financialYear }: any) {
    const { t } = useTranslation();
    const { auth } = usePage<any>().props;
    const [asOfDate, setAsOfDate] = useState(financialYear?.year_end_date || '');
    const [customerId, setCustomerId] = useState('all');
    const [customerPickerOpen, setCustomerPickerOpen] = useState(false);
    const [customerSearch, setCustomerSearch] = useState('');
    const [invoiceDateRange, setInvoiceDateRange] = useState('');
    const [agingBucket, setAgingBucket] = useState('all');
    const [visibleColumns, setVisibleColumns] = useState<ColumnKey[]>(defaultColumns);
    const [data, setData] = useState<AgingData | null>(null);
    const [loading, setLoading] = useState(false);

    const columns: Array<{ key: ColumnKey; label: string; align: 'left' | 'right' }> = [
        { key: 'account_code', label: t('Account Code'), align: 'left' },
        { key: 'customer', label: t('Customer'), align: 'left' },
        { key: 'invoice_number', label: t('Invoice Number'), align: 'left' },
        { key: 'current', label: t('Current'), align: 'right' },
        { key: '1_30_days', label: `1-30 ${t('Days')}`, align: 'right' },
        { key: '31_60_days', label: `31-60 ${t('Days')}`, align: 'right' },
        { key: '61_90_days', label: `61-90 ${t('Days')}`, align: 'right' },
        { key: 'over_90_days', label: `>90 ${t('Days')}`, align: 'right' },
        { key: 'total', label: t('Total'), align: 'right' },
    ];
    const activeColumns = columns.filter(column => visibleColumns.includes(column.key));
    const customerOptions = data?.filter_options.customers || [];
    const selectedCustomer = customerOptions.find(customer => customer.id.toString() === customerId);
    const normalizedCustomerSearch = customerSearch.trim().toLowerCase();
    const filteredCustomers = normalizedCustomerSearch
        ? customerOptions.filter(customer => customer.name.toLowerCase().includes(normalizedCustomerSearch))
        : customerOptions;

    const buildParams = () => {
        const [invoiceDateFrom, invoiceDateTo] = invoiceDateRange ? invoiceDateRange.split(' - ') : [];
        return {
            as_of_date: asOfDate,
            ...(customerId !== 'all' ? { customer_id: customerId } : {}),
            ...(invoiceDateFrom && invoiceDateTo ? { invoice_date_from: invoiceDateFrom, invoice_date_to: invoiceDateTo } : {}),
            ...(agingBucket !== 'all' ? { aging_bucket: agingBucket } : {}),
        };
    };

    const loadData = async (params: ReturnType<typeof buildParams>) => {
        setLoading(true);
        try {
            const response = await axios.get(route('account.reports.invoice-aging'), { params });
            setData(response.data);
        } catch (error) {
            console.error('Error:', error);
        } finally {
            setLoading(false);
        }
    };

    const fetchData = () => loadData(buildParams());

    useEffect(() => { fetchData(); }, []);

    const clearFilters = () => {
        setCustomerId('all');
        setInvoiceDateRange('');
        setAgingBucket('all');
        loadData({ as_of_date: asOfDate });
    };

    const toggleColumn = (key: ColumnKey, checked: boolean) => {
        if (key === 'customer') return;
        setVisibleColumns(current => checked
            ? defaultColumns.filter(column => current.includes(column) || column === key)
            : current.filter(column => column !== key));
    };

    const handleDownloadPDF = () => {
        const params = new URLSearchParams({ ...buildParams(), columns: visibleColumns.join(','), download: 'pdf' });
        window.open(`${route('account.reports.invoice-aging.print')}?${params.toString()}`, '_blank');
    };

    const invoiceValue = (key: ColumnKey, customer: AgingCustomer, invoice: AgingInvoice) => {
        if (key === 'account_code') return customer.account_code || '-';
        if (key === 'customer') return customer.customer_name;
        if (key === 'invoice_number') return invoice.invoice_number;
        return formatCurrency(invoice[key]);
    };

    return (
        <Card className="shadow-sm">
            <CardContent className="p-6 border-b bg-gray-50/50">
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">{t('As Of Date')}</label>
                        <DatePicker value={asOfDate} onChange={setAsOfDate} placeholder={t('Select date')} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">{t('Customer')}</label>
                        <Popover open={customerPickerOpen} onOpenChange={(open) => {
                            setCustomerPickerOpen(open);
                            if (open) fetchData();
                            else setCustomerSearch('');
                        }}>
                            <PopoverTrigger asChild>
                                <Button type="button" variant="outline" role="combobox" aria-expanded={customerPickerOpen} className="w-full justify-between font-normal">
                                    <span className="truncate">{selectedCustomer?.name || t('All Customers')}</span>
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent align="start" className="w-[28rem] max-w-[calc(100vw-2rem)] p-0">
                                <Command>
                                    <CommandInput value={customerSearch} onChange={event => setCustomerSearch(event.target.value)} placeholder={t('Search customers...')} />
                                    <CommandList className="max-h-[520px]">
                                        <CommandGroup>
                                            <CommandItem onSelect={() => { setCustomerId('all'); setCustomerPickerOpen(false); }} className="gap-2">
                                                <Check className={cn('h-4 w-4', customerId === 'all' ? 'opacity-100' : 'opacity-0')} />
                                                <span>{t('All Customers')}</span>
                                            </CommandItem>
                                            {filteredCustomers.map(customer => (
                                                <CommandItem key={customer.id} onSelect={() => { setCustomerId(customer.id.toString()); setCustomerPickerOpen(false); }} className="items-start gap-2 py-2">
                                                    <Check className={cn('mt-0.5 h-4 w-4 shrink-0', customerId === customer.id.toString() ? 'opacity-100' : 'opacity-0')} />
                                                    <span className="whitespace-normal break-words leading-5">{customer.name}</span>
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                        {filteredCustomers.length === 0 && <CommandEmpty>{t('No customers found')}</CommandEmpty>}
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">{t('Invoice Date Range')}</label>
                        <DateRangePicker value={invoiceDateRange} onChange={setInvoiceDateRange} placeholder={t('Select date range')} />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">{t('Aging Bucket')}</label>
                        <Select value={agingBucket} onValueChange={setAgingBucket}>
                            <SelectTrigger><SelectValue placeholder={t('All Buckets')} /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All Buckets')}</SelectItem>
                                <SelectItem value="current">{t('Current')}</SelectItem>
                                <SelectItem value="1_30_days">1-30 {t('Days')}</SelectItem>
                                <SelectItem value="31_60_days">31-60 {t('Days')}</SelectItem>
                                <SelectItem value="61_90_days">61-90 {t('Days')}</SelectItem>
                                <SelectItem value="over_90_days">&gt;90 {t('Days')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex items-end gap-2">
                        <Button onClick={fetchData} disabled={loading} size="sm">{loading ? t('Loading...') : t('Generate')}</Button>
                        <Button variant="outline" onClick={clearFilters} size="sm">{t('Clear')}</Button>
                    </div>
                </div>
            </CardContent>

            <CardContent className="p-0">
                {data && data.customers.length > 0 ? (
                    <>
                        <div className="flex flex-col gap-3 border-b bg-gray-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:pr-[90px]">
                            <div>
                                <h3 className="font-semibold text-lg">{t('Invoice Aging Report')}</h3>
                                <p className="text-sm text-gray-600">{t('As of')} {formatDate(data.as_of_date)}</p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild><Button variant="outline" size="sm" className="gap-2"><Columns3 className="h-4 w-4" />{t('Columns')}</Button></DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-56">
                                        <DropdownMenuLabel>{t('Visible Columns')}</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        {columns.map(column => (
                                            <DropdownMenuCheckboxItem key={column.key} checked={visibleColumns.includes(column.key)} disabled={column.key === 'customer'} onCheckedChange={checked => toggleColumn(column.key, checked === true)}>
                                                {column.label}
                                            </DropdownMenuCheckboxItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                {auth.user?.permissions?.includes('print-invoice-aging') && (
                                    <Button variant="outline" size="sm" onClick={handleDownloadPDF} className="gap-2"><Printer className="h-4 w-4" />{t('Download PDF')}</Button>
                                )}
                            </div>
                        </div>
                        <div className="max-h-[60vh] overflow-auto">
                            <table className="w-full" style={{ minWidth: Math.max(700, activeColumns.length * 135) }}>
                                <thead className="bg-gray-100 sticky top-0"><tr>{activeColumns.map(column => <th key={column.key} className={`px-4 py-3 text-sm font-semibold ${column.align === 'right' ? 'text-right' : 'text-left'}`}>{column.label}</th>)}</tr></thead>
                                <tbody>
                                    {data.customers.map(customer => (
                                        <Fragment key={`${customer.account_code}-${customer.customer_name}`}>
                                            {customer.invoices.map(invoice => (
                                                <tr key={invoice.id} className="border-t hover:bg-gray-50">
                                                    {activeColumns.map(column => <td key={column.key} className={`whitespace-nowrap px-4 py-3 ${column.align === 'right' ? 'text-right' : 'text-left'} ${column.key === 'invoice_number' || column.key === 'total' ? 'font-semibold' : ''}`}>{invoiceValue(column.key, customer, invoice)}</td>)}
                                                </tr>
                                            ))}
                                            <tr className="border-t bg-gray-50 font-semibold">
                                                {activeColumns.map(column => <td key={column.key} className={`px-4 py-3 ${column.align === 'right' ? 'text-right' : 'text-left'}`}>{column.key === 'customer' ? `${customer.customer_name} ${t('Subtotal')}` : amountColumns.includes(column.key) ? formatCurrency(customer[column.key as keyof AgingAmounts]) : ''}</td>)}
                                            </tr>
                                            <tr aria-hidden="true"><td colSpan={activeColumns.length} className="h-2 border-x-0 border-y border-gray-300 bg-gray-200 p-0" /></tr>
                                        </Fragment>
                                    ))}
                                    <tr className="bg-gray-200 font-bold border-t-4">
                                        {activeColumns.map(column => <td key={column.key} className={`px-4 py-4 ${column.align === 'right' ? 'text-right' : 'text-left'}`}>{column.key === 'customer' ? t('Grand Total') : amountColumns.includes(column.key) ? formatCurrency(data.aging_summary[column.key as keyof AgingAmounts]) : ''}</td>)}
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </>
                ) : <NoRecordsFound icon={FileText} title={t('Invoice Aging Report')} description={t('No outstanding invoices found')} className="h-auto py-12" />}
            </CardContent>
        </Card>
    );
}

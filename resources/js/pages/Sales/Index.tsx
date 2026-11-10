import { useEffect, useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, ChevronDown, Clock3, Copy, Download, Edit as EditIcon, Eye, FileSearch, FileText, Filter, LoaderCircle, MoreHorizontal, Package, Plus, Receipt, Search, Trash2, WalletCards, Wrench, X } from 'lucide-react';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { usePageButtons } from '@/hooks/usePageButtons';
import { useFormFields } from '@/hooks/useFormFields';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Pagination } from '@/components/ui/pagination';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import NoRecordsFound from '@/components/no-records-found';
import DualCurrencyAmount from '@/components/dual-currency-amount';
import { formatDate } from '@/utils/helpers';
import { getStatusBadgeClasses } from './utils';
import { InvoiceCustomerOption, SalesInvoice } from './types';

type ListedInvoice = SalesInvoice & { is_overdue: boolean };

interface SalesIndexProps {
    invoices: { data: ListedInvoice[]; current_page: number; last_page: number; per_page: number; total: number; from: number; to: number };
    customers: InvoiceCustomerOption[];
    warehouses: Array<{ id: number; name: string; address: string }>;
    summary: { total: number; awaiting: number; overdue: number };
    filters: Record<string, string>;
    auth: any;
    [key: string]: any;
}

const views = [['all', 'All invoices'], ['product', 'Product'], ['service', 'Service'], ['overdue', 'Overdue']];
const sorts = [['created_at', 'Created date'], ['invoice_date', 'Invoice date'], ['due_date', 'Due date'], ['total_amount', 'Total'], ['balance_amount', 'Balance'], ['invoice_number', 'Number'], ['status', 'Status']];
const statuses = [['draft', 'Draft'], ['posted', 'Posted'], ['partial', 'Partial'], ['paid', 'Paid'], ['overdue', 'Overdue'], ['cancelled', 'Cancelled']];
const rowColumns = 'min-[1360px]:grid-cols-[minmax(190px,1fr)_minmax(140px,1.2fr)_115px_90px_115px_115px_140px]';

// Buttons added by other modules (e-invoice, signature, Xero) stay on the row as they were.
const SignatureButtons = ({ invoice }: { invoice: ListedInvoice }) => {
    const buttons = usePageButtons('signatureBtn', { invoice });
    return <>{buttons.map((button) => <div key={button.id}>{button.component}</div>)}</>;
};

const InvoiceActionButtons = ({ invoice, auth }: { invoice: ListedInvoice; auth: any }) => {
    const buttons = usePageButtons('invoiceActionButtons', { invoice_id: invoice.id, auth });
    return <>{buttons.map((button) => <div key={button.id}>{button.component}</div>)}</>;
};

const SalesInvoiceActionButtons = ({ invoice, auth }: { invoice: ListedInvoice; auth: any }) => {
    const buttons = usePageButtons('xeroSyncInvoiceBtn', { invoice_id: invoice.id, auth, status: invoice.status });
    return <>{buttons.map((button) => <div key={button.id}>{button.component}</div>)}</>;
};

export default function Index() {
    const { t } = useTranslation();
    const { invoices, customers, warehouses, summary, filters = {}, auth } = usePage<SalesIndexProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [isSearching, setIsSearching] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.customer_id || filters.warehouse_id || filters.status || filters.date_range));
    const [duplicateInvoiceId, setDuplicateInvoiceId] = useState<number | null>(null);

    useFlashMessages();

    const salesAlerts = useFormFields('salesInvoiceAlert', {}, () => {}, {});

    const pageButtons = usePageButtons('salesBtn', 'Sales data');
    const spreadsheetButtons = usePageButtons('spreadsheetBtn', { module: 'Sales Invoice' });
    const googleDriveButtons = usePageButtons('googleDriveBtn', { module: 'Sales Invoice', settingKey: 'GoogleDrive Sales Invoice' });
    const oneDriveButtons = usePageButtons('oneDriveBtn', { module: 'Sales Invoice', settingKey: 'OneDrive Sales Invoice' });
    const dropboxBtn = usePageButtons('dropboxBtn', { module: 'Sales Invoice', settingKey: 'Dropbox Sales Invoice' });
    const boxBtn = usePageButtons('boxBtn', { module: 'Sales Invoice', settingKey: 'Box Sales Invoice' });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'sales-invoices.destroy',
        defaultMessage: t('Are you sure you want to delete this sales invoice?')
    });

    const can = (permission: string) => Boolean(auth.user?.permissions?.includes(permission));
    const activeView = filters.view || 'all';
    const activeFilterCount = [filters.customer_id, filters.warehouse_id, filters.status, filters.date_range].filter(Boolean).length;
    const hasFilters = Boolean(filters.search || activeFilterCount || activeView !== 'all');

    const navigate = (extra: Record<string, string | number>) => router.get(route('sales-invoices.index'), { ...filters, ...extra }, { preserveState: true, replace: true });
    const clearAdvanced = () => navigate({ customer_id: '', warehouse_id: '', status: '', date_range: '', page: 1 });
    const clearAll = () => {
        setSearch('');
        router.get(route('sales-invoices.index'), { per_page: filters.per_page || '' }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        const normalizedSearch = search.trim();
        if (normalizedSearch === (filters.search || '')) return;

        setIsSearching(true);
        const timeout = window.setTimeout(() => {
            router.get(route('sales-invoices.index'), { ...filters, search: normalizedSearch, page: 1 }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['invoices', 'filters'],
                onFinish: () => setIsSearching(false),
            });
        }, normalizedSearch ? 300 : 0);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search]);

    const openInvoice = (invoice: ListedInvoice) => {
        if (can('view-sales-invoices')) router.get(route('sales-invoices.show', invoice.id));
    };

    const confirmDuplicate = () => {
        if (duplicateInvoiceId) {
            router.post(route('sales-invoices.duplicate', duplicateInvoiceId), {}, { preserveScroll: true });
        }
        setDuplicateInvoiceId(null);
    };

    const customerName = (invoice: ListedInvoice) => invoice.customer_details?.company_name || invoice.customer_details?.contact_person_name || invoice.customer?.name || '-';
    const customerContact = (invoice: ListedInvoice) => {
        const contact = invoice.customer_details?.contact_person_name || invoice.customer?.name;
        return contact && contact !== customerName(invoice) ? contact : null;
    };

    const summaryCards = [
        { label: t('Total invoices'), value: summary.total, icon: FileText, tone: 'bg-blue-50 text-blue-600', valueTone: '' },
        { label: t('Awaiting payment'), value: summary.awaiting, icon: WalletCards, tone: 'bg-amber-50 text-amber-700', valueTone: '' },
        { label: t('Overdue'), value: summary.overdue, icon: Clock3, tone: 'bg-rose-50 text-rose-600', valueTone: summary.overdue > 0 ? 'text-rose-600' : '' },
    ];

    const headerButtons = [...pageButtons, ...spreadsheetButtons, ...googleDriveButtons, ...oneDriveButtons, ...dropboxBtn, ...boxBtn];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Sales Invoices') }]}
            pageTitle={t('Sales Invoices')}
            pageActions={
                <TooltipProvider>
                    <div className="flex gap-2">
                        {headerButtons.map((button) => (
                            <div key={button.id}>{button.component}</div>
                        ))}
                        {can('create-sales-invoices') && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        {t('New invoice')}
                                        <ChevronDown className="ml-2 h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => router.visit(route('sales-invoices.create', { type: 'product' }))}>
                                        <Package className="mr-2 h-4 w-4" />
                                        {t('Product Invoice')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => router.visit(route('sales-invoices.create', { type: 'service' }))}>
                                        <Wrench className="mr-2 h-4 w-4" />
                                        {t('Service Invoice')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                </TooltipProvider>
            }
        >
            <Head title={t('Sales Invoices')} />

            <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label={t('Invoice summary')}>
                    {summaryCards.map((card, index) => (
                        <div key={card.label} className={`rounded-2xl border bg-background p-4 shadow-sm shadow-slate-200/30 ${index === 0 ? 'col-span-2 sm:col-span-1' : ''}`}>
                            <div className="flex items-center gap-3">
                                <span className={`rounded-xl p-2.5 ${card.tone}`}><card.icon className="h-5 w-5" /></span>
                                <div>
                                    <p className="text-xs font-medium text-muted-foreground">{card.label}</p>
                                    <p className={`mt-0.5 text-xl font-semibold tabular-nums ${card.valueTone}`}>{card.value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </section>

                <Card className="relative z-20 overflow-visible rounded-2xl border shadow-sm shadow-slate-200/40">
                    <CardContent className="p-0">
                        <div className="flex flex-col gap-3 p-4 sm:p-5 xl:flex-row xl:items-center">
                            <form onSubmit={e => { e.preventDefault(); navigate({ search: search.trim(), page: 1 }); }} className="relative min-w-0 flex-1" role="search">
                                <Search className="absolute left-3.5 top-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    autoComplete="off"
                                    placeholder={t('Search by invoice number or customer')}
                                    className="h-10 border-slate-200 bg-slate-50/70 pl-10 pr-10 focus-visible:bg-background"
                                />
                                {isSearching ? (
                                    <LoaderCircle className="absolute right-3.5 top-3 h-4 w-4 animate-spin text-primary" />
                                ) : search && (
                                    <button type="button" onClick={() => setSearch('')} className="absolute right-3 top-2.5 rounded-md p-1 text-muted-foreground hover:bg-slate-200 hover:text-foreground" aria-label={t('Clear search')}>
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                            </form>
                            <div className="flex flex-wrap items-center gap-2">
                                <label className="relative min-w-[160px] flex-1 sm:flex-none">
                                    <span className="sr-only">{t('Sort invoices')}</span>
                                    <select value={filters.sort || 'created_at'} onChange={e => navigate({ sort: e.target.value, page: 1 })} className="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-background pl-3 pr-9 text-sm sm:w-48">
                                        {sorts.map(([value, label]) => <option key={value} value={value}>{`${t('Sort')}: ${t(label)}`}</option>)}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-muted-foreground" />
                                </label>
                                <Button variant="outline" size="icon" className="h-10 w-10 rounded-lg" title={filters.direction === 'asc' ? t('Ascending') : t('Descending')} onClick={() => navigate({ direction: filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}>
                                    {filters.direction === 'asc' ? <ArrowUpAZ className="h-4 w-4" /> : <ArrowDownAZ className="h-4 w-4" />}
                                </Button>
                                <PerPageSelector routeName="sales-invoices.index" filters={filters} className="h-10 w-36" />
                                <Button variant={showAdvanced || activeFilterCount ? 'secondary' : 'outline'} className="h-10 rounded-lg" onClick={() => setShowAdvanced(value => !value)}>
                                    <Filter className="mr-2 h-4 w-4" />
                                    {t('Filters')}
                                    {activeFilterCount > 0 && <span className="ml-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{activeFilterCount}</span>}
                                </Button>
                            </div>
                        </div>

                        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('Invoice views')}>
                            {views.map(([value, label]) => (
                                <button
                                    key={value}
                                    role="tab"
                                    aria-selected={activeView === value}
                                    onClick={() => navigate({ view: value, page: 1 })}
                                    className={`whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${activeView === value ? 'bg-background text-foreground shadow-sm ring-1 ring-slate-200' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}
                                >
                                    {t(label)}
                                </button>
                            ))}
                        </div>

                        {showAdvanced && (
                            <div className="grid gap-3 border-t bg-slate-50/30 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                                {can('manage-users') && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">{t('Customer')}</span>
                                        <Select value={filters.customer_id || 'all'} onValueChange={value => navigate({ customer_id: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by customer')} className="bg-background"><SelectValue placeholder={t('Any customer')} /></SelectTrigger>
                                            <SelectContent searchable>
                                                <SelectItem value="all">{t('Any customer')}</SelectItem>
                                                {customers.map(customer => (
                                                    <SelectItem key={customer.id} value={customer.id.toString()}>
                                                        {customer.company_name ? `${customer.company_name} — ${customer.contact_person_name || customer.name}` : customer.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                {can('manage-warehouses') && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">{t('Warehouse')}</span>
                                        <Select value={filters.warehouse_id || 'all'} onValueChange={value => navigate({ warehouse_id: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by warehouse')} className="bg-background"><SelectValue placeholder={t('Any warehouse')} /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">{t('Any warehouse')}</SelectItem>
                                                {warehouses.map(warehouse => <SelectItem key={warehouse.id} value={warehouse.id.toString()}>{warehouse.name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Status')}</span>
                                    <Select value={filters.status || 'all'} onValueChange={value => navigate({ status: value === 'all' ? '' : value, page: 1 })}>
                                        <SelectTrigger aria-label={t('Filter by status')} className="bg-background"><SelectValue placeholder={t('Any status')} /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('Any status')}</SelectItem>
                                            {statuses.map(([value, label]) => <SelectItem key={value} value={value}>{t(label)}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Invoice date')}</span>
                                    <DateRangePicker value={filters.date_range || ''} onChange={value => navigate({ date_range: value, page: 1 })} placeholder={t('Select date range')} />
                                </div>
                                <Button variant="ghost" className="self-end text-muted-foreground" onClick={clearAdvanced} disabled={!activeFilterCount}>{t('Clear filters')}</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="relative z-0 overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <div className={`hidden gap-3 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 min-[1360px]:grid ${rowColumns}`}>
                        <span>{t('Invoice')}</span>
                        <span>{t('Customer')}</span>
                        <span>{t('Dates')}</span>
                        <span>{t('Status')}</span>
                        <span className="text-right">{t('Total')}</span>
                        <span className="text-right">{t('Balance')}</span>
                        <span />
                    </div>

                    <div className="divide-y">
                        {invoices.data.map(invoice => (
                            <div
                                key={invoice.id}
                                role="button"
                                tabIndex={0}
                                onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openInvoice(invoice); } }}
                                onClick={() => openInvoice(invoice)}
                                className={`group grid cursor-pointer gap-3 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary min-[1360px]:items-center min-[1360px]:px-6 min-[1360px]:py-4 ${rowColumns}`}
                            >
                                <div className="flex min-w-0 items-center gap-3.5">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-700">
                                        {invoice.type === 'service' ? <Wrench className="h-5 w-5" /> : <Package className="h-5 w-5" />}
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-slate-900 group-hover:text-primary">{invoice.invoice_number}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">{invoice.type === 'service' ? t('Service') : t('Product')}</p>
                                    </div>
                                </div>

                                <div className="min-w-0 text-sm">
                                    <p className="truncate font-medium text-slate-700">{customerName(invoice)}</p>
                                    {customerContact(invoice) && <p className="mt-0.5 truncate text-xs text-slate-500">{customerContact(invoice)}</p>}
                                </div>

                                <div className="flex justify-between text-sm min-[1360px]:block">
                                    <span className="text-slate-500 min-[1360px]:hidden">{t('Dates')}</span>
                                    <div className="text-right min-[1360px]:text-left">
                                        <p className="text-slate-700">{formatDate(invoice.invoice_date)}</p>
                                        <p className={`mt-0.5 text-xs ${invoice.is_overdue ? 'font-medium text-rose-600' : 'text-slate-500'}`}>{t('Due')} {formatDate(invoice.due_date)}</p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className={getStatusBadgeClasses(invoice.status).replace('text-sm', 'text-xs')}>{t(invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1))}</span>
                                    {invoice.is_overdue && invoice.status !== 'overdue' && <span className="rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-600">{t('Overdue')}</span>}
                                </div>

                                <div className="flex justify-between text-sm min-[1360px]:block min-[1360px]:text-right">
                                    <span className="text-slate-500 min-[1360px]:hidden">{t('Total')}</span>
                                    <DualCurrencyAmount baseFirst className="font-semibold tabular-nums text-slate-900" amount={invoice.total_amount} currencyCode={invoice.currency_code} baseAmount={invoice.base_amount ?? Number(invoice.total_amount) * Number(invoice.exchange_rate || 1)} baseCurrencyCode={invoice.base_currency_code} />
                                </div>

                                <div className="flex justify-between text-sm min-[1360px]:block min-[1360px]:text-right">
                                    <span className="text-slate-500 min-[1360px]:hidden">{t('Balance')}</span>
                                    <DualCurrencyAmount baseFirst className={`font-semibold tabular-nums ${invoice.is_overdue ? 'text-rose-600' : 'text-slate-700'}`} amount={invoice.balance_amount} currencyCode={invoice.currency_code} baseAmount={Number(invoice.balance_amount) * Number(invoice.exchange_rate || 1)} baseCurrencyCode={invoice.base_currency_code} />
                                </div>

                                <div className="flex flex-wrap justify-end gap-0.5" onClick={e => e.stopPropagation()} onKeyDown={e => e.stopPropagation()}>
                                    <TooltipProvider>
                                        <SignatureButtons invoice={invoice} />
                                        {can('download-sales-invoices') && (
                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Preview')} aria-label={t('Preview')} onClick={() => window.open(route('sales-invoices.print', invoice.id), '_blank')}>
                                                <FileSearch className="h-4 w-4" />
                                            </Button>
                                        )}
                                        {can('download-sales-invoices') && (
                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Download PDF')} aria-label={t('Download PDF')} onClick={() => window.open(route('sales-invoices.print', invoice.id) + '?download=pdf', '_blank')}>
                                                <Download className="h-4 w-4" />
                                            </Button>
                                        )}
                                        <InvoiceActionButtons invoice={invoice} auth={auth} />
                                        <SalesInvoiceActionButtons invoice={invoice} auth={auth} />
                                    </TooltipProvider>
                                    <DropdownMenu modal={false}>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('More actions')} aria-label={t('More actions')}>
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="min-w-[190px]">
                                            {can('view-sales-invoices') && <DropdownMenuItem onClick={() => router.get(route('sales-invoices.show', invoice.id))}><Eye className="mr-2 h-4 w-4" />{t('View')}</DropdownMenuItem>}
                                            {invoice.status === 'draft' && can('edit-sales-invoices') && <DropdownMenuItem onClick={() => router.visit(route('sales-invoices.edit', invoice.id))}><EditIcon className="mr-2 h-4 w-4" />{t('Edit')}</DropdownMenuItem>}
                                            {invoice.status === 'draft' && can('post-sales-invoices') && <DropdownMenuItem onClick={() => router.post(route('sales-invoices.post', invoice.id))}><FileText className="mr-2 h-4 w-4" />{t('Post Invoice')}</DropdownMenuItem>}
                                            {can('create-sales-invoices') && <DropdownMenuItem onClick={() => setDuplicateInvoiceId(invoice.id)}><Copy className="mr-2 h-4 w-4" />{t('Duplicate')}</DropdownMenuItem>}
                                            {invoice.status === 'draft' && can('delete-sales-invoices') && (
                                                <>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem className="text-rose-600 focus:text-rose-600" onClick={() => openDeleteDialog(invoice.id)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem>
                                                </>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        ))}

                        {!invoices.data.length && (
                            <NoRecordsFound
                                icon={Receipt}
                                title={t('No sales invoices found')}
                                description={t('Get started by creating your first sales invoice.')}
                                hasFilters={hasFilters}
                                onClearFilters={clearAll}
                                createPermission="create-sales-invoices"
                                onCreateClick={() => router.visit(route('sales-invoices.create'))}
                                createButtonText={t('Create Sales Invoice')}
                                className="h-auto"
                            />
                        )}
                    </div>

                    {invoices.total > 0 && (
                        <div className="border-t bg-slate-50/30 px-3">
                            <Pagination data={invoices} onPageChange={page => navigate({ page })} />
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmationDialog
                open={duplicateInvoiceId !== null}
                onOpenChange={() => setDuplicateInvoiceId(null)}
                title={t('Duplicate Sales Invoice')}
                message={t('This creates a new draft invoice dated today with the same customer and items. Continue?')}
                confirmText={t('Duplicate')}
                onConfirm={confirmDuplicate}
            />
            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Sales Invoice')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
            {salesAlerts.map((alert) => (
                <div key={alert.id}>{alert.component}</div>
            ))}
        </AuthenticatedLayout>
    );
}

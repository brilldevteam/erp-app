import { useEffect, useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, ChevronDown, Clock3, Download, Edit as EditIcon, Eye, FileText, Filter, LoaderCircle, MoreHorizontal, Plus, Receipt, Search, Trash2, WalletCards, X } from 'lucide-react';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { usePageButtons } from '@/hooks/usePageButtons';
import { useFormFields } from '@/hooks/useFormFields';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Card, CardContent } from '@/components/ui/card';
import { TooltipProvider } from '@/components/ui/tooltip';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Pagination } from '@/components/ui/pagination';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import NoRecordsFound from '@/components/no-records-found';
import DualCurrencyAmount from '@/components/dual-currency-amount';
import { formatDate } from '@/utils/helpers';
import { getStatusBadgeClasses } from './utils';
import { InvoiceVendorOption, PurchaseInvoice } from './types';

type ListedInvoice = PurchaseInvoice & { is_overdue: boolean };

interface PurchaseIndexProps {
    invoices: { data: ListedInvoice[]; current_page: number; last_page: number; per_page: number; total: number; from: number; to: number };
    vendors: InvoiceVendorOption[];
    warehouses: Array<{ id: number; name: string; address: string }>;
    summary: { total: number; awaiting: number; overdue: number };
    filters: Record<string, string>;
    auth: any;
    [key: string]: any;
}

const views = [['all', 'All invoices'], ['unpaid', 'Unpaid'], ['paid', 'Paid'], ['overdue', 'Overdue']];
const sorts = [['created_at', 'Created date'], ['invoice_date', 'Invoice date'], ['due_date', 'Due date'], ['total_amount', 'Total'], ['balance_amount', 'Balance'], ['invoice_number', 'Number'], ['status', 'Status']];
const statuses = [['draft', 'Draft'], ['posted', 'Posted'], ['partial', 'Partial'], ['paid', 'Paid'], ['overdue', 'Overdue']];
const rowColumns = 'min-[1360px]:grid-cols-[minmax(190px,1fr)_minmax(140px,1.2fr)_115px_90px_115px_115px_140px]';

// Buttons added by other modules (signature, Xero) stay on the row as they were.
const SignatureButtons = ({ invoice }: { invoice: ListedInvoice }) => {
    const buttons = usePageButtons('signatureBtn', { invoice });
    return <>{buttons.map((button) => <div key={button.id}>{button.component}</div>)}</>;
};

const PurchaseInvoiceActionButtons = ({ invoice, auth }: { invoice: ListedInvoice; auth: any }) => {
    const buttons = usePageButtons('xeroSyncPurchaseInvoiceBtn', { purchase_invoice_id: invoice.id, auth, status: invoice.status });
    return <>{buttons.map((button) => <div key={button.id}>{button.component}</div>)}</>;
};

export default function Index() {
    const { t } = useTranslation();
    const { invoices, vendors, warehouses, summary, filters = {}, auth } = usePage<PurchaseIndexProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [isSearching, setIsSearching] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.vendor_id || filters.warehouse_id || filters.status || filters.date_range));

    useFlashMessages();

    const purchaseAlerts = useFormFields('purchaseInvoiceAlert', {}, () => { }, {});

    const pageButtons = usePageButtons('purchaseBtn', 'Purchase data');
    const spreadsheetButtons = usePageButtons('spreadsheetBtn', { module: 'Purchase Invoice' });
    const googleDriveButtons = usePageButtons('googleDriveBtn', { module: 'Purchase Invoice', settingKey: 'GoogleDrive Purchase Invoice' });
    const oneDriveButtons = usePageButtons('oneDriveBtn', { module: 'Purchase Invoice', settingKey: 'OneDrive Purchase Invoice' });
    const dropboxBtn = usePageButtons('dropboxBtn', { module: 'Purchase Invoice', settingKey: 'Dropbox Purchase Invoice' });
    const boxBtn = usePageButtons('boxBtn', { module: 'Purchase Invoice', settingKey: 'Box Purchase Invoice' });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'purchase-invoices.destroy',
        defaultMessage: t('Are you sure you want to delete this purchase invoice?')
    });

    const can = (permission: string) => Boolean(auth.user?.permissions?.includes(permission));
    const activeView = filters.view || 'all';
    const activeFilterCount = [filters.vendor_id, filters.warehouse_id, filters.status, filters.date_range].filter(Boolean).length;
    const hasFilters = Boolean(filters.search || activeFilterCount || activeView !== 'all');

    const navigate = (extra: Record<string, string | number>) => router.get(route('purchase-invoices.index'), { ...filters, ...extra }, { preserveState: true, replace: true });
    const clearAdvanced = () => navigate({ vendor_id: '', warehouse_id: '', status: '', date_range: '', page: 1 });
    const clearAll = () => {
        setSearch('');
        router.get(route('purchase-invoices.index'), { per_page: filters.per_page || '' }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        const normalizedSearch = search.trim();
        if (normalizedSearch === (filters.search || '')) return;

        setIsSearching(true);
        const timeout = window.setTimeout(() => {
            router.get(route('purchase-invoices.index'), { ...filters, search: normalizedSearch, page: 1 }, {
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
        if (can('view-purchase-invoices')) router.get(route('purchase-invoices.show', invoice.id));
    };

    const vendorName = (invoice: ListedInvoice) => invoice.vendor_details?.company_name || invoice.vendor_details?.contact_person_name || invoice.vendor?.name || '-';
    const vendorContact = (invoice: ListedInvoice) => {
        const contact = invoice.vendor_details?.contact_person_name || invoice.vendor?.name;
        return contact && contact !== vendorName(invoice) ? contact : null;
    };

    const summaryCards = [
        { label: t('Total invoices'), value: summary.total, icon: FileText, tone: 'bg-blue-50 text-blue-600', valueTone: '' },
        { label: t('Awaiting payment'), value: summary.awaiting, icon: WalletCards, tone: 'bg-amber-50 text-amber-700', valueTone: '' },
        { label: t('Overdue'), value: summary.overdue, icon: Clock3, tone: 'bg-rose-50 text-rose-600', valueTone: summary.overdue > 0 ? 'text-rose-600' : '' },
    ];

    const headerButtons = [...googleDriveButtons, ...oneDriveButtons, ...pageButtons, ...spreadsheetButtons, ...dropboxBtn, ...boxBtn];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Purchase Invoices') }]}
            pageTitle={t('Purchase Invoices')}
            pageActions={
                <TooltipProvider>
                    <div className="flex gap-2">
                        {headerButtons.map((button) => (
                            <div key={button.id}>{button.component}</div>
                        ))}
                        {can('create-purchase-invoices') && (
                            <Button size="sm" onClick={() => router.visit(route('purchase-invoices.create'))}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('New invoice')}
                            </Button>
                        )}
                    </div>
                </TooltipProvider>
            }
        >
            <Head title={t('Purchase Invoices')} />

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
                                    placeholder={t('Search by invoice number or vendor')}
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
                                <PerPageSelector routeName="purchase-invoices.index" filters={filters} className="h-10 w-36" />
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
                                        <span className="text-xs font-medium text-muted-foreground">{t('Vendor')}</span>
                                        <Select value={filters.vendor_id || 'all'} onValueChange={value => navigate({ vendor_id: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by vendor')} className="bg-background"><SelectValue placeholder={t('Any vendor')} /></SelectTrigger>
                                            <SelectContent searchable>
                                                <SelectItem value="all">{t('Any vendor')}</SelectItem>
                                                {vendors.map(vendor => (
                                                    <SelectItem key={vendor.id} value={vendor.id.toString()}>
                                                        {vendor.company_name ? `${vendor.company_name} — ${vendor.contact_person_name || vendor.name}` : vendor.name}
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
                        <span>{t('Vendor')}</span>
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
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-700"><Receipt className="h-5 w-5" /></span>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-slate-900 group-hover:text-primary">{invoice.invoice_number}</p>
                                        {invoice.supplier_invoice_number && <p className="mt-0.5 truncate text-xs text-slate-500" title={invoice.supplier_invoice_number}>{t('Supplier')}: {invoice.supplier_invoice_number}</p>}
                                    </div>
                                </div>

                                <div className="min-w-0 text-sm">
                                    <p className="truncate font-medium text-slate-700">{vendorName(invoice)}</p>
                                    {vendorContact(invoice) && <p className="mt-0.5 truncate text-xs text-slate-500">{vendorContact(invoice)}</p>}
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
                                        <PurchaseInvoiceActionButtons invoice={invoice} auth={auth} />
                                    </TooltipProvider>
                                    {can('download-purchase-invoices') && (
                                        <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Download PDF')} aria-label={t('Download PDF')} onClick={() => window.open(route('purchase-invoices.print', invoice.id) + '?download=pdf', '_blank')}>
                                            <Download className="h-4 w-4" />
                                        </Button>
                                    )}
                                    {(can('view-purchase-invoices') || (invoice.status === 'draft' && (can('edit-purchase-invoices') || can('post-purchase-invoices') || can('delete-purchase-invoices')))) && (
                                        <DropdownMenu modal={false}>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('More actions')} aria-label={t('More actions')}>
                                                    <MoreHorizontal className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end" className="min-w-[190px]">
                                                {can('view-purchase-invoices') && <DropdownMenuItem onClick={() => router.get(route('purchase-invoices.show', invoice.id))}><Eye className="mr-2 h-4 w-4" />{t('View')}</DropdownMenuItem>}
                                                {invoice.status === 'draft' && can('edit-purchase-invoices') && <DropdownMenuItem onClick={() => router.visit(route('purchase-invoices.edit', invoice.id))}><EditIcon className="mr-2 h-4 w-4" />{t('Edit')}</DropdownMenuItem>}
                                                {invoice.status === 'draft' && can('post-purchase-invoices') && <DropdownMenuItem onClick={() => router.post(route('purchase-invoices.post', invoice.id))}><FileText className="mr-2 h-4 w-4" />{t('Post Invoice')}</DropdownMenuItem>}
                                                {invoice.status === 'draft' && can('delete-purchase-invoices') && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem className="text-rose-600 focus:text-rose-600" onClick={() => openDeleteDialog(invoice.id)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem>
                                                    </>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                            </div>
                        ))}

                        {!invoices.data.length && (
                            <NoRecordsFound
                                icon={Receipt}
                                title={t('No purchase invoices found')}
                                description={t('Get started by creating your first purchase invoice.')}
                                hasFilters={hasFilters}
                                onClearFilters={clearAll}
                                createPermission="create-purchase-invoices"
                                onCreateClick={() => router.visit(route('purchase-invoices.create'))}
                                createButtonText={t('Create Purchase Invoice')}
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
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Purchase Invoice')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />

            {purchaseAlerts.map((alert) => (
                <div key={alert.id}>{alert.component}</div>
            ))}
        </AuthenticatedLayout>
    );
}

import { useEffect, useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, ChevronDown, Clock3, Copy, Download, Edit as EditIcon, Eye, FileSearch, FileText, Filter, LoaderCircle, MoreHorizontal, Package, Plus, PlusCircle, Receipt, RefreshCw, Search, Send, Check, Trash2, Wrench, X } from 'lucide-react';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { usePageButtons } from '@/hooks/usePageButtons';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Pagination } from '@/components/ui/pagination';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import NoRecordsFound from '@/components/no-records-found';
import { formatCurrency, formatDate } from '@/utils/helpers';
import { getStatusBadgeClasses } from './utils';
import { Quotation } from './types';

type ListedQuotation = Quotation & {
    catalog_type: 'product' | 'service' | null;
    is_overdue: boolean;
    salesperson?: { id: number; name: string } | null;
};

interface QuotationIndexProps {
    quotations: { data: ListedQuotation[]; current_page: number; last_page: number; per_page: number; total: number; from: number; to: number };
    customers: Array<{ id: number; name: string; email: string }>;
    summary: { total: number; awaiting: number; overdue: number };
    filters: Record<string, string>;
    auth: any;
    [key: string]: any;
}

const views = [['all', 'All quotations'], ['product', 'Product'], ['service', 'Service'], ['overdue', 'Overdue']];
const sorts = [['created_at', 'Created date'], ['quotation_date', 'Quotation date'], ['due_date', 'Due date'], ['total_amount', 'Total'], ['quotation_number', 'Number'], ['status', 'Status']];
const statuses = [['draft', 'Draft'], ['sent', 'Sent'], ['accepted', 'Accepted'], ['rejected', 'Rejected'], ['expired', 'Expired'], ['converted', 'Converted']];
const rowColumns = 'min-[1360px]:grid-cols-[minmax(200px,1fr)_minmax(150px,1.1fr)_minmax(110px,.7fr)_120px_100px_125px_104px]';

export default function Index() {
    const { t } = useTranslation();
    const { quotations, customers, summary, filters = {}, auth } = usePage<QuotationIndexProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [isSearching, setIsSearching] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.customer_id || filters.status || filters.date_range));
    const [createMenuOpen, setCreateMenuOpen] = useState(false);
    const [duplicateState, setDuplicateState] = useState<{ isOpen: boolean; quotationId: number | null }>({ isOpen: false, quotationId: null });

    useFlashMessages();

    const pageButtons = usePageButtons('quotationBtn', 'Quotation data');
    const dropboxBtn = usePageButtons('dropboxBtn', { module: 'Quotation', settingKey: 'Dropbox Quotation' });

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'quotations.destroy',
        defaultMessage: t('Are you sure you want to delete this quotation?')
    });

    const can = (permission: string) => Boolean(auth.user?.permissions?.includes(permission));
    const activeView = filters.view || 'all';
    const activeFilterCount = [filters.customer_id, filters.status, filters.date_range].filter(Boolean).length;
    const hasFilters = Boolean(filters.search || activeFilterCount || activeView !== 'all');

    const navigate = (extra: Record<string, string | number>) => router.get(route('quotations.index'), { ...filters, ...extra }, { preserveState: true, replace: true });
    const clearAdvanced = () => navigate({ customer_id: '', status: '', date_range: '', page: 1 });
    const clearAll = () => {
        setSearch('');
        router.get(route('quotations.index'), { per_page: filters.per_page || '' }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        const normalizedSearch = search.trim();
        if (normalizedSearch === (filters.search || '')) return;

        setIsSearching(true);
        const timeout = window.setTimeout(() => {
            router.get(route('quotations.index'), { ...filters, search: normalizedSearch, page: 1 }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['quotations', 'filters'],
                onFinish: () => setIsSearching(false),
            });
        }, normalizedSearch ? 300 : 0);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search]);

    const openQuotation = (quotation: ListedQuotation) => {
        if (can('view-quotations')) router.get(route('quotations.show', quotation.id));
    };

    const confirmDuplicate = () => {
        if (duplicateState.quotationId) {
            router.post(route('quotations.duplicate', duplicateState.quotationId));
        }
        setDuplicateState({ isOpen: false, quotationId: null });
    };

    const canConvert = (quotation: ListedQuotation) =>
        !quotation.converted_to_invoice
        && ['draft', 'sent', 'accepted'].includes(quotation.status)
        && can('convert-to-invoice-quotations')
        && can('create-sales-invoices');

    const statusLabel = (quotation: ListedQuotation) => quotation.converted_to_invoice
        ? t('Converted')
        : t(quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1));

    const rowIcon = (quotation: ListedQuotation) => {
        if (quotation.catalog_type === 'product') return <Package className="h-5 w-5" />;
        if (quotation.catalog_type === 'service') return <Wrench className="h-5 w-5" />;
        return <FileText className="h-5 w-5" />;
    };

    const summaryCards = [
        { label: t('Total quotations'), value: summary.total, icon: FileText, tone: 'bg-blue-50 text-blue-600', valueTone: '' },
        { label: t('Awaiting response'), value: summary.awaiting, icon: Send, tone: 'bg-amber-50 text-amber-700', valueTone: '' },
        { label: t('Overdue'), value: summary.overdue, icon: Clock3, tone: 'bg-rose-50 text-rose-600', valueTone: summary.overdue > 0 ? 'text-rose-600' : '' },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Quotations') }]}
            pageTitle={t('Quotations')}
            pageActions={
                <div className="flex gap-2">
                    {dropboxBtn.map((button) => (
                        <div key={button.id}>{button.component}</div>
                    ))}
                    {can('create-quotations') && (
                        <DropdownMenu open={createMenuOpen} onOpenChange={setCreateMenuOpen}>
                            <DropdownMenuTrigger asChild>
                                <Button size="sm">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('New quotation')}
                                    <ChevronDown className="ml-2 h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={() => router.visit(route('quotations.create', { type: 'product' }))}>
                                    <Package className="mr-2 h-4 w-4" />
                                    {t('Product Quotation')}
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => router.visit(route('quotations.create', { type: 'service' }))}>
                                    <Wrench className="mr-2 h-4 w-4" />
                                    {t('Service Quotation')}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                    {pageButtons.map((button) => (
                        <div key={button.id}>{button.component}</div>
                    ))}
                </div>
            }
        >
            <Head title={t('Quotations')} />

            <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label={t('Quotation summary')}>
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
                                    placeholder={t('Search by quotation number or customer')}
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
                                    <span className="sr-only">{t('Sort quotations')}</span>
                                    <select value={filters.sort || 'created_at'} onChange={e => navigate({ sort: e.target.value, page: 1 })} className="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-background pl-3 pr-9 text-sm sm:w-48">
                                        {sorts.map(([value, label]) => <option key={value} value={value}>{`${t('Sort')}: ${t(label)}`}</option>)}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-muted-foreground" />
                                </label>
                                <Button variant="outline" size="icon" className="h-10 w-10 rounded-lg" title={filters.direction === 'asc' ? t('Ascending') : t('Descending')} onClick={() => navigate({ direction: filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}>
                                    {filters.direction === 'asc' ? <ArrowUpAZ className="h-4 w-4" /> : <ArrowDownAZ className="h-4 w-4" />}
                                </Button>
                                <PerPageSelector routeName="quotations.index" filters={filters} className="h-10 w-36" />
                                <Button variant={showAdvanced || activeFilterCount ? 'secondary' : 'outline'} className="h-10 rounded-lg" onClick={() => setShowAdvanced(value => !value)}>
                                    <Filter className="mr-2 h-4 w-4" />
                                    {t('Filters')}
                                    {activeFilterCount > 0 && <span className="ml-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{activeFilterCount}</span>}
                                </Button>
                            </div>
                        </div>

                        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('Quotation views')}>
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
                            <div className="grid gap-3 border-t bg-slate-50/30 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_auto]">
                                {can('manage-users') && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">{t('Customer')}</span>
                                        <Select value={filters.customer_id || 'all'} onValueChange={value => navigate({ customer_id: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by customer')} className="bg-background"><SelectValue placeholder={t('Any customer')} /></SelectTrigger>
                                            <SelectContent searchable>
                                                <SelectItem value="all">{t('Any customer')}</SelectItem>
                                                {customers.map(customer => <SelectItem key={customer.id} value={customer.id.toString()}>{customer.name}</SelectItem>)}
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
                                    <span className="text-xs font-medium text-muted-foreground">{t('Quotation date')}</span>
                                    <DateRangePicker value={filters.date_range || ''} onChange={value => navigate({ date_range: value, page: 1 })} placeholder={t('Select date range')} />
                                </div>
                                <Button variant="ghost" className="self-end text-muted-foreground" onClick={clearAdvanced} disabled={!activeFilterCount}>{t('Clear filters')}</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="relative z-0 overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <div className={`hidden gap-3 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 min-[1360px]:grid ${rowColumns}`}>
                        <span>{t('Quotation')}</span>
                        <span>{t('Customer')}</span>
                        <span>{t('Salesperson')}</span>
                        <span>{t('Dates')}</span>
                        <span>{t('Status')}</span>
                        <span className="text-right">{t('Total')}</span>
                        <span />
                    </div>

                    <div className="divide-y">
                        {quotations.data.map(quotation => (
                            <div
                                key={quotation.id}
                                role="button"
                                tabIndex={0}
                                onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openQuotation(quotation); } }}
                                onClick={() => openQuotation(quotation)}
                                className={`group grid cursor-pointer gap-3 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary min-[1360px]:items-center min-[1360px]:px-6 min-[1360px]:py-4 ${rowColumns}`}
                            >
                                <div className="flex min-w-0 items-center gap-3.5">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-700">{rowIcon(quotation)}</span>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-slate-900 group-hover:text-primary">
                                            {quotation.quotation_number}
                                            {quotation.revision_number > 1 && <span className="ml-1.5 rounded-full bg-blue-100 px-1.5 py-0.5 text-[10px] font-medium text-blue-800">v{quotation.revision_number}</span>}
                                        </p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {quotation.catalog_type === 'product' ? t('Product') : quotation.catalog_type === 'service' ? t('Service') : t('Quotation')}
                                        </p>
                                    </div>
                                </div>

                                <div className="min-w-0 text-sm">
                                    <p className="truncate font-medium text-slate-700">{quotation.customer?.name || '-'}</p>
                                    {quotation.customer?.email && !quotation.customer.email.endsWith('@import.local') && <p className="mt-0.5 truncate text-xs text-slate-500">{quotation.customer.email}</p>}
                                </div>

                                <div className="flex justify-between text-sm min-[1360px]:block">
                                    <span className="text-slate-500 min-[1360px]:hidden">{t('Salesperson')}</span>
                                    <span className="truncate text-slate-700">{quotation.salesperson?.name || '-'}</span>
                                </div>

                                <div className="flex justify-between text-sm min-[1360px]:block">
                                    <span className="text-slate-500 min-[1360px]:hidden">{t('Dates')}</span>
                                    <div className="text-right min-[1360px]:text-left">
                                        <p className="text-slate-700">{formatDate(quotation.quotation_date)}</p>
                                        <p className={`mt-0.5 text-xs ${quotation.is_overdue ? 'font-medium text-rose-600' : 'text-slate-500'}`}>{t('Due')} {formatDate(quotation.due_date)}</p>
                                    </div>
                                </div>

                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className={getStatusBadgeClasses(quotation.converted_to_invoice ? 'converted' : quotation.status)}>{statusLabel(quotation)}</span>
                                    {quotation.is_overdue && <span className="rounded-full bg-rose-50 px-2 py-1 text-xs font-medium text-rose-600">{t('Overdue')}</span>}
                                </div>

                                <div className="flex justify-between text-sm min-[1360px]:block min-[1360px]:text-right">
                                    <span className="text-slate-500 min-[1360px]:hidden">{t('Total')}</span>
                                    <strong className="font-semibold tabular-nums text-slate-900">{formatCurrency(quotation.total_amount)}</strong>
                                </div>

                                <div className="flex justify-end gap-0.5" onClick={e => e.stopPropagation()} onKeyDown={e => e.stopPropagation()}>
                                    {can('print-quotations') && (
                                        <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Preview')} aria-label={t('Preview')} onClick={() => window.open(route('quotations.print', quotation.id), '_blank')}>
                                            <FileSearch className="h-4 w-4" />
                                        </Button>
                                    )}
                                    {can('print-quotations') && (
                                        <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Download PDF')} aria-label={t('Download PDF')} onClick={() => window.open(route('quotations.print', quotation.id) + '?download=pdf', '_blank')}>
                                            <Download className="h-4 w-4" />
                                        </Button>
                                    )}
                                    <DropdownMenu modal={false}>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('More actions')} aria-label={t('More actions')}>
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end" className="min-w-[190px]">
                                            {can('view-quotations') && <DropdownMenuItem onClick={() => router.get(route('quotations.show', quotation.id))}><Eye className="mr-2 h-4 w-4" />{t('View')}</DropdownMenuItem>}
                                            {quotation.status === 'draft' && can('edit-quotations') && <DropdownMenuItem onClick={() => router.visit(route('quotations.edit', quotation.id))}><EditIcon className="mr-2 h-4 w-4" />{t('Edit')}</DropdownMenuItem>}
                                            {quotation.status === 'draft' && can('sent-quotations') && <DropdownMenuItem onClick={() => router.post(route('quotations.sent', quotation.id))}><Send className="mr-2 h-4 w-4" />{t('Sent Quotation')}</DropdownMenuItem>}
                                            {quotation.status === 'sent' && can('approve-quotations') && <DropdownMenuItem onClick={() => router.post(route('quotations.approve', quotation.id))}><Check className="mr-2 h-4 w-4" />{t('Approve')}</DropdownMenuItem>}
                                            {quotation.status === 'sent' && can('reject-quotations') && <DropdownMenuItem onClick={() => router.post(route('quotations.reject', quotation.id))}><X className="mr-2 h-4 w-4" />{t('Reject')}</DropdownMenuItem>}
                                            {quotation.converted_to_invoice && quotation.invoice_id ? (
                                                <DropdownMenuItem onClick={() => router.get(route('sales-invoices.show', quotation.invoice_id))}><Receipt className="mr-2 h-4 w-4" />{t('View Sales Invoice')}</DropdownMenuItem>
                                            ) : canConvert(quotation) && (
                                                <DropdownMenuItem onClick={() => router.get(route('quotations.convert-to-invoice', quotation.id))}><RefreshCw className="mr-2 h-4 w-4" />{t('Convert to Invoice')}</DropdownMenuItem>
                                            )}
                                            {can('duplicate-quotations') && <DropdownMenuItem onClick={() => setDuplicateState({ isOpen: true, quotationId: quotation.id })}><Copy className="mr-2 h-4 w-4" />{t('Duplicate')}</DropdownMenuItem>}
                                            {quotation.status !== 'draft' && can('create-quotations-revision') && <DropdownMenuItem onClick={() => router.post(route('quotations.create-revision', quotation.id))}><PlusCircle className="mr-2 h-4 w-4" />{t('Create Version')}</DropdownMenuItem>}
                                            {quotation.status === 'draft' && can('delete-quotations') && (
                                                <>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem className="text-rose-600 focus:text-rose-600" onClick={() => openDeleteDialog(quotation.id)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem>
                                                </>
                                            )}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>
                        ))}

                        {!quotations.data.length && (
                            <NoRecordsFound
                                icon={Receipt}
                                title={t('No quotations found')}
                                description={t('Get started by creating your first quotation.')}
                                hasFilters={hasFilters}
                                onClearFilters={clearAll}
                                createPermission="create-quotations"
                                onCreateClick={() => setCreateMenuOpen(true)}
                                createButtonText={t('Create Quotation')}
                                className="h-auto"
                            />
                        )}
                    </div>

                    {quotations.total > 0 && (
                        <div className="border-t bg-slate-50/30 px-3">
                            <Pagination data={quotations} onPageChange={page => navigate({ page })} />
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Quotation')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />

            <ConfirmationDialog
                open={duplicateState.isOpen}
                onOpenChange={open => setDuplicateState(current => ({ ...current, isOpen: open }))}
                title={t('Duplicate Quotation')}
                message={t('Are you sure you want to duplicate this quotation?')}
                confirmText={t('Duplicate')}
                onConfirm={confirmDuplicate}
            />
        </AuthenticatedLayout>
    );
}

import { useEffect, useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, CheckCircle, ChevronDown, Clock3, CreditCard, Download, Eye, Filter, LoaderCircle, MoreHorizontal, Plus, Search, Trash2, WalletCards, X } from 'lucide-react';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { usePageButtons } from '@/hooks/usePageButtons';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Dialog } from '@/components/ui/dialog';
import { TooltipProvider } from '@/components/ui/tooltip';
import { Pagination } from '@/components/ui/pagination';
import { formatDate, getCurrencySymbolForCode } from '@/utils/helpers';
import NoRecordsFound from '@/components/no-records-found';
import Create from './Create';
import View from './View';
import { CustomerPaymentsIndexProps, CustomerPaymentModalState, CustomerPayment } from './types';

type ListedPayment = CustomerPayment & {
    customer_details?: { company_name?: string | null; contact_person_name?: string | null } | null;
};

type PageProps = Omit<CustomerPaymentsIndexProps, 'payments' | 'filters'> & {
    payments: { data: ListedPayment[]; current_page: number; last_page: number; per_page: number; total: number; from: number; to: number };
    summary: { total: number; cleared: number; pending: number };
    filters: Record<string, string>;
    companyAllSetting?: { defaultCurrency?: string };
};

const views = [['all', 'All payments'], ['cleared', 'Cleared'], ['pending', 'Pending'], ['cancelled', 'Cancelled']];
const sorts = [['created_at', 'Created date'], ['payment_date', 'Received date'], ['payment_amount', 'Amount'], ['payment_number', 'Number'], ['status', 'Status']];
const rowColumns = 'min-[1360px]:grid-cols-[minmax(180px,1fr)_minmax(150px,1.2fr)_minmax(125px,.8fr)_145px_85px_130px_104px]';

const statusClasses = (status: string) => {
    if (status === 'cleared') return 'bg-green-100 text-green-800';
    if (status === 'pending') return 'bg-yellow-100 text-yellow-800';
    return 'bg-red-100 text-red-800';
};

// Buttons added by other modules (Xero) stay on the row as they were.
const CustomerPaymentActionButtons = ({ payment, auth }: { payment: ListedPayment; auth: any }) => {
    const actionButtons = usePageButtons('xeroSyncCustomerPaymentBtn', { payment_id: payment.id, auth, status: payment.status });
    return <>{actionButtons.map((button) => <div key={button.id}>{button.component}</div>)}</>;
};

export default function Index() {
    const { t } = useTranslation();
    const pageProps = usePage().props as unknown as PageProps;
    const { payments, customers, bankAccounts, summary, filters = {}, auth } = pageProps;
    const baseCurrency = pageProps.companyAllSetting?.defaultCurrency || 'USD';
    // Each payment's own base currency decides whether a second (base) amount is shown.
    const baseOf = (payment: { base_currency_code?: string | null }) => (payment.base_currency_code || baseCurrency).toUpperCase();
    const formatMoney = (amount: number | string, currency: string) =>
        `${getCurrencySymbolForCode(currency, pageProps)} ${Number(amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    const [search, setSearch] = useState(filters.search || '');
    const [isSearching, setIsSearching] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.customer_id || filters.bank_account_id || filters.date_from));
    const [modalState, setModalState] = useState<CustomerPaymentModalState>({ isOpen: false, mode: '', data: null });
    const [viewingItem, setViewingItem] = useState<ListedPayment | null>(null);

    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'account.customer-payments.destroy',
        defaultMessage: t('Are you sure you want to delete this payment?')
    });

    const can = (permission: string) => Boolean(auth.user?.permissions?.includes(permission));
    const activeView = filters.status || 'all';
    const dateRange = filters.date_from && filters.date_to ? `${filters.date_from} - ${filters.date_to}` : '';
    const activeFilterCount = [filters.customer_id, filters.bank_account_id, dateRange].filter(Boolean).length;
    const hasFilters = Boolean(filters.search || activeFilterCount || filters.status);

    const navigate = (extra: Record<string, string | number>) => router.get(route('account.customer-payments.index'), { ...filters, ...extra }, { preserveState: true, replace: true });
    const clearAdvanced = () => navigate({ customer_id: '', bank_account_id: '', date_from: '', date_to: '', page: 1 });
    const clearAll = () => {
        setSearch('');
        router.get(route('account.customer-payments.index'), { per_page: filters.per_page || '' }, { preserveState: true, replace: true });
    };
    const changeDateRange = (value: string) => {
        const [from = '', to = ''] = value ? value.split(' - ') : [];
        navigate({ date_from: from, date_to: to, page: 1 });
    };

    useEffect(() => {
        const normalizedSearch = search.trim();
        if (normalizedSearch === (filters.search || '')) return;

        setIsSearching(true);
        const timeout = window.setTimeout(() => {
            router.get(route('account.customer-payments.index'), { ...filters, search: normalizedSearch, page: 1 }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['payments', 'filters'],
                onFinish: () => setIsSearching(false),
            });
        }, normalizedSearch ? 300 : 0);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search]);

    const handleStatusUpdate = (paymentId: number, status: string) => {
        router.patch(route('account.customer-payments.update-status', paymentId), { status });
    };

    const downloadReceipt = (payment: ListedPayment) => {
        window.open(route('account.customer-payments.receipt', payment.id) + '?download=pdf', '_blank');
    };

    const openPayment = (payment: ListedPayment) => {
        if (can('view-customer-payments')) setViewingItem(payment);
    };

    const customerName = (payment: ListedPayment) => payment.customer_details?.company_name || payment.customer_details?.contact_person_name || (payment as any).customer?.name || '-';
    const customerContact = (payment: ListedPayment) => {
        const contact = payment.customer_details?.contact_person_name || (payment as any).customer?.name;
        return contact && contact !== customerName(payment) ? contact : null;
    };
    const paymentMode = (payment: ListedPayment) => {
        const mode = (payment as any).payment_mode as string | null | undefined;
        if (!mode) return t('Payment');
        return t(mode.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()));
    };
    const invoiceNumbers = (payment: ListedPayment) => (payment.allocations || []).map((allocation) => allocation.invoice?.invoice_number).filter(Boolean).join(', ');

    const summaryCards = [
        { label: t('Total payments'), value: summary.total, icon: WalletCards, tone: 'bg-blue-50 text-blue-600', valueTone: '' },
        { label: t('Cleared'), value: summary.cleared, icon: CheckCircle, tone: 'bg-green-50 text-green-600', valueTone: '' },
        { label: t('Pending'), value: summary.pending, icon: Clock3, tone: 'bg-amber-50 text-amber-700', valueTone: summary.pending > 0 ? 'text-amber-700' : '' },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Accounting'), url: route('account.index') },
                { label: t('Payment Received') }
            ]}
            pageTitle={t('Payment Received')}
            pageActions={
                can('create-customer-payments') ? (
                    <Button size="sm" onClick={() => setModalState({ isOpen: true, mode: 'add', data: null })}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('New payment')}
                    </Button>
                ) : undefined
            }
        >
            <Head title={t('Payment Received')} />

            <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label={t('Payment summary')}>
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
                                    placeholder={t('Search by payment number, reference or customer')}
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
                                    <span className="sr-only">{t('Sort payments')}</span>
                                    <select value={filters.sort || 'created_at'} onChange={e => navigate({ sort: e.target.value, page: 1 })} className="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-background pl-3 pr-9 text-sm sm:w-48">
                                        {sorts.map(([value, label]) => <option key={value} value={value}>{`${t('Sort')}: ${t(label)}`}</option>)}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-muted-foreground" />
                                </label>
                                <Button variant="outline" size="icon" className="h-10 w-10 rounded-lg" title={filters.direction === 'asc' ? t('Ascending') : t('Descending')} onClick={() => navigate({ direction: filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}>
                                    {filters.direction === 'asc' ? <ArrowUpAZ className="h-4 w-4" /> : <ArrowDownAZ className="h-4 w-4" />}
                                </Button>
                                <PerPageSelector routeName="account.customer-payments.index" filters={filters} className="h-10 w-36" />
                                <Button variant={showAdvanced || activeFilterCount ? 'secondary' : 'outline'} className="h-10 rounded-lg" onClick={() => setShowAdvanced(value => !value)}>
                                    <Filter className="mr-2 h-4 w-4" />
                                    {t('Filters')}
                                    {activeFilterCount > 0 && <span className="ml-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{activeFilterCount}</span>}
                                </Button>
                            </div>
                        </div>

                        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('Payment views')}>
                            {views.map(([value, label]) => (
                                <button
                                    key={value}
                                    role="tab"
                                    aria-selected={activeView === value}
                                    onClick={() => navigate({ status: value === 'all' ? '' : value, page: 1 })}
                                    className={`whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${activeView === value ? 'bg-background text-foreground shadow-sm ring-1 ring-slate-200' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}
                                >
                                    {t(label)}
                                </button>
                            ))}
                        </div>

                        {showAdvanced && (
                            <div className="grid gap-3 border-t bg-slate-50/30 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_auto]">
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
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Bank Account')}</span>
                                    <Select value={filters.bank_account_id || 'all'} onValueChange={value => navigate({ bank_account_id: value === 'all' ? '' : value, page: 1 })}>
                                        <SelectTrigger aria-label={t('Filter by bank account')} className="bg-background"><SelectValue placeholder={t('Any bank account')} /></SelectTrigger>
                                        <SelectContent searchable>
                                            <SelectItem value="all">{t('Any bank account')}</SelectItem>
                                            {bankAccounts.map(account => <SelectItem key={account.id} value={account.id.toString()}>{account.account_name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Received date')}</span>
                                    <DateRangePicker value={dateRange} onChange={changeDateRange} placeholder={t('Select date range')} />
                                </div>
                                <Button variant="ghost" className="self-end text-muted-foreground" onClick={clearAdvanced} disabled={!activeFilterCount}>{t('Clear filters')}</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="relative z-0 overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <div className={`hidden gap-3 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 min-[1360px]:grid ${rowColumns}`}>
                        <span>{t('Payment')}</span>
                        <span>{t('Customer')}</span>
                        <span>{t('Invoice')}</span>
                        <span>{t('Received')}</span>
                        <span>{t('Status')}</span>
                        <span className="text-right">{t('Amount')}</span>
                        <span />
                    </div>

                    <div className="divide-y">
                        {payments.data.map(payment => {
                            const currency = payment.currency_code || baseCurrency;
                            const difference = (payment.allocations || []).reduce((sum, allocation) => sum + Number(allocation.exchange_difference || 0), 0);
                            // Base-currency amount actually settled: green for an exchange gain, red for a loss.
                            const baseTone = Math.abs(difference) < 0.005 ? 'text-gray-500' : difference > 0 ? 'text-green-600' : 'text-red-600';
                            const deposit = Number((payment as any).available_deposit || 0);
                            const invoices = invoiceNumbers(payment);
                            const canClear = payment.status === 'pending' && can('cleared-customer-payments');

                            return (
                                <div
                                    key={payment.id}
                                    role="button"
                                    tabIndex={0}
                                    onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPayment(payment); } }}
                                    onClick={() => openPayment(payment)}
                                    className={`group grid cursor-pointer gap-3 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary min-[1360px]:items-center min-[1360px]:px-6 min-[1360px]:py-4 ${rowColumns}`}
                                >
                                    <div className="flex min-w-0 items-center gap-3.5">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-700"><CreditCard className="h-5 w-5" /></span>
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-slate-900 group-hover:text-primary">{payment.payment_number}</p>
                                            <p className="mt-0.5 truncate text-xs text-slate-500">{paymentMode(payment)}</p>
                                        </div>
                                    </div>

                                    <div className="min-w-0 text-sm">
                                        <p className="truncate font-medium text-slate-700">{customerName(payment)}</p>
                                        {customerContact(payment) && <p className="mt-0.5 truncate text-xs text-slate-500">{customerContact(payment)}</p>}
                                    </div>

                                    <div className="flex min-w-0 justify-between gap-3 text-sm min-[1360px]:block">
                                        <span className="text-slate-500 min-[1360px]:hidden">{t('Invoice')}</span>
                                        <span className="block truncate text-slate-700" title={invoices}>{invoices || '-'}</span>
                                    </div>

                                    <div className="flex min-w-0 justify-between gap-3 text-sm min-[1360px]:block">
                                        <span className="text-slate-500 min-[1360px]:hidden">{t('Received')}</span>
                                        <div className="min-w-0 text-right min-[1360px]:text-left">
                                            <p className="text-slate-700">{formatDate(payment.payment_date)}</p>
                                            <p className="mt-0.5 truncate text-xs text-slate-500" title={(payment as any).bank_account?.account_name}>{(payment as any).bank_account?.account_name || '-'}</p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <span className={`rounded-full px-2 py-1 text-xs ${statusClasses(payment.status)}`}>{t(payment.status.charAt(0).toUpperCase() + payment.status.slice(1))}</span>
                                    </div>

                                    <div className="flex justify-between text-sm min-[1360px]:block min-[1360px]:text-right">
                                        <span className="text-slate-500 min-[1360px]:hidden">{t('Amount')}</span>
                                        <div className="text-right">
                                            <strong className="block whitespace-nowrap font-semibold tabular-nums text-slate-900" dir="ltr">{formatMoney(payment.payment_amount, currency)}</strong>
                                            {currency !== baseOf(payment) && (
                                                <span className={`mt-0.5 block whitespace-nowrap text-xs font-medium ${baseTone}`} dir="ltr">
                                                    {formatMoney((payment as any).base_amount ?? Number(payment.payment_amount) * Number((payment as any).exchange_rate || 1), baseOf(payment))}
                                                </span>
                                            )}
                                            {deposit > 0 && (
                                                <span className="mt-0.5 block whitespace-nowrap text-xs text-slate-500" dir="ltr">{t('Deposit')} {formatMoney(deposit, currency)}</span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap justify-end gap-0.5" onClick={e => e.stopPropagation()} onKeyDown={e => e.stopPropagation()}>
                                        <TooltipProvider>
                                            <CustomerPaymentActionButtons payment={payment} auth={auth} />
                                        </TooltipProvider>
                                        {can('view-customer-payments') && (
                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Download Receipt')} aria-label={t('Download Receipt')} onClick={() => downloadReceipt(payment)}>
                                                <Download className="h-4 w-4" />
                                            </Button>
                                        )}
                                        {(can('view-customer-payments') || canClear || (payment.status === 'pending' && can('delete-customer-payments'))) && (
                                            <DropdownMenu modal={false}>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('More actions')} aria-label={t('More actions')}>
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="min-w-[190px]">
                                                    {can('view-customer-payments') && <DropdownMenuItem onClick={() => setViewingItem(payment)}><Eye className="mr-2 h-4 w-4" />{t('View')}</DropdownMenuItem>}
                                                    {canClear && <DropdownMenuItem onClick={() => handleStatusUpdate(payment.id, 'cleared')}><CheckCircle className="mr-2 h-4 w-4" />{t('Mark as Cleared')}</DropdownMenuItem>}
                                                    {canClear && <DropdownMenuItem onClick={() => handleStatusUpdate(payment.id, 'cancelled')}><X className="mr-2 h-4 w-4" />{t('Cancel Payment')}</DropdownMenuItem>}
                                                    {payment.status === 'pending' && can('delete-customer-payments') && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem className="text-rose-600 focus:text-rose-600" onClick={() => openDeleteDialog(payment.id)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </div>
                                </div>
                            );
                        })}

                        {!payments.data.length && (
                            <NoRecordsFound
                                icon={CreditCard}
                                title={t('No payments found')}
                                description={t('Get started by recording your first payment.')}
                                hasFilters={hasFilters}
                                onClearFilters={clearAll}
                                createPermission="create-customer-payments"
                                onCreateClick={() => setModalState({ isOpen: true, mode: 'add', data: null })}
                                createButtonText={t('New payment')}
                                className="h-auto"
                            />
                        )}
                    </div>

                    {payments.total > 0 && (
                        <div className="border-t bg-slate-50/30 px-3">
                            <Pagination data={payments} onPageChange={page => navigate({ page })} />
                        </div>
                    )}
                </Card>
            </div>

            <Dialog open={modalState.isOpen} onOpenChange={() => setModalState({ isOpen: false, mode: '', data: null })}>
                {modalState.mode === 'add' && (
                    <Create
                        customers={customers}
                        bankAccounts={bankAccounts}
                        onSuccess={() => setModalState({ isOpen: false, mode: '', data: null })}
                    />
                )}
            </Dialog>

            <Dialog open={!!viewingItem} onOpenChange={() => setViewingItem(null)}>
                {viewingItem && (
                    <View
                        payment={viewingItem}
                        canApplyDeposit={auth.user?.permissions?.includes('cleared-customer-payments')}
                        onApplied={() => setViewingItem(null)}
                    />
                )}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Payment')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

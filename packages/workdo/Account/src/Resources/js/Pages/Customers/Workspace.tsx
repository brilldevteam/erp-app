import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Activity, AlertCircle, ArrowLeft, Building2, ChevronDown, ChevronLeft, ChevronRight, CircleDollarSign,
  Clock3, CreditCard, Download, FilePlus2, FileText, Mail, MapPin,
  Menu, MoreHorizontal, Phone, ReceiptText, Search, UserRound, WalletCards,
} from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { DatePicker } from '@/components/ui/date-picker';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import SavedPartyDocuments from '../Parties/SavedPartyDocuments';
import { formatAddressLines } from '@/types/address';
import { formatCurrency } from '@/utils/helpers';
import { formatTransactionMoney } from '@/utils/transaction-money';
import { Customer, CustomerWorkspaceData, PaginationData, WorkspaceTransaction } from './types';

type Props = {
  directory: PaginationData<Customer>;
  workspace: CustomerWorkspaceData;
  filters: Record<string, string>;
  auth: { user: { permissions: string[] } };
};

const tabs = [
  ['overview', 'Overview'], ['transactions', 'Transactions'], ['statement', 'Statement'],
  ['activity', 'Activity'], ['details', 'Files & details'],
] as const;

const statusTone: Record<string, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200', approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  completed: 'bg-emerald-50 text-emerald-700 border-emerald-200', posted: 'bg-blue-50 text-blue-700 border-blue-200',
  partial: 'bg-amber-50 text-amber-700 border-amber-200', pending: 'bg-amber-50 text-amber-700 border-amber-200',
  overdue: 'bg-red-50 text-red-700 border-red-200', cancelled: 'bg-slate-100 text-slate-600 border-slate-200',
};

function money(value: number | string | null | undefined, currency?: string) {
  return currency
    ? formatTransactionMoney(Number(value || 0), currency)
    : formatCurrency(Number(value || 0));
}

function date(value?: string | null) {
  return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : 'No activity';
}

function Directory({ items, selected, filters, mobile = false }: { items: PaginationData<Customer>; selected: number; filters: Record<string, string>; mobile?: boolean }) {
  const { t } = useTranslation();
  const [search, setSearch] = useState(filters.search || '');
  const [isSearching, setIsSearching] = useState(false);
  const views = [['all', 'All'], ['outstanding', 'Outstanding'], ['overdue', 'Overdue'], ['inactive', 'No activity'], ['portal_disabled', 'Portal disabled']];
  const navigate = (extra: Record<string, string | number>) => router.get(route('account.customers.workspace', selected), { ...filters, ...extra }, { preserveState: true, replace: true });

  useEffect(() => {
    const normalizedSearch = search.trim();
    if (normalizedSearch === (filters.search || '')) return;

    setIsSearching(true);
    const timeout = window.setTimeout(() => {
      router.get(route('account.customers.workspace', selected), {
        ...filters,
        search: normalizedSearch,
        directory_page: 1,
      }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['directory', 'filters'],
        onFinish: () => setIsSearching(false),
      });
    }, normalizedSearch ? 300 : 0);

    return () => window.clearTimeout(timeout);
  }, [search, filters.search, selected]);

  return <aside className={`${mobile ? '' : 'hidden lg:flex'} min-h-0 flex-col border-r bg-background lg:w-[340px] lg:shrink-0`} aria-label={t('Customer directory')}>
    <div className="space-y-3 border-b p-4">
      <div className="flex items-center justify-between"><div><p className="font-semibold">{t('Customers')}</p><p className="text-xs text-muted-foreground">{items.total} {t('accounts')}</p></div><Button size="sm" onClick={() => router.visit(route('account.customers.index'))}>{t('New')}</Button></div>
      <form onSubmit={e => e.preventDefault()} className="relative" role="search">
        <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
        <Input value={search} onChange={e => setSearch(e.target.value)} placeholder={t('Search customers...')} autoComplete="off" className="pl-9 pr-9" />
        {isSearching && <span className="absolute right-3 top-2.5 h-4 w-4 animate-spin rounded-full border-2 border-primary border-r-transparent" aria-label={t('Searching')} />}
        {!isSearching && search && <button type="button" onClick={() => setSearch('')} className="absolute right-2 top-1.5 rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground" aria-label={t('Clear search')}>&times;</button>}
      </form>
      <div className="flex gap-1 overflow-x-auto pb-1">
        {views.map(([value, label]) => <button key={value} onClick={() => navigate({ view: value, directory_page: 1 })} className={`whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-medium ${filters.view === value || (!filters.view && value === 'all') ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted'}`}>{t(label)}</button>)}
      </div>
    </div>
    <div className="min-h-0 flex-1 overflow-y-auto">
      {items.data.map(customer => <button key={customer.id} onClick={() => router.get(route('account.customers.workspace', customer.id), { ...filters, directory_page: items.current_page })} className={`w-full border-b p-4 text-left transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${selected === customer.id ? 'bg-primary/5 border-l-4 border-l-primary' : ''}`}>
        <div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate font-medium">{customer.company_name}</p><p className="truncate text-xs text-muted-foreground">{customer.customer_code} · {customer.contact_person_name || t('No contact')}</p></div><Badge variant="outline" className={customer.user?.is_enable_login && !customer.user?.is_disable ? 'text-emerald-700' : 'text-slate-500'}>{customer.user?.is_enable_login && !customer.user?.is_disable ? t('Portal on') : t('Portal off')}</Badge></div>
        <div className="mt-3 flex justify-between text-xs"><span className="text-muted-foreground">{t('Outstanding')}</span><strong>{money(customer.outstanding_base)}</strong></div>
        <div className="mt-1 flex justify-between text-xs"><span className="text-muted-foreground">{date(customer.last_activity_at)}</span>{Number(customer.overdue_base) > 0 && <span className="font-medium text-red-600">{money(customer.overdue_base)} {t('overdue')}</span>}</div>
      </button>)}
      {!items.data.length && <div className="p-8 text-center"><Building2 className="mx-auto mb-3 h-8 w-8 text-muted-foreground" /><p className="font-medium">{t('No customers found')}</p><p className="mt-1 text-sm text-muted-foreground">{t('Try changing the search or view.')}</p></div>}
    </div>
    {items.last_page > 1 && <div className="flex items-center justify-between gap-2 border-t bg-background px-3 py-2.5 text-xs">
      <span className="text-muted-foreground">{items.from || 0}–{items.to || items.data.length} {t('of')} {items.total}</span>
      <div className="flex items-center gap-1">
        <Button variant="outline" size="icon" className="h-8 w-8" disabled={items.current_page <= 1} onClick={() => navigate({ directory_page: items.current_page - 1 })}><ChevronLeft className="h-4 w-4" /><span className="sr-only">{t('Previous')}</span></Button>
        <span className="min-w-12 text-center font-medium">{items.current_page}/{items.last_page}</span>
        <Button variant="outline" size="icon" className="h-8 w-8" disabled={items.current_page >= items.last_page} onClick={() => navigate({ directory_page: items.current_page + 1 })}><ChevronRight className="h-4 w-4" /><span className="sr-only">{t('Next')}</span></Button>
      </div>
    </div>}
  </aside>;
}

function Metric({ label, value, icon: Icon, tone = 'text-foreground' }: { label: string; value: string; icon: any; tone?: string }) {
  return <Card><CardContent className="flex items-start justify-between p-5"><div><p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</p><p className={`mt-2 text-2xl font-semibold ${tone}`}>{value}</p></div><span className="rounded-xl bg-primary/10 p-2.5 text-primary"><Icon className="h-5 w-5" /></span></CardContent></Card>;
}

function Status({ value }: { value: string }) {
  return <Badge variant="outline" className={statusTone[value?.toLowerCase()] || 'bg-slate-50 text-slate-700'}><span className="mr-1.5 h-1.5 w-1.5 rounded-full bg-current" />{value?.replaceAll('_', ' ')}</Badge>;
}

export default function Workspace() {
  const { directory, workspace, filters, auth } = usePage<Props>().props;
  const { t } = useTranslation();
  const [directoryOpen, setDirectoryOpen] = useState(false);
  const { customer, summary } = workspace;
  const currency = summary.base_currency;
  const maxTrend = Math.max(1, ...workspace.revenue_trend.map(item => item.value));
  const query = (extra: Record<string, unknown>) => router.get(route('account.customers.workspace', customer.id), { ...filters, tab: workspace.tab, ...extra }, { preserveState: true, preserveScroll: true, replace: true });
  const create = (name: string) => router.visit(route(name) + `?customer_id=${customer.user_id}`);
  const statementPdfUrl = () => {
    const params = new URLSearchParams({
      start_date: filters.start_date || '',
      end_date: filters.end_date || '',
      download: 'pdf',
    });

    return `${route('account.customers.workspace.statement-pdf', customer.id)}?${params.toString()}`;
  };
  const documentRoute = (row: WorkspaceTransaction) => {
    const names: Record<string, string> = { invoice: 'sales-invoices.show', quotation: 'quotations.show', credit_note: 'account.credit-notes.show', retainer: 'retainers.show' };
    return names[row.type] ? route(names[row.type], row.id) : null;
  };
  const address = (value: Customer['billing_address']) => formatAddressLines(value, t).join(', ') || t('Not provided');
  const canEdit = workspace.available_actions.edit;

  return <AuthenticatedLayout breadcrumbs={[{ label: 'Accounting', url: route('account.index') }, { label: 'Customers', url: route('account.customers.index') }, { label: customer.company_name }]} pageTitle="">
    <Head title={`${customer.company_name} · Customer 360`} />
    <div className="-m-4 flex h-[calc(100vh-4rem)] min-h-[680px] overflow-hidden sm:-m-6">
      <Directory items={directory} selected={customer.id} filters={filters} />
      {directoryOpen && <div className="fixed inset-0 z-50 bg-background lg:hidden"><div className="flex h-14 items-center gap-2 border-b px-4"><Button variant="ghost" size="icon" onClick={() => setDirectoryOpen(false)}><ArrowLeft className="h-5 w-5" /></Button><strong>{t('Customers')}</strong></div><Directory items={directory} selected={customer.id} filters={filters} mobile /></div>}

      <main className="min-w-0 flex-1 overflow-y-auto bg-muted/30">
        <div className="mx-auto max-w-[1500px] space-y-5 p-4 sm:p-6 lg:p-8">
          <section className="rounded-2xl border bg-background p-5 shadow-sm sm:p-6">
            <div className="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
              <div className="flex min-w-0 flex-1 items-start gap-3"><Button variant="outline" size="icon" className="shrink-0 lg:hidden" onClick={() => setDirectoryOpen(true)}><Menu className="h-5 w-5" /><span className="sr-only">{t('Open customers')}</span></Button><div className="shrink-0 rounded-xl bg-primary/10 p-3 text-primary"><Building2 className="h-6 w-6" /></div><div className="min-w-0 flex-1"><div className="flex min-w-0 items-center gap-2"><h1 className="min-w-0 truncate text-xl font-semibold sm:text-2xl" title={customer.company_name}>{customer.company_name}</h1><Badge variant="outline" className="shrink-0">{customer.customer_code}</Badge></div><div className="mt-1 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"><Badge className={`shrink-0 ${customer.user?.is_enable_login && !customer.user?.is_disable ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{customer.user?.is_enable_login && !customer.user?.is_disable ? t('Portal enabled') : t('Portal disabled')}</Badge><p className="min-w-0 truncate text-sm text-muted-foreground">{customer.contact_person_name || t('No primary contact')} · {customer.contact_person_email || t('No email')}</p></div></div></div>
              <div className="flex shrink-0 flex-wrap gap-2 xl:flex-nowrap">
                <DropdownMenu><DropdownMenuTrigger asChild><Button><FilePlus2 className="mr-2 h-4 w-4" />{t('New transaction')}<ChevronDown className="ml-2 h-4 w-4" /></Button></DropdownMenuTrigger><DropdownMenuContent align="end">
                  {workspace.available_actions.quotation && <DropdownMenuItem onClick={() => router.visit(route('quotations.create', { type: 'product', customer_id: customer.user_id }))}>{t('Product Quotation')}</DropdownMenuItem>}
                  {workspace.available_actions.quotation && <DropdownMenuItem onClick={() => router.visit(route('quotations.create', { type: 'service', customer_id: customer.user_id }))}>{t('Service Quotation')}</DropdownMenuItem>}
                  {workspace.available_actions.invoice && <DropdownMenuItem onClick={() => create('sales-invoices.create')}>{t('Invoice')}</DropdownMenuItem>}
                  {workspace.available_actions.payment && <DropdownMenuItem onClick={() => create('account.customer-payments.index')}>{t('Payment')}</DropdownMenuItem>}
                  {workspace.available_actions.credit_note && <DropdownMenuItem onClick={() => create('account.credit-notes.create')}>{t('Credit note')}</DropdownMenuItem>}
                  {workspace.available_actions.retainer && <DropdownMenuItem onClick={() => create('retainers.create')}>{t('Retainer')}</DropdownMenuItem>}
                </DropdownMenuContent></DropdownMenu>
                {canEdit && <Button variant="outline" onClick={() => router.visit(route('account.customers.index', { edit: customer.id }))}>{t('Edit')}</Button>}
                <DropdownMenu><DropdownMenuTrigger asChild><Button variant="outline" size="icon"><MoreHorizontal className="h-4 w-4" /><span className="sr-only">{t('More actions')}</span></Button></DropdownMenuTrigger><DropdownMenuContent align="end">{workspace.available_actions.statement && <DropdownMenuItem onClick={() => window.open(statementPdfUrl(), '_blank')}><Download className="mr-2 h-4 w-4" />{t('Download statement')}</DropdownMenuItem>}</DropdownMenuContent></DropdownMenu>
              </div>
            </div>
            <nav className="mt-6 flex gap-1 overflow-x-auto border-b" role="tablist" aria-label={t('Customer workspace')}>
              {tabs.map(([value, label]) => <button key={value} role="tab" aria-selected={workspace.tab === value} onClick={() => query({ tab: value })} className={`whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${workspace.tab === value ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'}`}>{t(label)}</button>)}
            </nav>
          </section>

          {workspace.tab === 'overview' && <>
            <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><Metric label={t('Outstanding')} value={money(summary.outstanding, currency)} icon={WalletCards} tone={Number(summary.outstanding) > 0 ? 'text-amber-700' : ''} /><Metric label={t('Overdue')} value={money(summary.overdue, currency)} icon={Clock3} tone={Number(summary.overdue) > 0 ? 'text-red-600' : ''} /><Metric label={t('Invoiced')} value={money(summary.total_invoiced, currency)} icon={ReceiptText} /><Metric label={t('Paid')} value={money(summary.total_paid, currency)} icon={CircleDollarSign} tone="text-emerald-700" /></section>
            <section className="grid gap-5 xl:grid-cols-[1.45fr_1fr]">
              <Card><CardHeader><CardTitle>{t('Revenue trend')}</CardTitle><p className="text-sm text-muted-foreground">{t('Base-currency invoicing over the last six months')}</p></CardHeader><CardContent><div className="flex h-56 items-end gap-3 border-b border-l px-3 pb-1">{workspace.revenue_trend.map(item => <div key={item.month} className="group flex h-full flex-1 flex-col justify-end gap-2"><div title={`${item.month}: ${money(item.value, currency)}`} style={{ height: `${Math.max(item.value ? 8 : 2, item.value / maxTrend * 100)}%` }} className="min-h-1 rounded-t-md bg-primary/80 transition-colors group-hover:bg-primary" /><span className="truncate text-center text-[10px] text-muted-foreground sm:text-xs">{item.month.split(' ')[0]}</span></div>)}</div></CardContent></Card>
              <Card><CardHeader><CardTitle>{t('Receivables aging')}</CardTitle><p className="text-sm text-muted-foreground">{t('Outstanding balance by days overdue')}</p></CardHeader><CardContent className="space-y-4">{Object.entries(workspace.aging).map(([key, value]) => { const total = Math.max(1, Number(summary.outstanding)); return <div key={key}><div className="mb-1.5 flex justify-between text-sm"><span>{t({ current: 'Current', days_1_30: '1–30 days', days_31_60: '31–60 days', days_61_90: '61–90 days', over_90: 'Over 90 days' }[key] || key)}</span><strong>{money(value, currency)}</strong></div><div className="h-2 rounded-full bg-muted"><div className="h-2 rounded-full bg-primary" style={{ width: `${Math.min(100, value / total * 100)}%` }} /></div></div>; })}</CardContent></Card>
            </section>
            <section className="grid gap-5 lg:grid-cols-3">
              <Card><CardHeader><CardTitle>{t('Attention')}</CardTitle></CardHeader><CardContent className="space-y-3">{workspace.attention_items.length ? workspace.attention_items.map((item, i) => <div key={i} className="flex gap-3 rounded-xl border p-3"><AlertCircle className={`mt-0.5 h-4 w-4 shrink-0 ${item.tone === 'danger' ? 'text-red-600' : 'text-amber-600'}`} /><div><p className="text-sm font-medium">{item.title}</p><p className="text-xs text-muted-foreground">{item.detail}</p></div></div>) : <p className="text-sm text-muted-foreground">{t('No items need attention.')}</p>}</CardContent></Card>
              <Card><CardHeader><CardTitle>{t('Recent activity')}</CardTitle></CardHeader><CardContent className="space-y-4">{workspace.activity?.slice(0, 6).map(item => <div key={item.id} className="flex gap-3"><span className="mt-1 rounded-full bg-primary/10 p-1.5 text-primary"><Activity className="h-3.5 w-3.5" /></span><div><p className="text-sm font-medium">{item.title}</p><p className="text-xs text-muted-foreground">{item.detail} · {date(item.occurred_at)}</p></div></div>)}</CardContent></Card>
              <Card><CardHeader><CardTitle>{t('Account snapshot')}</CardTitle></CardHeader><CardContent className="space-y-4 text-sm"><div className="flex gap-3"><UserRound className="h-4 w-4 text-muted-foreground" /><span>{customer.contact_person_name || t('Not provided')}</span></div><div className="flex gap-3"><Mail className="h-4 w-4 text-muted-foreground" /><span className="break-all">{customer.contact_person_email || t('Not provided')}</span></div><div className="flex gap-3"><Phone className="h-4 w-4 text-muted-foreground" /><span>{customer.contact_person_mobile || t('Not provided')}</span></div><div className="flex gap-3"><CreditCard className="h-4 w-4 text-muted-foreground" /><span>{customer.payment_terms || t('No payment terms')}</span></div><div className="flex justify-between border-t pt-4"><span className="text-muted-foreground">{t('Average payment time')}</span><strong>{summary.average_payment_days === null ? t('No paid invoices') : `${summary.average_payment_days} ${t('days')}`}</strong></div>{workspace.currency_breakdown.map(item => <div key={item.currency} className="rounded-lg bg-muted/50 p-3"><div className="flex justify-between"><strong>{item.currency}</strong><span>{money(item.outstanding, item.currency)} {t('outstanding')}</span></div>{item.credits > 0 && <p className="mt-1 text-right text-xs text-emerald-700">{money(item.credits, item.currency)} {t('credit')}</p>}</div>)}</CardContent></Card>
            </section>
          </>}

          {workspace.tab === 'transactions' && <Card><CardHeader><div className="space-y-4"><div><CardTitle>{t('Transactions')}</CardTitle><p className="text-sm text-muted-foreground">{t('All accounting documents for this customer')}</p></div><div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4"><select value={filters.type || ''} onChange={e => query({ type: e.target.value, transaction_page: 1 })} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="">{t('All types')}</option>{['invoice', 'quotation', 'payment', 'credit_note', 'return', 'retainer'].map(type => <option key={type} value={type}>{t(type.replaceAll('_', ' '))}</option>)}</select><select value={filters.status || ''} onChange={e => query({ status: e.target.value, transaction_page: 1 })} className="h-10 rounded-md border bg-background px-3 text-sm"><option value="">{t('All statuses')}</option>{['draft', 'posted', 'partial', 'paid', 'overdue', 'pending', 'cleared', 'approved', 'completed', 'cancelled'].map(status => <option key={status} value={status}>{t(status)}</option>)}</select><DatePicker ariaLabel={t('Start date')} value={filters.start_date || ''} onChange={value => query({ start_date: value, transaction_page: 1 })} placeholder={t('Start date')} /><DatePicker ariaLabel={t('End date')} value={filters.end_date || ''} onChange={value => query({ end_date: value, transaction_page: 1 })} placeholder={t('End date')} minDate={filters.start_date ? new Date(`${filters.start_date}T00:00:00`) : undefined} /></div></div></CardHeader><CardContent className="p-0"><TransactionList rows={workspace.transactions?.data || []} currency={currency} onOpen={row => { const url = documentRoute(row); if (url) router.visit(url); }} />{workspace.transactions && workspace.transactions.last_page > 1 && <SimplePages current={workspace.transactions.current_page} last={workspace.transactions.last_page} onChange={page => query({ transaction_page: page })} />}</CardContent></Card>}

          {workspace.tab === 'statement' && <Card><CardHeader><div className="space-y-4"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><CardTitle>{t('Statement')}</CardTitle><p className="text-sm text-muted-foreground">{t('Running debit and credit balance')}</p></div><div className="flex flex-wrap gap-2"><Button variant="outline" onClick={() => window.open(route('account.customers.workspace.statement-csv', customer.id) + `?start_date=${filters.start_date || ''}&end_date=${filters.end_date || ''}`, '_blank')}><Download className="mr-2 h-4 w-4" />{t('Excel / CSV')}</Button><Button variant="outline" onClick={() => window.open(statementPdfUrl(), '_blank')}><Download className="mr-2 h-4 w-4" />{t('PDF')}</Button></div></div><div className="grid gap-2 sm:max-w-lg sm:grid-cols-2"><DatePicker ariaLabel={t('Statement start date')} value={filters.start_date || ''} onChange={value => query({ start_date: value, statement_page: 1 })} placeholder={t('Start date')} /><DatePicker ariaLabel={t('Statement end date')} value={filters.end_date || ''} onChange={value => query({ end_date: value, statement_page: 1 })} placeholder={t('End date')} minDate={filters.start_date ? new Date(`${filters.start_date}T00:00:00`) : undefined} /></div></div></CardHeader><CardContent className="p-0"><div className="flex justify-between border-y bg-muted/30 px-6 py-3 text-sm"><span className="text-muted-foreground">{t('Opening balance')}</span><strong>{money(workspace.statement?.opening_balance, currency)}</strong></div><Statement rows={workspace.statement?.data || []} currency={currency} /><div className="flex justify-end border-t p-5"><div className="text-right"><p className="text-xs uppercase text-muted-foreground">{t('Closing balance')}</p><p className="text-xl font-semibold">{money(workspace.statement?.closing_balance, currency)}</p></div></div>{workspace.statement && workspace.statement.last_page > 1 && <SimplePages current={workspace.statement.current_page} last={workspace.statement.last_page} onChange={page => query({ statement_page: page })} />}</CardContent></Card>}

          {workspace.tab === 'activity' && <Card><CardHeader><CardTitle>{t('Activity timeline')}</CardTitle><p className="text-sm text-muted-foreground">{t('Profile changes and real accounting events')}</p></CardHeader><CardContent><div className="relative ml-3 border-l pl-6">{workspace.activity?.map(item => <div key={item.id} className="relative pb-7"><span className="absolute -left-[31px] top-0 rounded-full border bg-background p-1.5 text-primary"><Activity className="h-3.5 w-3.5" /></span><p className="font-medium">{item.title}</p><p className="text-sm text-muted-foreground">{item.detail || t('System event')}</p><time className="mt-1 block text-xs text-muted-foreground">{date(item.occurred_at)}</time></div>)}</div></CardContent></Card>}

          {workspace.tab === 'details' && <section className="grid gap-5 xl:grid-cols-2"><Card><CardHeader><CardTitle>{t('Customer details')}</CardTitle></CardHeader><CardContent className="grid gap-5 sm:grid-cols-2"><Detail label={t('Company')} value={customer.company_name} /><Detail label={t('Customer code')} value={customer.customer_code} /><Detail label={t('Primary contact')} value={customer.contact_person_name} /><Detail label={t('Email')} value={customer.contact_person_email} /><Detail label={t('Mobile')} value={customer.contact_person_mobile} /><Detail label={t('Preferred currency')} value={customer.user?.preferred_currency_code || currency} /><Detail label={t('CR number')} value={customer.cr_number} /><Detail label={t('Tax number')} value={customer.tax_number} /><Detail label={t('Payment terms')} value={customer.payment_terms} /><Detail label={t('Notes')} value={customer.notes} wide /></CardContent></Card><Card><CardHeader><CardTitle>{t('Addresses & files')}</CardTitle></CardHeader><CardContent className="space-y-6"><div className="flex gap-3"><MapPin className="mt-1 h-4 w-4 text-muted-foreground" /><div><p className="font-medium">{t('Billing address')}</p><p className="mt-1 text-sm text-muted-foreground">{address(customer.billing_address)}</p></div></div><div className="flex gap-3"><MapPin className="mt-1 h-4 w-4 text-muted-foreground" /><div><p className="font-medium">{t('Shipping address')}</p><p className="mt-1 text-sm text-muted-foreground">{address(customer.shipping_address)}</p></div></div><SavedPartyDocuments partyType="customers" partyId={customer.id} attachments={customer.attachments || []} canRemove={canEdit} /></CardContent></Card></section>}
        </div>
      </main>
    </div>
  </AuthenticatedLayout>;
}

function TransactionList({ rows, currency, onOpen }: { rows: WorkspaceTransaction[]; currency: string; onOpen: (row: WorkspaceTransaction) => void }) {
  if (!rows.length) return <div className="p-12 text-center"><FileText className="mx-auto mb-3 h-9 w-9 text-muted-foreground" /><p className="font-medium">No transactions yet</p><p className="text-sm text-muted-foreground">New customer documents will appear here.</p></div>;
  return <><div className="hidden overflow-x-auto md:block"><table className="w-full text-sm"><thead className="border-y bg-muted/50 text-left text-xs uppercase text-muted-foreground"><tr><th className="px-6 py-3">Type</th><th className="px-4 py-3">Number</th><th className="px-4 py-3">Date</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Total</th><th className="px-6 py-3 text-right">Balance</th></tr></thead><tbody>{rows.map(row => <tr key={`${row.type}-${row.id}`} onClick={() => onOpen(row)} className="cursor-pointer border-b hover:bg-muted/40"><td className="px-6 py-4 font-medium capitalize">{row.type.replaceAll('_', ' ')}</td><td className="px-4 py-4 text-primary">{row.number}</td><td className="px-4 py-4">{date(row.date)}</td><td className="px-4 py-4"><Status value={row.status} /></td><td className="px-4 py-4 text-right">{money(row.total, row.currency || currency)}</td><td className="px-6 py-4 text-right font-medium">{money(row.balance, row.currency || currency)}</td></tr>)}</tbody></table></div><div className="divide-y md:hidden">{rows.map(row => <button key={`${row.type}-${row.id}`} onClick={() => onOpen(row)} className="w-full space-y-3 p-4 text-left hover:bg-muted/40"><div className="flex justify-between gap-3"><div><p className="font-medium capitalize">{row.type.replaceAll('_', ' ')} <span className="text-primary">{row.number}</span></p><p className="text-xs text-muted-foreground">{date(row.date)}</p></div><Status value={row.status} /></div><div className="flex justify-between text-sm"><span>{money(row.total, row.currency || currency)}</span><strong>{money(row.balance, row.currency || currency)} balance</strong></div></button>)}</div></>;
}

function Statement({ rows, currency }: { rows: WorkspaceTransaction[]; currency: string }) {
  return <div className="overflow-x-auto"><table className="w-full min-w-[720px] text-sm"><thead className="border-y bg-muted/50 text-left text-xs uppercase text-muted-foreground"><tr><th className="px-6 py-3">Date</th><th className="px-4 py-3">Transaction</th><th className="px-4 py-3 text-right">Debit</th><th className="px-4 py-3 text-right">Credit</th><th className="px-6 py-3 text-right">Balance</th></tr></thead><tbody>{rows.map(row => <tr key={`${row.type}-${row.id}`} className="border-b"><td className="px-6 py-4">{date(row.date)}</td><td className="px-4 py-4"><span className="capitalize">{row.type.replaceAll('_', ' ')}</span> · {row.number}</td><td className="px-4 py-4 text-right">{row.debit ? money(row.debit, currency) : '—'}</td><td className="px-4 py-4 text-right">{row.credit ? money(row.credit, currency) : '—'}</td><td className="px-6 py-4 text-right font-medium">{money(row.running_balance, currency)}</td></tr>)}</tbody></table></div>;
}

function SimplePages({ current, last, onChange }: { current: number; last: number; onChange: (page: number) => void }) {
  return <div className="flex items-center justify-between border-t p-4 text-sm"><span className="text-muted-foreground">Page {current} of {last}</span><div className="flex gap-2"><Button variant="outline" size="sm" disabled={current === 1} onClick={() => onChange(current - 1)}>Previous</Button><Button variant="outline" size="sm" disabled={current === last} onClick={() => onChange(current + 1)}>Next</Button></div></div>;
}

function Detail({ label, value, wide = false }: { label: string; value?: string | null; wide?: boolean }) {
  return <div className={wide ? 'sm:col-span-2' : ''}><p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">{label}</p><p className="mt-1 break-words text-sm">{value || 'Not provided'}</p></div>;
}

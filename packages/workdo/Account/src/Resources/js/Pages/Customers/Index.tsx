import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, Building2, ChevronDown, ChevronRight, Clock3, Edit3, Filter, LoaderCircle, Plus, Search, Trash2, Users, WalletCards, X } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Dialog } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Pagination } from '@/components/ui/pagination';
import { DatePicker } from '@/components/ui/date-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import Create from './Create';
import Edit from './Edit';
import { Customer } from './types';
import { formatCurrency } from '@/utils/helpers';

type Props = {
  customers: { data: Customer[]; current_page: number; last_page: number; per_page: number; total: number; from: number; to: number };
  filters: Record<string, string>;
  currencies?: Array<{ code: string; name: string; symbol?: string }>;
  editCustomer?: Customer | null;
  auth: { user: { permissions: string[] } };
};

const views = [['all', 'All customers'], ['outstanding', 'Outstanding'], ['overdue', 'Overdue'], ['inactive', 'No activity'], ['portal_disabled', 'Portal disabled']];
const sorts = [['company_name', 'Name'], ['outstanding', 'Outstanding'], ['overdue', 'Overdue'], ['last_activity', 'Last activity'], ['created_at', 'Created date']];

function date(value?: string | null) {
  return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : 'No activity';
}

export default function Index() {
  const { customers, filters = {}, currencies = [], auth, editCustomer } = usePage<Props>().props;
  const { t } = useTranslation();
  const [search, setSearch] = useState(filters.search || '');
  const [searchFocused, setSearchFocused] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.currency || filters.portal || filters.activity_from));
  const [modal, setModal] = useState<{ open: boolean; mode: 'add' | 'edit'; customer?: Customer }>({ open: !!editCustomer, mode: editCustomer ? 'edit' : 'add', customer: editCustomer || undefined });
  const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({ routeName: 'account.customers.destroy', defaultMessage: 'Are you sure you want to delete this customer?' });
  useFlashMessages();

  const navigate = (extra: Record<string, string | number>) => router.get(route('account.customers.index'), { ...filters, ...extra }, { preserveState: true, replace: true });
  const openWorkspace = (customer: Customer) => router.visit(route('account.customers.workspace', customer.id) + `?view=${filters.view || 'all'}&search=${encodeURIComponent(filters.search || '')}&sort=${filters.sort || 'company_name'}&direction=${filters.direction || 'asc'}`);
  const can = (permission: string) => auth.user.permissions.includes(permission);
  const activeView = filters.view || 'all';
  const activeFilterCount = [filters.currency, filters.portal, filters.activity_from].filter(Boolean).length;
  const accountsOutstanding = customers.data.filter(customer => Number(customer.outstanding_base) > 0).length;
  const accountsOverdue = customers.data.filter(customer => Number(customer.overdue_base) > 0).length;
  const clearAdvanced = () => {
    navigate({ currency: '', portal: '', activity_from: '', directory_page: 1 });
  };

  useEffect(() => {
    const normalizedSearch = search.trim();
    if (normalizedSearch === (filters.search || '')) return;

    setIsSearching(true);
    const timeout = window.setTimeout(() => {
      router.get(route('account.customers.index'), {
        ...filters,
        search: normalizedSearch,
        directory_page: 1,
      }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['customers', 'filters'],
        onFinish: () => setIsSearching(false),
      });
    }, normalizedSearch ? 300 : 0);

    return () => window.clearTimeout(timeout);
  }, [search, filters.search]);

  return <AuthenticatedLayout breadcrumbs={[{ label: 'Accounting', url: route('account.index') }, { label: 'Customers' }]} pageTitle={t('Customers')} pageActions={can('create-customers') ? <Button size="sm" onClick={() => setModal({ open: true, mode: 'add' })}><Plus className="mr-2 h-4 w-4" />{t('New customer')}</Button> : undefined}>
    <Head title={t('Customers')} />
    <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
      <section className="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label={t('Customer summary')}>
        <div className="col-span-2 rounded-2xl border bg-background p-4 shadow-sm shadow-slate-200/30 sm:col-span-1"><div className="flex items-center gap-3"><span className="rounded-xl bg-blue-50 p-2.5 text-blue-600"><Users className="h-5 w-5" /></span><div><p className="text-xs font-medium text-muted-foreground">{t('Customer accounts')}</p><p className="mt-0.5 text-xl font-semibold tabular-nums">{customers.total}</p></div></div></div>
        <div className="rounded-2xl border bg-background p-4 shadow-sm shadow-slate-200/30"><div className="flex items-center gap-3"><span className="rounded-xl bg-amber-50 p-2.5 text-amber-700"><WalletCards className="h-5 w-5" /></span><div><p className="text-xs font-medium text-muted-foreground">{t('Owing on this page')}</p><p className="mt-0.5 text-xl font-semibold tabular-nums">{accountsOutstanding}</p></div></div></div>
        <div className="rounded-2xl border bg-background p-4 shadow-sm shadow-slate-200/30"><div className="flex items-center gap-3"><span className="rounded-xl bg-rose-50 p-2.5 text-rose-600"><Clock3 className="h-5 w-5" /></span><div><p className="text-xs font-medium text-muted-foreground">{t('Overdue on this page')}</p><p className={`mt-0.5 text-xl font-semibold tabular-nums ${accountsOverdue > 0 ? 'text-rose-600' : ''}`}>{accountsOverdue}</p></div></div></div>
      </section>

      <Card className="relative z-20 overflow-visible rounded-2xl border shadow-sm shadow-slate-200/40"><CardContent className="p-0">
        <div className="flex flex-col gap-3 p-4 sm:p-5 xl:flex-row xl:items-center">
          <form onSubmit={e => { e.preventDefault(); navigate({ search: search.trim(), directory_page: 1 }); }} className="relative min-w-0 flex-1" role="search"><Search className="absolute left-3.5 top-3 h-4 w-4 text-muted-foreground" /><Input value={search} onFocus={() => setSearchFocused(true)} onBlur={() => window.setTimeout(() => setSearchFocused(false), 150)} onChange={e => setSearch(e.target.value)} autoComplete="off" aria-expanded={searchFocused && Boolean(search.trim())} aria-controls="customer-search-results" placeholder={t('Search customers by name, code, contact or tax number')} className="h-10 border-slate-200 bg-slate-50/70 pl-10 pr-10 focus-visible:bg-background" />{isSearching ? <LoaderCircle className="absolute right-3.5 top-3 h-4 w-4 animate-spin text-primary" /> : search && <button type="button" onMouseDown={e => e.preventDefault()} onClick={() => setSearch('')} className="absolute right-3 top-2.5 rounded-md p-1 text-muted-foreground hover:bg-slate-200 hover:text-foreground" aria-label={t('Clear search')}><X className="h-4 w-4" /></button>}
            {searchFocused && search.trim() && <div id="customer-search-results" role="listbox" className="absolute left-0 right-0 top-12 z-40 overflow-hidden rounded-xl border bg-background shadow-xl shadow-slate-300/30">
              <div className="flex items-center justify-between border-b bg-slate-50/70 px-4 py-2 text-xs text-muted-foreground"><span>{isSearching ? t('Searching...') : t('Matching customers')}</span>{!isSearching && <span>{customers.total} {t('results')}</span>}</div>
              {!isSearching && customers.data.slice(0, 7).map(customer => <button key={customer.id} type="button" role="option" onMouseDown={e => e.preventDefault()} onClick={() => openWorkspace(customer)} className="flex w-full items-center gap-3 border-b px-4 py-3 text-left transition-colors last:border-0 hover:bg-blue-50/60 focus:bg-blue-50/60 focus:outline-none"><span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-blue-100 bg-blue-50 text-xs font-semibold text-blue-700">{customer.company_name?.charAt(0).toUpperCase()}</span><span className="min-w-0 flex-1"><span className="block truncate text-sm font-medium text-slate-900">{customer.company_name}</span><span className="block truncate text-xs text-slate-500">{customer.customer_code} · {customer.contact_person_name || t('No primary contact')}</span></span><ChevronRight className="h-4 w-4 shrink-0 text-slate-400" /></button>)}
              {!isSearching && !customers.data.length && <div className="px-4 py-8 text-center"><p className="text-sm font-medium">{t('No matching customers')}</p><p className="mt-1 text-xs text-muted-foreground">{t('Try a name, customer code, email or tax number.')}</p></div>}
            </div>}
          </form>
          <div className="flex flex-wrap items-center gap-2">
            <label className="relative min-w-[160px] flex-1 sm:flex-none"><span className="sr-only">{t('Sort customers')}</span><select value={filters.sort || 'company_name'} onChange={e => navigate({ sort: e.target.value, directory_page: 1 })} className="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-background pl-3 pr-9 text-sm sm:w-44">{sorts.map(([value, label]) => <option key={value} value={value}>{t(`Sort: ${label}`)}</option>)}</select><ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-muted-foreground" /></label>
            <Button variant="outline" size="icon" className="h-10 w-10 rounded-lg" title={filters.direction === 'desc' ? t('Descending') : t('Ascending')} onClick={() => navigate({ direction: filters.direction === 'desc' ? 'asc' : 'desc' })}>{filters.direction === 'desc' ? <ArrowDownAZ className="h-4 w-4" /> : <ArrowUpAZ className="h-4 w-4" />}<span className="sr-only">{filters.direction === 'desc' ? t('Descending') : t('Ascending')}</span></Button>
            <Button variant={showAdvanced || activeFilterCount ? 'secondary' : 'outline'} className="h-10 rounded-lg" onClick={() => setShowAdvanced(value => !value)}><Filter className="mr-2 h-4 w-4" />{t('Filters')}{activeFilterCount > 0 && <span className="ml-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{activeFilterCount}</span>}</Button>
          </div>
        </div>
        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('Customer views')}>{views.map(([value, label]) => <button key={value} role="tab" aria-selected={activeView === value} onClick={() => navigate({ view: value, directory_page: 1 })} className={`whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${activeView === value ? 'bg-background text-foreground shadow-sm ring-1 ring-slate-200' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}>{t(label)}</button>)}</div>
        {showAdvanced && <div className="grid gap-3 border-t bg-slate-50/30 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_1fr_auto]">
          <label className="space-y-1.5"><span className="text-xs font-medium text-muted-foreground">{t('Currency')}</span><Select value={filters.currency || 'all'} onValueChange={value => navigate({ currency: value === 'all' ? '' : value, directory_page: 1 })}><SelectTrigger aria-label={t('Filter by currency')} className="bg-background"><SelectValue placeholder={t('Any currency')} /></SelectTrigger><SelectContent searchable><SelectItem value="all">{t('Any currency')}</SelectItem>{currencies.map(currency => <SelectItem key={currency.code} value={currency.code}>{currency.name} ({currency.code})</SelectItem>)}</SelectContent></Select></label>
          <label className="space-y-1.5"><span className="text-xs font-medium text-muted-foreground">{t('Portal access')}</span><select value={filters.portal || ''} onChange={e => navigate({ portal: e.target.value, directory_page: 1 })} className="h-10 w-full rounded-md border bg-background px-3 text-sm"><option value="">{t('Any portal state')}</option><option value="enabled">{t('Portal enabled')}</option><option value="disabled">{t('Portal disabled')}</option></select></label>
          <label className="space-y-1.5"><span className="text-xs font-medium text-muted-foreground">{t('Activity since')}</span><DatePicker value={filters.activity_from || ''} onChange={value => navigate({ activity_from: value, directory_page: 1 })} placeholder={t('Select activity date')} ariaLabel={t('Activity since')} /></label>
          <Button variant="ghost" className="self-end text-muted-foreground" onClick={clearAdvanced} disabled={!activeFilterCount}>{t('Clear filters')}</Button>
        </div>}
      </CardContent></Card>

      <Card className="relative z-0 overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
        <div className="hidden grid-cols-[minmax(260px,1.3fr)_minmax(180px,.8fr)_minmax(180px,.8fr)_150px_150px_130px_96px] gap-4 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 xl:grid"><span>{t('Customer')}</span><span>{t('Primary contact')}</span><span>{t('Access')}</span><span className="text-right">{t('Outstanding')}</span><span className="text-right">{t('Overdue')}</span><span>{t('Last activity')}</span><span /></div>
        <div className="divide-y">
          {customers.data.map(customer => <div key={customer.id} role="button" tabIndex={0} onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') openWorkspace(customer); }} onClick={() => openWorkspace(customer)} className="group grid cursor-pointer gap-4 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary xl:grid-cols-[minmax(260px,1.3fr)_minmax(180px,.8fr)_minmax(180px,.8fr)_150px_150px_130px_96px] xl:items-center xl:px-6 xl:py-4">
            <div className="flex min-w-0 items-center gap-3.5"><span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-sm font-semibold text-blue-700">{customer.company_name?.charAt(0).toUpperCase()}</span><div className="min-w-0"><p className="truncate font-semibold text-slate-900 group-hover:text-primary">{customer.company_name}</p><p className="mt-0.5 text-xs text-slate-500">{customer.customer_code}</p></div></div>
            <div className="min-w-0 text-sm"><p className="truncate font-medium text-slate-700">{customer.contact_person_name || t('No primary contact')}</p><p className="mt-0.5 truncate text-xs text-slate-500">{customer.contact_person_email || customer.contact_person_mobile || t('No contact details')}</p></div>
            <div className="flex flex-wrap items-center gap-2"><span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${customer.user?.is_enable_login && !customer.user?.is_disable ? 'border-emerald-200 bg-emerald-50/70 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'}`}><span className={`h-1.5 w-1.5 rounded-full ${customer.user?.is_enable_login && !customer.user?.is_disable ? 'bg-emerald-500' : 'bg-slate-400'}`} />{customer.user?.is_enable_login && !customer.user?.is_disable ? t('Portal on') : t('Portal off')}</span>{Boolean(customer.user?.is_disable) && <span className="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50/70 px-2.5 py-1 text-xs font-medium text-rose-700"><span className="h-1.5 w-1.5 rounded-full bg-rose-500" />{t('Disabled')}</span>}</div>
            <div className="flex justify-between text-sm xl:block xl:text-right"><span className="text-slate-500 xl:hidden">{t('Outstanding')}</span><strong className="font-semibold tabular-nums text-slate-900">{formatCurrency(customer.outstanding_base || 0)}</strong></div>
            <div className="flex justify-between text-sm xl:block xl:text-right"><span className="text-slate-500 xl:hidden">{t('Overdue')}</span><strong className={`font-semibold tabular-nums ${Number(customer.overdue_base) > 0 ? 'text-rose-600' : 'text-slate-600'}`}>{formatCurrency(customer.overdue_base || 0)}</strong></div>
            <div className="flex items-center gap-2 text-xs text-slate-500"><Clock3 className="h-3.5 w-3.5" />{date(customer.last_activity_at)}</div>
            <div className="flex justify-end gap-0.5 opacity-100 transition-opacity xl:opacity-20 xl:group-hover:opacity-100 xl:group-focus-within:opacity-100" onClick={e => e.stopPropagation()}>{can('edit-customers') && <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" aria-label={t('Edit customer')} onClick={() => setModal({ open: true, mode: 'edit', customer })}><Edit3 className="h-4 w-4" /></Button>}{can('delete-customers') && <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg hover:bg-rose-50" aria-label={t('Delete customer')} onClick={() => openDeleteDialog(customer.id)}><Trash2 className="h-4 w-4 text-rose-500" /></Button>}<Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" aria-label={t('Open customer')} onClick={() => openWorkspace(customer)}><ChevronRight className="h-4 w-4" /></Button></div>
          </div>)}
          {!customers.data.length && <div className="p-14 text-center"><Building2 className="mx-auto mb-3 h-10 w-10 text-muted-foreground" /><p className="font-medium">{t('No customers found')}</p><p className="mt-1 text-sm text-muted-foreground">{t('Adjust your search or create a new customer.')}</p></div>}
        </div>
        {customers.total > 0 && <div className="border-t bg-slate-50/30 px-3"><Pagination data={customers} onPageChange={page => navigate({ directory_page: page })} /></div>}
      </Card>
    </div>

    <Dialog open={modal.open} onOpenChange={open => setModal(current => ({ ...current, open }))}>{modal.mode === 'add' ? <Create onSuccess={() => setModal(current => ({ ...current, open: false }))} /> : modal.customer ? <Edit customer={modal.customer} onSuccess={() => setModal(current => ({ ...current, open: false }))} /> : null}</Dialog>
    <ConfirmationDialog open={deleteState.isOpen} onOpenChange={closeDeleteDialog} title="Delete Customer" message={deleteState.message} confirmText="Delete" onConfirm={confirmDelete} variant="destructive" />
  </AuthenticatedLayout>;
}

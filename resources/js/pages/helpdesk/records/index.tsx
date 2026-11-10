import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, CheckCircle2, ChevronDown, Clock3, Filter, Inbox, LoaderCircle, Search, Ticket, Timer, X } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Pagination } from '@/components/ui/pagination';
import NoRecordsFound from '@/components/no-records-found';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { formatDate } from '@/utils/helpers';

type TicketRecord = {
    id: number;
    ticket_id: string;
    title: string;
    status: string;
    priority: string;
    created_at: string;
    resolved_at?: string | null;
    category?: { id: number; name: string; color?: string } | null;
    creator?: { id: number; name: string } | null;
    requester?: { id: number; name: string } | null;
    assigned_agent?: { id: number; name: string } | null;
};

type Breakdown = Array<{ label: string; count: number }>;

type Props = {
    records: { data: TicketRecord[]; current_page: number; last_page: number; per_page: number; total: number; from: number; to: number };
    summary: {
        total: number;
        by_status: Record<string, number>;
        by_priority: Record<string, number>;
        by_category: Breakdown;
        by_company?: Breakdown;
        by_agent?: Breakdown;
        avg_first_response_seconds: number | null;
        avg_resolution_seconds: number | null;
    };
    filters: Record<string, string>;
    categories: Array<{ id: number; name: string }>;
    companies: Array<{ id: number; name: string }>;
    agents: Array<{ id: number; name: string }>;
    supportSide: boolean;
};

const statuses = ['open', 'in_progress', 'waiting_customer', 'waiting_internal', 'resolved', 'closed'];
const priorities = ['low', 'medium', 'high', 'urgent'];
const sorts = [['created_at', 'Created date'], ['resolved_at', 'Resolved date'], ['ticket_id', 'Ticket number'], ['priority', 'Priority'], ['status', 'Status']];
const rowColumns = 'xl:grid-cols-[minmax(220px,1.4fr)_minmax(150px,1fr)_minmax(130px,.9fr)_110px_110px_120px]';

const label = (value: string) => value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusTone: Record<string, string> = {
    open: 'border-blue-200 bg-blue-50 text-blue-700',
    in_progress: 'border-amber-200 bg-amber-50 text-amber-700',
    waiting_customer: 'border-violet-200 bg-violet-50 text-violet-700',
    waiting_internal: 'border-slate-200 bg-slate-50 text-slate-700',
    resolved: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    closed: 'border-gray-200 bg-gray-100 text-gray-700',
};

const priorityTone: Record<string, string> = {
    low: 'text-slate-600',
    medium: 'text-blue-700',
    high: 'text-amber-700',
    urgent: 'text-rose-600',
};

function duration(seconds: number | null, empty: string) {
    if (seconds === null) return empty;
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) return `${minutes}m`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ${minutes % 60}m`;
    return `${Math.floor(hours / 24)}d ${hours % 24}h`;
}

function BreakdownList({ title, items }: { title: string; items: Breakdown }) {
    const max = Math.max(1, ...items.map((item) => item.count));
    return (
        <div className="min-w-0">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-[0.08em] text-slate-500">{title}</h3>
            {items.length ? (
                <ul className="space-y-2">
                    {items.map((item) => (
                        <li key={item.label} className="text-sm">
                            <div className="flex justify-between gap-3">
                                <span className="truncate text-slate-700" title={item.label}>{item.label}</span>
                                <span className="font-semibold tabular-nums text-slate-900">{item.count}</span>
                            </div>
                            <div className="mt-1 h-1.5 rounded-full bg-slate-100">
                                <div className="h-1.5 rounded-full bg-primary/60" style={{ width: `${(item.count / max) * 100}%` }} />
                            </div>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-slate-500">-</p>
            )}
        </div>
    );
}

export default function Records() {
    const { t } = useTranslation();
    const { records, summary, filters = {}, categories, companies, agents, supportSide } = usePage().props as unknown as Props;
    const [search, setSearch] = useState(filters.search || '');
    const [isSearching, setIsSearching] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.priority || filters.category_id || filters.company_id || filters.agent_id || filters.date_from || filters.date_to));

    useFlashMessages();

    const activeStatus = filters.status || 'all';
    const activeFilterCount = [filters.priority, filters.category_id, filters.company_id, filters.agent_id, filters.date_from, filters.date_to].filter(Boolean).length;
    const hasFilters = Boolean(filters.search || filters.status || activeFilterCount);

    const navigate = (extra: Record<string, string | number>) => router.get(route('helpdesk-tickets.records'), { ...filters, ...extra }, { preserveState: true, replace: true });
    const clearAdvanced = () => navigate({ priority: '', category_id: '', company_id: '', agent_id: '', date_from: '', date_to: '', page: 1 });
    const clearAll = () => {
        setSearch('');
        router.get(route('helpdesk-tickets.records'), { per_page: filters.per_page || '' }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        const normalized = search.trim();
        if (normalized === (filters.search || '')) return;

        setIsSearching(true);
        const timeout = window.setTimeout(() => {
            router.get(route('helpdesk-tickets.records'), { ...filters, search: normalized, page: 1 }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['records', 'summary', 'filters'],
                onFinish: () => setIsSearching(false),
            });
        }, normalized ? 300 : 0);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search]);

    const openCount = (summary.by_status.open ?? 0) + (summary.by_status.in_progress ?? 0) + (summary.by_status.waiting_customer ?? 0) + (summary.by_status.waiting_internal ?? 0);
    const doneCount = (summary.by_status.resolved ?? 0) + (summary.by_status.closed ?? 0);

    const cards = [
        { label: t('Total tickets'), value: String(summary.total), icon: Ticket, tone: 'bg-blue-50 text-blue-600' },
        { label: t('Open'), value: String(openCount), icon: Inbox, tone: 'bg-amber-50 text-amber-700' },
        { label: t('Resolved or closed'), value: String(doneCount), icon: CheckCircle2, tone: 'bg-green-50 text-green-600' },
        { label: t('Avg first response'), value: duration(summary.avg_first_response_seconds, t('No responses yet')), icon: Clock3, tone: 'bg-violet-50 text-violet-600' },
        { label: t('Avg resolution'), value: duration(summary.avg_resolution_seconds, t('None resolved yet')), icon: Timer, tone: 'bg-rose-50 text-rose-600' },
    ];

    const statusBreakdown = statuses.map((status) => ({ label: t(label(status)), count: summary.by_status[status] ?? 0 }));
    const priorityBreakdown = priorities.map((priority) => ({ label: t(label(priority)), count: summary.by_priority[priority] ?? 0 }));

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Helpdesk'), url: route('helpdesk-tickets.index') }, { label: t('Ticket Records') }]} pageTitle={t('Ticket Records')}>
            <Head title={t('Ticket Records')} />

            <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
                <section className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" aria-label={t('Ticket summary')}>
                    {cards.map((card, index) => (
                        <div key={card.label} className={`rounded-2xl border bg-background p-4 shadow-sm shadow-slate-200/30 ${index === 0 ? 'col-span-2 md:col-span-1' : ''}`}>
                            <div className="flex items-center gap-3">
                                <span className={`rounded-xl p-2.5 ${card.tone}`}><card.icon className="h-5 w-5" /></span>
                                <div className="min-w-0">
                                    <p className="text-xs font-medium text-muted-foreground">{card.label}</p>
                                    <p className="mt-0.5 truncate text-xl font-semibold tabular-nums">{card.value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </section>

                <section className={`grid gap-4 sm:grid-cols-2 ${supportSide ? 'xl:grid-cols-5' : 'xl:grid-cols-3'}`}>
                    {[
                        { title: t('By status'), items: statusBreakdown },
                        { title: t('By priority'), items: priorityBreakdown },
                        { title: t('By category'), items: summary.by_category },
                        ...(supportSide ? [
                            { title: t('By company'), items: summary.by_company ?? [] },
                            { title: t('By agent'), items: summary.by_agent ?? [] },
                        ] : []),
                    ].map((section) => (
                        <Card key={section.title} className="rounded-2xl border shadow-sm shadow-slate-200/40">
                            <CardContent className="p-5">
                                <BreakdownList title={section.title} items={section.items} />
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <Card className="relative z-20 overflow-visible rounded-2xl border shadow-sm shadow-slate-200/40">
                    <CardContent className="p-0">
                        <div className="flex flex-col gap-3 p-4 sm:p-5 xl:flex-row xl:items-center">
                            <form onSubmit={(e) => { e.preventDefault(); navigate({ search: search.trim(), page: 1 }); }} className="relative min-w-0 flex-1" role="search">
                                <Search className="absolute left-3.5 top-3 h-4 w-4 text-muted-foreground" />
                                <Input value={search} onChange={(e) => setSearch(e.target.value)} autoComplete="off" placeholder={t('Search by ticket number, title, customer or company')} className="h-10 border-slate-200 bg-slate-50/70 pl-10 pr-10 focus-visible:bg-background" />
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
                                    <span className="sr-only">{t('Sort records')}</span>
                                    <select value={filters.sort || 'created_at'} onChange={(e) => navigate({ sort: e.target.value, page: 1 })} className="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-background pl-3 pr-9 text-sm sm:w-48">
                                        {sorts.map(([value, name]) => <option key={value} value={value}>{`${t('Sort')}: ${t(name)}`}</option>)}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-muted-foreground" />
                                </label>
                                <Button variant="outline" size="icon" className="h-10 w-10 rounded-lg" title={filters.direction === 'asc' ? t('Ascending') : t('Descending')} onClick={() => navigate({ direction: filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}>
                                    {filters.direction === 'asc' ? <ArrowUpAZ className="h-4 w-4" /> : <ArrowDownAZ className="h-4 w-4" />}
                                </Button>
                                <PerPageSelector routeName="helpdesk-tickets.records" filters={filters} className="h-10 w-36" />
                                <Button variant={showAdvanced || activeFilterCount ? 'secondary' : 'outline'} className="h-10 rounded-lg" onClick={() => setShowAdvanced((value) => !value)}>
                                    <Filter className="mr-2 h-4 w-4" />
                                    {t('Filters')}
                                    {activeFilterCount > 0 && <span className="ml-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{activeFilterCount}</span>}
                                </Button>
                            </div>
                        </div>

                        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('Ticket statuses')}>
                            {['all', ...statuses].map((status) => (
                                <button
                                    key={status}
                                    role="tab"
                                    aria-selected={activeStatus === status}
                                    onClick={() => navigate({ status: status === 'all' ? '' : status, page: 1 })}
                                    className={`whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${activeStatus === status ? 'bg-background text-foreground shadow-sm ring-1 ring-slate-200' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}
                                >
                                    {status === 'all' ? t('All tickets') : t(label(status))}
                                </button>
                            ))}
                        </div>

                        {showAdvanced && (
                            <div className="grid gap-3 border-t bg-slate-50/30 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-4">
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Priority')}</span>
                                    <Select value={filters.priority || 'all'} onValueChange={(value) => navigate({ priority: value === 'all' ? '' : value, page: 1 })}>
                                        <SelectTrigger aria-label={t('Filter by priority')} className="bg-background"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('Any priority')}</SelectItem>
                                            {priorities.map((priority) => <SelectItem key={priority} value={priority}>{t(label(priority))}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Category')}</span>
                                    <Select value={filters.category_id || 'all'} onValueChange={(value) => navigate({ category_id: value === 'all' ? '' : value, page: 1 })}>
                                        <SelectTrigger aria-label={t('Filter by category')} className="bg-background"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('Any category')}</SelectItem>
                                            {categories.map((category) => <SelectItem key={category.id} value={category.id.toString()}>{category.name}</SelectItem>)}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {supportSide && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">{t('Company')}</span>
                                        <Select value={filters.company_id || 'all'} onValueChange={(value) => navigate({ company_id: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by company')} className="bg-background"><SelectValue /></SelectTrigger>
                                            <SelectContent searchable>
                                                <SelectItem value="all">{t('Any company')}</SelectItem>
                                                {companies.map((company) => <SelectItem key={company.id} value={company.id.toString()}>{company.name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                {supportSide && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">{t('Agent')}</span>
                                        <Select value={filters.agent_id || 'all'} onValueChange={(value) => navigate({ agent_id: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by agent')} className="bg-background"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">{t('Any agent')}</SelectItem>
                                                <SelectItem value="unassigned">{t('Unassigned')}</SelectItem>
                                                {agents.map((agent) => <SelectItem key={agent.id} value={agent.id.toString()}>{agent.name}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="space-y-1.5">
                                    <label htmlFor="records-from" className="text-xs font-medium text-muted-foreground">{t('Created from')}</label>
                                    <Input id="records-from" type="date" value={filters.date_from || ''} onChange={(e) => navigate({ date_from: e.target.value, page: 1 })} className="bg-background" />
                                </div>
                                <div className="space-y-1.5">
                                    <label htmlFor="records-to" className="text-xs font-medium text-muted-foreground">{t('Created to')}</label>
                                    <Input id="records-to" type="date" value={filters.date_to || ''} onChange={(e) => navigate({ date_to: e.target.value, page: 1 })} className="bg-background" />
                                </div>
                                <Button variant="ghost" className="self-end text-muted-foreground" onClick={clearAdvanced} disabled={!activeFilterCount}>{t('Clear filters')}</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="relative z-0 overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <div className={`hidden gap-4 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 xl:grid ${rowColumns}`}>
                        <span>{t('Ticket')}</span>
                        <span>{t('Customer and company')}</span>
                        <span>{t('Agent')}</span>
                        <span>{t('Priority')}</span>
                        <span>{t('Status')}</span>
                        <span>{t('Dates')}</span>
                    </div>

                    <div className="divide-y">
                        {records.data.map((record) => (
                            <div
                                key={record.id}
                                role="button"
                                tabIndex={0}
                                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); router.get(route('helpdesk-tickets.show', record.id)); } }}
                                onClick={() => router.get(route('helpdesk-tickets.show', record.id))}
                                className={`group grid cursor-pointer gap-3 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary xl:items-center xl:gap-4 xl:px-6 xl:py-4 ${rowColumns}`}
                            >
                                <div className="flex min-w-0 items-center gap-3.5">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-blue-700"><Ticket className="h-5 w-5" /></span>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-slate-900 group-hover:text-primary" title={record.title}>{record.title}</p>
                                        <p className="mt-0.5 truncate text-xs text-slate-500">#{record.ticket_id}{record.category ? ` · ${record.category.name}` : ''}</p>
                                    </div>
                                </div>

                                <div className="min-w-0 text-sm">
                                    <p className="truncate font-medium text-slate-700">{record.requester?.name || t('Not recorded')}</p>
                                    <p className="mt-0.5 truncate text-xs text-slate-500">{record.creator?.name || '-'}</p>
                                </div>

                                <div className="flex justify-between gap-3 text-sm xl:block">
                                    <span className="text-slate-500 xl:hidden">{t('Agent')}</span>
                                    <span className={`truncate ${record.assigned_agent ? 'text-slate-700' : 'text-slate-400'}`}>{record.assigned_agent?.name || t('Unassigned')}</span>
                                </div>

                                <div className="flex justify-between gap-3 text-sm xl:block">
                                    <span className="text-slate-500 xl:hidden">{t('Priority')}</span>
                                    <span className={`font-medium ${priorityTone[record.priority] ?? 'text-slate-600'}`}>{t(label(record.priority))}</span>
                                </div>

                                <div className="flex items-center justify-between gap-3 text-sm xl:block">
                                    <span className="text-slate-500 xl:hidden">{t('Status')}</span>
                                    <span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-medium ${statusTone[record.status] ?? statusTone.closed}`}>{t(label(record.status))}</span>
                                </div>

                                <div className="flex justify-between gap-3 text-sm xl:block">
                                    <span className="text-slate-500 xl:hidden">{t('Dates')}</span>
                                    <div className="text-right xl:text-left">
                                        <p className="text-slate-700">{formatDate(record.created_at)}</p>
                                        <p className="mt-0.5 text-xs text-slate-500">{record.resolved_at ? `${t('Resolved')} ${formatDate(record.resolved_at)}` : t('Not resolved')}</p>
                                    </div>
                                </div>
                            </div>
                        ))}

                        {!records.data.length && (
                            <NoRecordsFound icon={Ticket} title={t('No tickets found')} description={t('Tickets appear here once they are raised.')} hasFilters={hasFilters} onClearFilters={clearAll} className="h-auto" />
                        )}
                    </div>

                    {records.total > 0 && (
                        <div className="border-t bg-slate-50/30 px-3">
                            <Pagination data={records} onPageChange={(page) => navigate({ page })} />
                        </div>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

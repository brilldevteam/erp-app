import { useEffect, useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ArrowDownAZ, ArrowUpAZ, ChevronDown, Edit, Filter, History, Key, LoaderCircle, Mail, MoreHorizontal, PackageOpen, Plus, Search, ShieldCheck, ShieldOff, Trash2, UserCheck, Users as UsersIcon, X } from 'lucide-react';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { usePageButtons } from '@/hooks/usePageButtons';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { getImagePath } from '@/utils/helpers';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Pagination } from '@/components/ui/pagination';
import Create from './create';
import EditUser from './edit';
import ChangePassword from './change-password';
import ChangePlan from './change-plan';
import NoRecordsFound from '@/components/no-records-found';
import { User, UsersIndexProps, UserModalState } from './types';

const sorts = [['created_at', 'Created date'], ['name', 'Name'], ['email', 'Email'], ['type', 'Role'], ['is_enable_login', 'Login status']];

export default function Index() {
    const { t } = useTranslation();
    const pageProps = usePage().props as unknown as UsersIndexProps & {
        summary: { total: number; enabled: number; disabled: number };
        filters: Record<string, string>;
    };
    const { users, roles, createRoles, plans, auth, summary, filters = {} } = pageProps;

    const [search, setSearch] = useState(filters.search || '');
    const [isSearching, setIsSearching] = useState(false);
    const [showAdvanced, setShowAdvanced] = useState(Boolean(filters.role || filters.is_enable_login));
    const [modalState, setModalState] = useState<UserModalState>({ isOpen: false, mode: '', data: null });

    useFlashMessages();

    const pageButtons = usePageButtons('userBtn', 'Test data');

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'users.destroy',
        defaultMessage: t('Are you sure you want to delete this user?')
    });

    const can = (permission: string) => Boolean(auth.user?.permissions?.includes(permission));
    // A super admin's list holds companies, so the staff, client and vendor tabs would always be empty.
    const views = (auth.user as any)?.type === 'superadmin'
        ? [['all', 'All users'], ['login_disabled', 'Login disabled']]
        : [['all', 'All users'], ['staff', 'Staff'], ['client', 'Clients'], ['vendor', 'Vendors'], ['login_disabled', 'Login disabled']];
    const activeView = filters.view || 'all';
    const activeFilterCount = [filters.role, filters.is_enable_login].filter(Boolean).length;
    const hasFilters = Boolean(filters.search || filters.name || filters.email || activeFilterCount || activeView !== 'all');

    const navigate = (extra: Record<string, string | number>) => router.get(route('users.index'), { ...filters, ...extra }, { preserveState: true, replace: true });
    const clearAdvanced = () => navigate({ role: '', is_enable_login: '', page: 1 });
    const clearAll = () => {
        setSearch('');
        router.get(route('users.index'), { per_page: filters.per_page || '' }, { preserveState: true, replace: true });
    };

    useEffect(() => {
        const normalizedSearch = search.trim();
        if (normalizedSearch === (filters.search || '')) return;

        setIsSearching(true);
        const timeout = window.setTimeout(() => {
            router.get(route('users.index'), { ...filters, search: normalizedSearch, page: 1 }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users', 'filters'],
                onFinish: () => setIsSearching(false),
            });
        }, normalizedSearch ? 300 : 0);

        return () => window.clearTimeout(timeout);
    }, [search, filters.search]);

    const openModal = (mode: 'add' | 'edit' | 'change-password' | 'change-plan', data: User | null = null) => {
        setModalState({ isOpen: true, mode, data });
    };

    const closeModal = () => {
        setModalState({ isOpen: false, mode: '', data: null });
    };

    const editUserOrParty = (user: User) => {
        if (user.linked_party) {
            const routeName = user.linked_party.type === 'customer' ? 'account.customers.index' : 'account.vendors.index';
            // The customer and vendor pages send you back here when their edit popup closes.
            router.get(route(routeName), { edit: user.linked_party.id, back: 'users', back_query: window.location.search.replace(/^\?/, '') });
            return;
        }
        openModal('edit', user);
    };

    const sendPasswordReset = (user: User) => {
        if (confirm(t('Send a password reset link to this user?'))) {
            router.post(route('users.send-password-reset', user.id), {}, {
                preserveScroll: true,
            });
        }
    };

    const isPlaceholderEmail = (email?: string | null) => {
        const value = (email ?? '').toLowerCase();

        return value === '' || value.endsWith('@import.local') || value.startsWith('zoho.customer.');
    };

    const hasUsableLoginEmail = (user: User) => !isPlaceholderEmail(user.email);
    const displayRole = (user: User) => user.display_role || user.type;
    const openUser = (user: User) => {
        if (can('edit-users')) editUserOrParty(user);
    };

    const summaryCards = [
        { label: t('Total users'), value: summary.total, icon: UsersIcon, tone: 'bg-blue-50 text-blue-600', valueTone: '' },
        { label: t('Login enabled'), value: summary.enabled, icon: ShieldCheck, tone: 'bg-green-50 text-green-600', valueTone: '' },
        { label: t('Login disabled'), value: summary.disabled, icon: ShieldOff, tone: 'bg-rose-50 text-rose-600', valueTone: '' },
    ];

    const rowColumns = 'xl:grid-cols-[minmax(240px,1.3fr)_minmax(200px,1.1fr)_150px_140px_96px]';

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Users') }]}
            pageTitle={t('Users')}
            pageActions={
                <TooltipProvider>
                    <div className="flex gap-2">
                        {can('view-login-history') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="outline" size="sm" aria-label={t('User Login History')} onClick={() => router.get(route('users.login-history'))}>
                                        <History className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('User Login History')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {can('create-users') && (
                            <Button size="sm" onClick={() => openModal('add')}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('New user')}
                            </Button>
                        )}
                        {pageButtons.map((button) => (
                            <div key={button.id}>{button.component}</div>
                        ))}
                    </div>
                </TooltipProvider>
            }
        >
            <Head title={t('Users')} />

            <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label={t('User summary')}>
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
                                    placeholder={t('Search users by name, email or mobile')}
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
                                    <span className="sr-only">{t('Sort users')}</span>
                                    <select value={filters.sort || 'created_at'} onChange={e => navigate({ sort: e.target.value, page: 1 })} className="h-10 w-full appearance-none rounded-lg border border-slate-200 bg-background pl-3 pr-9 text-sm sm:w-44">
                                        {sorts.map(([value, label]) => <option key={value} value={value}>{`${t('Sort')}: ${t(label)}`}</option>)}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-muted-foreground" />
                                </label>
                                <Button variant="outline" size="icon" className="h-10 w-10 rounded-lg" title={filters.direction === 'asc' ? t('Ascending') : t('Descending')} onClick={() => navigate({ direction: filters.direction === 'asc' ? 'desc' : 'asc', page: 1 })}>
                                    {filters.direction === 'asc' ? <ArrowUpAZ className="h-4 w-4" /> : <ArrowDownAZ className="h-4 w-4" />}
                                </Button>
                                <PerPageSelector routeName="users.index" filters={filters} className="h-10 w-36" />
                                <Button variant={showAdvanced || activeFilterCount ? 'secondary' : 'outline'} className="h-10 rounded-lg" onClick={() => setShowAdvanced(value => !value)}>
                                    <Filter className="mr-2 h-4 w-4" />
                                    {t('Filters')}
                                    {activeFilterCount > 0 && <span className="ml-2 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-primary-foreground">{activeFilterCount}</span>}
                                </Button>
                            </div>
                        </div>

                        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('User views')}>
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
                            <div className="grid gap-3 border-t bg-slate-50/30 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_1fr_auto]">
                                {can('manage-roles') && (
                                    <div className="space-y-1.5">
                                        <span className="text-xs font-medium text-muted-foreground">{t('Role')}</span>
                                        <Select value={filters.role || 'all'} onValueChange={value => navigate({ role: value === 'all' ? '' : value, page: 1 })}>
                                            <SelectTrigger aria-label={t('Filter by role')} className="bg-background"><SelectValue placeholder={t('Any role')} /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">{t('Any role')}</SelectItem>
                                                {Object.entries(roles).map(([name, label]) => <SelectItem key={name} value={name}>{label as string}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="space-y-1.5">
                                    <span className="text-xs font-medium text-muted-foreground">{t('Login status')}</span>
                                    <Select value={filters.is_enable_login || 'all'} onValueChange={value => navigate({ is_enable_login: value === 'all' ? '' : value, page: 1 })}>
                                        <SelectTrigger aria-label={t('Filter by login status')} className="bg-background"><SelectValue placeholder={t('Any login status')} /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('Any login status')}</SelectItem>
                                            <SelectItem value="1">{t('Enabled')}</SelectItem>
                                            <SelectItem value="0">{t('Disabled')}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button variant="ghost" className="self-end text-muted-foreground" onClick={clearAdvanced} disabled={!activeFilterCount}>{t('Clear filters')}</Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="relative z-0 overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <div className={`hidden gap-4 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 xl:grid ${rowColumns}`}>
                        <span>{t('User')}</span>
                        <span>{t('Contact')}</span>
                        <span>{t('Role')}</span>
                        <span>{t('Login')}</span>
                        <span />
                    </div>

                    <div className="divide-y">
                        {users.data.map((user) => {
                            const canImpersonate = can('impersonate-users') && user.id !== auth.user?.id && user.is_enable_login && hasUsableLoginEmail(user);
                            const canManagePassword = can('change-password-users') && !user.linked_party && hasUsableLoginEmail(user);
                            const canChangePlan = can('edit-users') && user.type === 'company' && plans.length > 0;
                            const canDelete = can('delete-users') && !user.linked_party;
                            const hasMenu = canImpersonate || canManagePassword || canChangePlan || canDelete;
                            const email = hasUsableLoginEmail(user) ? user.email : null;

                            return (
                                <div
                                    key={user.id}
                                    role="button"
                                    tabIndex={0}
                                    onKeyDown={e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openUser(user); } }}
                                    onClick={() => openUser(user)}
                                    className={`group grid cursor-pointer gap-4 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary xl:items-center xl:px-6 xl:py-4 ${rowColumns}`}
                                >
                                    <div className="flex min-w-0 items-center gap-3.5">
                                        <span className="relative h-11 w-11 shrink-0">
                                            {user.avatar ? (
                                                <img src={getImagePath(user.avatar)} alt={user.name} className="h-11 w-11 rounded-xl border border-blue-100 object-cover" />
                                            ) : (
                                                <span className="flex h-11 w-11 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-sm font-semibold text-blue-700">{user.name?.charAt(0).toUpperCase()}</span>
                                            )}
                                            <span className={`absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-white ${user.is_online ? 'bg-green-500' : 'bg-gray-400'}`} title={user.is_online ? t('Online') : t('Offline')} />
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-slate-900 group-hover:text-primary" title={user.name}>{user.name}</p>
                                            <p className="mt-0.5 truncate text-xs text-slate-500">{user.is_online ? t('Online') : t('Offline')}</p>
                                        </div>
                                    </div>

                                    <div className="min-w-0 text-sm">
                                        <p className="truncate font-medium text-slate-700" title={email ?? undefined}>{email || t('No email')}</p>
                                        <p className="mt-0.5 truncate text-xs text-slate-500">{user.mobile_no || t('No mobile')}</p>
                                    </div>

                                    <div className="flex items-center justify-between gap-3 text-sm xl:block">
                                        <span className="text-slate-500 xl:hidden">{t('Role')}</span>
                                        <span className="inline-flex max-w-full items-center truncate rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-medium capitalize text-blue-700">{displayRole(user)}</span>
                                    </div>

                                    <div className="flex items-center justify-between gap-3 text-sm xl:block">
                                        <span className="text-slate-500 xl:hidden">{t('Login')}</span>
                                        <span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${user.is_enable_login ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'}`}>
                                            <span className={`h-1.5 w-1.5 rounded-full ${user.is_enable_login ? 'bg-emerald-500' : 'bg-rose-500'}`} />
                                            {user.is_enable_login ? t('Enabled') : t('Disabled')}
                                        </span>
                                    </div>

                                    <div className="flex justify-end gap-0.5" onClick={e => e.stopPropagation()} onKeyDown={e => e.stopPropagation()}>
                                        {can('edit-users') && (
                                            <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('Edit')} aria-label={t('Edit')} onClick={() => editUserOrParty(user)}>
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                        )}
                                        {hasMenu && (
                                            <DropdownMenu modal={false}>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8 rounded-lg" title={t('More actions')} aria-label={t('More actions')}>
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end" className="min-w-[210px]">
                                                    {canImpersonate && <DropdownMenuItem onClick={() => router.post(route('users.impersonate', user.id))}><UserCheck className="mr-2 h-4 w-4" />{t('Login As User')}</DropdownMenuItem>}
                                                    {canManagePassword && <DropdownMenuItem onClick={() => openModal('change-password', user)}><Key className="mr-2 h-4 w-4" />{t('Change Password')}</DropdownMenuItem>}
                                                    {canManagePassword && <DropdownMenuItem onClick={() => sendPasswordReset(user)}><Mail className="mr-2 h-4 w-4" />{t('Send Password Reset Link')}</DropdownMenuItem>}
                                                    {canChangePlan && <DropdownMenuItem onClick={() => openModal('change-plan', user)}><PackageOpen className="mr-2 h-4 w-4" />{t('Change Plan')}</DropdownMenuItem>}
                                                    {canDelete && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem className="text-rose-600 focus:text-rose-600" onClick={() => openDeleteDialog(user.id)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </div>
                                </div>
                            );
                        })}

                        {!users.data.length && (
                            <NoRecordsFound
                                icon={UsersIcon}
                                title={t('No users found')}
                                description={t('Get started by creating your first user.')}
                                hasFilters={hasFilters}
                                onClearFilters={clearAll}
                                createPermission="create-users"
                                onCreateClick={() => openModal('add')}
                                createButtonText={t('Create User')}
                                className="h-auto"
                            />
                        )}
                    </div>

                    {users.total > 0 && (
                        <div className="border-t bg-slate-50/30 px-3">
                            <Pagination data={users} onPageChange={page => navigate({ page })} />
                        </div>
                    )}
                </Card>
            </div>

            <Dialog open={modalState.isOpen} onOpenChange={closeModal}>
                {modalState.mode === 'add' && (
                    <Create onSuccess={closeModal} roles={createRoles} />
                )}
                {modalState.mode === 'edit' && modalState.data && (
                    <EditUser
                        user={modalState.data}
                        onSuccess={closeModal}
                        roles={roles}
                        plans={plans}
                    />
                )}
                {modalState.mode === 'change-password' && modalState.data && (
                    <ChangePassword
                        user={modalState.data}
                        onSuccess={closeModal}
                    />
                )}
                {modalState.mode === 'change-plan' && modalState.data && (
                    <ChangePlan
                        user={modalState.data}
                        plans={plans}
                        onSuccess={closeModal}
                    />
                )}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete User')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

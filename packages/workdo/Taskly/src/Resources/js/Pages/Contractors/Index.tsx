import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/ui/pagination';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Edit, HardHat, Plus, Search, Trash2 } from 'lucide-react';
import { formatCurrency, formatDate } from '@/utils/helpers';
import ContractorForm from './ContractorForm';
import { ContractorOption, ContractorType, ProjectContract } from './types';

interface PageProps {
    contracts: {
        data: ProjectContract[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    projects: Array<{ id: number; name: string }>;
    vendors: Array<{ id: number; company_name: string; vendor_code: string }>;
    mainContracts: ContractorOption[];
    filters: Record<string, string>;
    auth: { user: { permissions: string[] } };
}

export default function Index() {
    const { t } = useTranslation();
    const { contracts, projects, vendors, mainContracts, filters, auth } = usePage<PageProps>().props;
    const activeType = (filters.type || 'main') as ContractorType;
    const [search, setSearch] = useState(filters.search || '');
    const [projectId, setProjectId] = useState(filters.project_id || 'all');
    const [modal, setModal] = useState<{ open: boolean; contract: ProjectContract | null }>({ open: false, contract: null });
    const [deleteId, setDeleteId] = useState<number | null>(null);
    useFlashMessages();

    const query = useMemo(() => ({
        type: activeType,
        search,
        project_id: projectId === 'all' ? '' : projectId,
        per_page: filters.per_page || 10,
    }), [activeType, search, projectId, filters.per_page]);

    const navigate = (overrides: Record<string, string | number> = {}) => {
        router.get(route('project.contractors.index'), { ...query, ...overrides }, { preserveState: true, replace: true });
    };

    const balanceClass = (value: number) => value < 0 ? 'font-semibold text-destructive' : 'font-medium';

    const Actions = ({ contract }: { contract: ProjectContract }) => (
        <div className="flex justify-end gap-1">
            {auth.user.permissions.includes('edit-project-contractors') && (
                <Button variant="ghost" size="icon" onClick={() => setModal({ open: true, contract })} aria-label={t('Edit')}>
                    <Edit className="h-4 w-4 text-blue-600" />
                </Button>
            )}
            {auth.user.permissions.includes('delete-project-contractors') && (
                <Button variant="ghost" size="icon" onClick={() => setDeleteId(contract.id)} aria-label={t('Delete')}>
                    <Trash2 className="h-4 w-4 text-destructive" />
                </Button>
            )}
        </div>
    );

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Project'), url: route('project.index') }, { label: t('Contractors') }]}
            pageTitle={t('Manage Contractors')}
            pageActions={auth.user.permissions.includes('create-project-contractors') ? (
                <Button size="sm" onClick={() => setModal({ open: true, contract: null })}><Plus className="mr-1 h-4 w-4" />{t('Add Contractor')}</Button>
            ) : undefined}
        >
            <Head title={t('Contractors')} />
            <Card>
                <CardHeader className="space-y-4">
                    <div className="flex w-full border-b" role="tablist">
                        {(['main', 'subcontractor'] as ContractorType[]).map((type) => (
                            <button
                                key={type}
                                className={`border-b-2 px-4 py-3 text-sm font-medium ${activeType === type ? 'border-primary text-primary' : 'border-transparent text-muted-foreground'}`}
                                onClick={() => navigate({ type, page: 1 })}
                            >
                                {type === 'main' ? t('Main Contractors') : t('Subcontractors')}
                            </button>
                        ))}
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input className="pl-9" value={search} onChange={(event) => setSearch(event.target.value)} onKeyDown={(event) => event.key === 'Enter' && navigate({ page: 1 })} placeholder={t('Search contractor, project, or scope')} />
                        </div>
                        <Select value={projectId} onValueChange={(value) => { setProjectId(value); router.get(route('project.contractors.index'), { ...query, project_id: value === 'all' ? '' : value, page: 1 }, { preserveState: true, replace: true }); }}>
                            <SelectTrigger className="w-full sm:w-64"><SelectValue placeholder={t('Filter by project')} /></SelectTrigger>
                            <SelectContent searchable>
                                <SelectItem value="all">{t('All Projects')}</SelectItem>
                                {projects.map((project) => <SelectItem key={project.id} value={project.id.toString()}>{project.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" onClick={() => navigate({ page: 1 })}>{t('Search')}</Button>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {contracts.data.length ? (
                        <>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full text-sm">
                                    <thead className="border-y bg-muted/50 text-left">
                                        <tr>
                                            {[t('Project'), t('Contractor Name'), t('Scope of Work'), t('Contract Value'), t('Amount Paid'), t('Remaining Balance'), t('Work Start Date'), t('Completion Date'), t('Actions')].map((heading) => <th key={heading} className="whitespace-nowrap px-4 py-3 font-medium">{heading}</th>)}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {contracts.data.map((contract) => (
                                            <tr key={contract.id} className="border-b last:border-0">
                                                <td className="px-4 py-3">{contract.project.name}</td>
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">{contract.vendor.company_name}</p>
                                                    {contract.parent_contract?.vendor && <p className="text-xs text-muted-foreground">{t('Under')} {contract.parent_contract.vendor.company_name}</p>}
                                                </td>
                                                <td className="max-w-56 px-4 py-3"><span className="line-clamp-2">{contract.scope_of_work}</span></td>
                                                <td className="whitespace-nowrap px-4 py-3">{formatCurrency(contract.contract_value)}</td>
                                                <td className="whitespace-nowrap px-4 py-3">{formatCurrency(contract.amount_paid)}</td>
                                                <td className={`whitespace-nowrap px-4 py-3 ${balanceClass(contract.remaining_balance)}`}>{formatCurrency(contract.remaining_balance)}</td>
                                                <td className="whitespace-nowrap px-4 py-3">{formatDate(contract.work_start_date)}</td>
                                                <td className="whitespace-nowrap px-4 py-3">{formatDate(contract.completion_date)}</td>
                                                <td className="px-4 py-3"><Actions contract={contract} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="grid gap-3 p-3 md:hidden">
                                {contracts.data.map((contract) => (
                                    <Card key={contract.id} className="overflow-hidden">
                                        <CardContent className="space-y-3 p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div><p className="font-semibold">{contract.vendor.company_name}</p><p className="text-sm text-muted-foreground">{contract.project.name}</p>{contract.parent_contract?.vendor && <p className="text-xs text-muted-foreground">{t('Under')} {contract.parent_contract.vendor.company_name}</p>}</div>
                                                <Actions contract={contract} />
                                            </div>
                                            <p className="text-sm">{contract.scope_of_work}</p>
                                            <div className="grid grid-cols-2 gap-3 text-sm">
                                                <div><p className="text-muted-foreground">{t('Contract Value')}</p><p>{formatCurrency(contract.contract_value)}</p></div>
                                                <div><p className="text-muted-foreground">{t('Amount Paid')}</p><p>{formatCurrency(contract.amount_paid)}</p></div>
                                                <div><p className="text-muted-foreground">{t('Remaining Balance')}</p><p className={balanceClass(contract.remaining_balance)}>{formatCurrency(contract.remaining_balance)}</p></div>
                                                <div><p className="text-muted-foreground">{t('Dates')}</p><p>{formatDate(contract.work_start_date)} – {formatDate(contract.completion_date)}</p></div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </>
                    ) : (
                        <div className="flex flex-col items-center gap-2 px-4 py-16 text-center"><HardHat className="h-10 w-10 text-muted-foreground" /><h3 className="font-semibold">{t('No contractors found')}</h3><p className="text-sm text-muted-foreground">{t('Add a contractor to start tracking project contracts and payments.')}</p></div>
                    )}
                    <div className="border-t px-3"><Pagination data={contracts} routeName="project.contractors.index" filters={query} /></div>
                </CardContent>
            </Card>

            <Dialog open={modal.open} onOpenChange={(open) => setModal((current) => ({ ...current, open }))}>
                {modal.open && <ContractorForm contract={modal.contract} defaultType={activeType} projects={projects} vendors={vendors} mainContracts={mainContracts} onSuccess={() => setModal({ open: false, contract: null })} />}
            </Dialog>
            <ConfirmationDialog
                open={deleteId !== null}
                onOpenChange={(open) => !open && setDeleteId(null)}
                title={t('Delete Project Contractor')}
                message={t('Are you sure you want to delete this project contractor?')}
                variant="destructive"
                onConfirm={() => deleteId && router.delete(route('project.contractors.destroy', deleteId), { preserveScroll: true, onFinish: () => setDeleteId(null) })}
            />
        </AuthenticatedLayout>
    );
}

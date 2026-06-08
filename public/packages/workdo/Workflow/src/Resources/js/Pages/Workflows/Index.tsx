import React, { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Plus, Edit as EditIcon, Trash2, Eye, Workflow as WorkflowIcon } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { formatDate } from '@/utils/helpers';
import NoRecordsFound from '@/components/no-records-found';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Workflow {
    id: number;
    name: string;
    module: string;
    submodule: string;
    is_active: boolean;
    conditions_count: number;
    actions_count: number;
    created_at: string;
}

interface WorkflowIndexProps {
    workflows: {
        data: Workflow[];
        links: any[];
        meta: any;
    };
    modules: Array<{module: string; module_alias: string; submodule: string}>;
    auth: any;
    [key: string]: any;
}

export default function Index() {
    const { t } = useTranslation();
    const { workflows, modules, auth } = usePage<WorkflowIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState({
        search: urlParams.get('search') || '',
        module: urlParams.get('module') || 'all',
        submodule: urlParams.get('submodule') || 'all',
        status: urlParams.get('status') || 'all',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [showFilters, setShowFilters] = useState(false);
    const [availableSubmodules, setAvailableSubmodules] = useState<string[]>([]);

    const uniqueModules = [...new Set(modules.map(m => m.module))];

    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'workflow.destroy',
        defaultMessage: t('Are you sure you want to delete this workflow?')
    });

    const handleModuleChange = (moduleValue: string) => {
        setFilters({...filters, module: moduleValue, submodule: 'all'});
        if (moduleValue === 'all') {
            setAvailableSubmodules([]);
        } else {
            const moduleSubmodules = modules
                .filter(m => m.module === moduleValue)
                .map(m => m.submodule);
            setAvailableSubmodules(moduleSubmodules);
        }
    };

    const handleFilter = () => {
        router.get(route('workflow.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('workflow.index'), {...filters, per_page: perPage, sort: field, direction}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({ search: '', module: 'all', submodule: 'all', status: 'all' });
        router.get(route('workflow.index'), {per_page: perPage});
    };

    const getStatusBadgeClasses = (isActive: boolean) => {
        return isActive
            ? 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800'
            : 'inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800';
    };

    const tableColumns = [
        {
            key: 'name',
            header: t('Workflow Name'),
            sortable: true,
            render: (value: string, workflow: Workflow) => value
        },
        {
            key: 'module',
            header: t('Module'),
            sortable: true,
            render: (value: string, workflow: Workflow) => {
                const moduleData = modules.find(m => m.module === value);
                return moduleData?.module_alias || value;
            }
        },
        {
            key: 'submodule',
            header: t('Sub Module'),
            sortable: true,
            render: (value: string) => value
        },
        {
            key: 'conditions_count',
            header: t('Conditions'),
            render: (value: number) => value || 0
        },
        {
            key: 'actions_count',
            header: t('Actions'),
            render: (value: number) => value || 0
        },
        {
            key: 'created_at',
            header: t('Created'),
            render: (value: string) => formatDate(value)
        },
        {
            key: 'is_active',
            header: t('Status'),
            render: (value: boolean) => (
                <span className={getStatusBadgeClasses(value)}>
                    {value ? t('Active') : t('Inactive')}
                </span>
            )
        },
        {
            key: 'actions',
            header: t('Actions'),
            render: (_: any, workflow: Workflow) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => router.get(route('workflow.edit', workflow.id))}
                                    className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
                                >
                                    <EditIcon className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('Edit')}</p>
                            </TooltipContent>
                        </Tooltip>
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => openDeleteDialog(workflow.id)}
                                    className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('Delete')}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[{label: t('Workflows')}]}
            pageTitle={t('Manage Workflows')}
            pageActions={
                <div className="flex gap-2">
                    <TooltipProvider>
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button size="sm" onClick={() => router.visit(route('workflow.create'))}>
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('Create')}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            }
        >
            <Head title={t('Workflows')} />

            <Card className="shadow-sm">
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.search || ''}
                                onChange={(value) => setFilters({...filters, search: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search workflows...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <PerPageSelector
                                routeName="workflow.index"
                                filters={filters}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [
                                        filters.module !== 'all' ? filters.module : '',
                                        filters.submodule !== 'all' ? filters.submodule : '',
                                        filters.status !== 'all' ? filters.status : ''
                                    ].filter(Boolean).length;
                                    return activeFilters > 0 && (
                                        <span className="absolute -top-2 -right-2 bg-primary text-primary-foreground text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
                                            {activeFilters}
                                        </span>
                                    );
                                })()}
                            </div>
                        </div>
                    </div>
                </CardContent>

                {showFilters && (
                    <CardContent className="p-6 bg-blue-50/30 border-b">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Module')}</label>
                                <Select value={filters.module} onValueChange={handleModuleChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('All Modules')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Modules')}</SelectItem>
                                        {uniqueModules?.map((module) => {
                                            const moduleData = modules.find(m => m.module === module);
                                            return (
                                                <SelectItem key={module} value={module}>
                                                    {moduleData?.module_alias || t(module)}
                                                </SelectItem>
                                            );
                                        })}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Sub Module')}</label>
                                <Select
                                    value={filters.submodule}
                                    onValueChange={(value) => setFilters({...filters, submodule: value})}
                                    disabled={filters.module === 'all'}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('All Sub Modules')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Sub Modules')}</SelectItem>
                                        {availableSubmodules?.map((submodule) => (
                                            <SelectItem key={submodule} value={submodule}>{t(submodule)}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Status')}</label>
                                <Select value={filters.status} onValueChange={(value) => setFilters({...filters, status: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('All Status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Status')}</SelectItem>
                                        <SelectItem value="active">{t('Active')}</SelectItem>
                                        <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={handleFilter} size="sm">{t('Apply')}</Button>
                                <Button variant="outline" onClick={clearFilters} size="sm">{t('Clear')}</Button>
                            </div>
                        </div>
                    </CardContent>
                )}

                <CardContent className="p-0">
                    <div className="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100 max-h-[70vh] rounded-none w-full">
                        <div className="min-w-[800px]">
                            <DataTable
                                data={workflows.data}
                                columns={tableColumns}
                                onSort={handleSort}
                                sortKey={sortField}
                                sortDirection={sortDirection as 'asc' | 'desc'}
                                className="rounded-none"
                                emptyState={
                                    <NoRecordsFound
                                        icon={WorkflowIcon}
                                        title={t('No workflows found')}
                                        description={t('Get started by creating your first workflow.')}
                                        hasFilters={!!(filters.search || (filters.module !== 'all' && filters.module) || (filters.submodule !== 'all' && filters.submodule) || (filters.status !== 'all' && filters.status))}
                                        onClearFilters={clearFilters}
                                        createPermission="create-workflow"
                                        onCreateClick={() => router.visit(route('workflow.create'))}
                                        createButtonText={t('Create Workflow')}
                                        className="h-auto"
                                    />
                                }
                            />
                        </div>
                    </div>
                </CardContent>

                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={{...workflows, ...workflows.meta}}
                        routeName="workflow.index"
                        filters={{...filters, per_page: perPage}}
                    />
                </CardContent>
            </Card>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Workflow')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Plus, Edit as EditIcon, Trash2, Tag as TagIcon, RefreshCw } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Pagination } from "@/components/ui/pagination";
import Create from './Create';
import EditPettyCashCategorie from './Edit';
import NoRecordsFound from '@/components/no-records-found';
import { PettyCashCategorie, PettyCashCategoriesIndexProps, PettyCashCategorieFilters, PettyCashCategorieModalState } from './types';

export default function Index() {
    const { t } = useTranslation();
    const { pettycashcategories, auth } = usePage<PettyCashCategoriesIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState<PettyCashCategorieFilters>({
        name: urlParams.get('name') || '',
    });

    const [perPage, setPerPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');


    const [modalState, setModalState] = useState<PettyCashCategorieModalState>({
        isOpen: false,
        mode: '',
        data: null
    });

    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'petty-cash-management.petty-cash-categories.destroy',
        defaultMessage: t('Are you sure you want to delete this category?')
    });

    const handleFilter = () => {
        router.get(route('petty-cash-management.petty-cash-categories.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handlePerPageChange = (newPerPage: string) => {
        setPerPage(newPerPage);
        router.get(route('petty-cash-management.petty-cash-categories.index'), {...filters, per_page: newPerPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('petty-cash-management.petty-cash-categories.index'), {...filters, per_page: perPage, sort: field, direction, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({
            name: '',
        });
        router.get(route('petty-cash-management.petty-cash-categories.index'), {per_page: perPage, view: viewMode});
    };

    const refreshData = () => {
        router.get(route('petty-cash-management.petty-cash-categories.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const openModal = (mode: 'add' | 'edit', data: PettyCashCategorie | null = null) => {
        setModalState({ isOpen: true, mode, data });
    };

    const closeModal = () => {
        setModalState({ isOpen: false, mode: '', data: null });
    };

    const handleModalSuccess = () => {
        closeModal();
        refreshData();
    };

    const tableColumns = [
        {
            key: 'name',
            header: t('Name'),
            sortable: false
        },
        ...(auth.user?.permissions?.some((p: string) => ['edit-petty-cash-categories', 'delete-petty-cash-categories'].includes(p)) ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, pettycashcategorie: PettyCashCategorie) => (
                <div className="flex gap-1">
                    <TooltipProvider>

                        {auth.user?.permissions?.includes('edit-petty-cash-categories') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="sm" onClick={() => openModal('edit', pettycashcategorie)} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                        <EditIcon className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Edit')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('delete-petty-cash-categories') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openDeleteDialog(pettycashcategorie.id)}
                                        className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Delete')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                    </TooltipProvider>
                </div>
            )
        }] : [])
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                {label: t('Petty Cash Management')},
                {label: t('Categories')}
            ]}
            pageTitle={t('Manage Categories')}
            pageActions={
                <TooltipProvider>
                    {auth.user?.permissions?.includes('create-petty-cash-categories') && (
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button size="sm" onClick={() => openModal('add')}>
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('Create')}</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </TooltipProvider>
            }
        >
            <Head title={t('Categories')} />

            {/* Main Content Card */}
            <Card className="shadow-sm">
                {/* Search & Controls Header */}
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.name}
                                onChange={(value) => setFilters({...filters, name: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search Categories...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <PerPageSelector
                                routeName="petty-cash-management.petty-cash-categories.index"
                                filters={{...filters, view: viewMode}}
                                currentPerPage={perPage}
                                onPerPageChange={handlePerPageChange}
                            />
                        </div>
                    </div>
                </CardContent>



                {/* Table Content */}
                <CardContent className="p-0">
                    {!pettycashcategories ? (
                        <div className="flex items-center justify-center h-64">
                            <div className="text-center">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                                <p className="text-gray-500">{t('Loading categories...')}</p>
                            </div>
                        </div>
                    ) : viewMode === 'list' ? (
                        <div className="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100 max-h-[70vh] rounded-none w-full">
                            <div className="min-w-[800px]">
                                <DataTable
                                    data={pettycashcategories?.data || []}
                                    columns={tableColumns}
                                    onSort={handleSort}
                                    sortKey={sortField}
                                    sortDirection={sortDirection as 'asc' | 'desc'}
                                    className="rounded-none"
                                    emptyState={
                                        <NoRecordsFound
                                            icon={TagIcon}
                                            title={t('No Categories found')}
                                            description={t('Get started by creating your first Category.')}
                                            hasFilters={!!(filters.name)}
                                            onClearFilters={clearFilters}
                                            createPermission="create-petty-cash-categories"
                                            onCreateClick={() => openModal('add')}
                                            createButtonText={t('Create Category')}
                                            className="h-auto"
                                        />
                                    }
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {pettycashcategories?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-6">
                                    {pettycashcategories?.data?.map((pettycashcategorie) => (
                                        <Card key={pettycashcategorie.id} className="p-6 hover:shadow-md transition-shadow">
                                            <div className="flex items-center justify-between mb-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <TagIcon className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <h3 className="font-semibold text-lg truncate" title={pettycashcategorie.name}>
                                                        {pettycashcategorie.name}
                                                    </h3>
                                                </div>
                                            </div>

                                            <div className="flex justify-end gap-2 pt-4 border-t">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('edit-petty-cash-categories') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => openModal('edit', pettycashcategorie)} className="h-9 w-9 p-0 text-blue-600 hover:text-blue-700 hover:bg-blue-50">
                                                                    <EditIcon className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('Edit')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('delete-petty-cash-categories') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openDeleteDialog(pettycashcategorie.id)}
                                                                    className="h-9 w-9 p-0 text-destructive hover:text-destructive hover:bg-red-50"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('Delete')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                </TooltipProvider>
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <NoRecordsFound
                                    icon={TagIcon}
                                    title={t('No Categories found')}
                                    description={t('Get started by creating your first Category.')}
                                    hasFilters={!!(filters.name)}
                                    onClearFilters={clearFilters}
                                    createPermission="create-petty-cash-categories"
                                    onCreateClick={() => openModal('add')}
                                    createButtonText={t('Create Category')}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                {/* Pagination Footer */}
                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={pettycashcategories || { data: [], links: [], meta: {} }}
                        routeName="petty-cash-management.petty-cash-categories.index"
                        filters={{...filters, per_page: perPage, view: viewMode}}
                    />
                </CardContent>
            </Card>

            <Dialog open={modalState.isOpen} onOpenChange={closeModal}>
                {modalState.mode === 'add' && (
                    <Create onSuccess={handleModalSuccess} />
                )}
                {modalState.mode === 'edit' && modalState.data && (
                    <EditPettyCashCategorie
                        pettycashcategorie={modalState.data}
                        onSuccess={handleModalSuccess}
                    />
                )}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Category')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

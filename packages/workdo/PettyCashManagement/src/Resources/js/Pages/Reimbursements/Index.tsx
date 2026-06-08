import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Plus, Edit as EditIcon, Trash2, Eye, Receipt as ReceiptIcon, Download, FileImage, CheckCircle, XCircle } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import Create from './Create';
import EditReimbursement from './Edit';
import View from './View';
import NoRecordsFound from '@/components/no-records-found';
import { Reimbursement, ReimbursementsIndexProps, ReimbursementFilters, ReimbursementModalState } from './types';
import { formatDate, formatTime, formatDateTime, formatCurrency, getImagePath } from '@/utils/helpers';
import { router as inertiaRouter } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { CurrencyInput } from '@/components/ui/currency-input';

export default function Index() {
    const { t } = useTranslation();
    const { reimbursements, auth, users, categories } = usePage<ReimbursementsIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState<ReimbursementFilters>({
        reimbursement_number: urlParams.get('reimbursement_number') || '',
        user_id: urlParams.get('user_id') || '',
        category_id: urlParams.get('category_id') || '',
        status: urlParams.get('status') || '',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');
    const [modalState, setModalState] = useState<ReimbursementModalState>({
        isOpen: false,
        mode: '',
        data: null
    });
    const [viewingItem, setViewingItem] = useState<Reimbursement | null>(null);
    const [approvalItem, setApprovalItem] = useState<Reimbursement | null>(null);
    const [rejectionItem, setRejectionItem] = useState<Reimbursement | null>(null);
    const [approvedAmount, setApprovedAmount] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');

    const [showFilters, setShowFilters] = useState(false);



    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'petty-cash-management.reimbursements.destroy',
        defaultMessage: t('Are you sure you want to delete this reimbursement?')
    });

    const handleFilter = () => {
        router.get(route('petty-cash-management.reimbursements.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('petty-cash-management.reimbursements.index'), {...filters, per_page: perPage, sort: field, direction, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({
            reimbursement_number: '',
            user_id: '',
            category_id: '',
            status: '',
        });
        router.get(route('petty-cash-management.reimbursements.index'), {per_page: perPage, view: viewMode});
    };

    const openModal = (mode: 'add' | 'edit', data: Reimbursement | null = null) => {
        setModalState({ isOpen: true, mode, data });
    };

    const closeModal = () => {
        setModalState({ isOpen: false, mode: '', data: null });
    };

    const handleApproval = () => {
        if (!approvalItem || !approvedAmount) return;

        const currentDateTime = new Date().toISOString().slice(0, 19).replace('T', ' ');

        inertiaRouter.put(route('petty-cash-management.reimbursements.update-status', approvalItem.id), {
            status: '1',
            approved_date: currentDateTime,
            approved_by: auth.user?.id,
            approved_amount: approvedAmount
        }, {
            onSuccess: () => {
                setApprovalItem(null);
                setApprovedAmount('');
            }
        });
    };

    const handleRejection = () => {
        if (!rejectionItem || !rejectionReason) return;

        inertiaRouter.put(route('petty-cash-management.reimbursements.update-status', rejectionItem.id), {
            status: '2',
            approved_date: null,
            approved_by: null,
            rejection_reason: rejectionReason
        }, {
            onSuccess: () => {
                setRejectionItem(null);
                setRejectionReason('');
            }
        });
    };

    const tableColumns = [
        {
            key: 'reimbursement_number',
            header: t('Reimbursement Number'),
            sortable: false,
            render: (value: string) => value || '-'
        },
        {
            key: 'user.name',
            header: t('User Name'),
            sortable: false,
            render: (value: any, row: any) => row.user?.name || '-'
        },
        {
            key: 'category.name',
            header: t('Category Name'),
            sortable: false,
            render: (value: any, row: any) => row.category?.name || '-'
        },
        {
            key: 'amount',
            header: t('Requested Amount'),
            sortable: false,
            render: (value: number) => value ? formatCurrency(value) : '-'
        },
        {
            key: 'approved_amount',
            header: t('Approved Amount'),
            sortable: false,
            render: (value: number) => value ? formatCurrency(value) : '-'
        },
         {
            key: 'created_at',
            header: t('Request Date'),
            sortable: false,
            render: (value: string) => value ? formatDateTime(value) : '-'
        },
        {
            key: 'status',
            header: t('Status'),
            sortable: false,
            render: (value: string) => {
                const statusMap = {
                    '0': { label: t('Pending'), className: 'bg-yellow-100 text-yellow-800' },
                    '1': { label: t('Approved'), className: 'bg-green-100 text-green-800' },
                    '2': { label: t('Rejected'), className: 'bg-red-100 text-red-800' },
                };
                const statusInfo = statusMap[value as keyof typeof statusMap] || { label: t('Unknown'), className: 'bg-gray-100 text-gray-800' };
                return (
                    <span className={`px-2 py-1 rounded-full text-sm ${statusInfo.className}`}>
                        {statusInfo.label}
                    </span>
                );
            }
        },
        ...(auth.user?.permissions?.some((p: string) => ['view-reimbursements', 'edit-reimbursements', 'delete-reimbursements'].includes(p)) ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, reimbursement: Reimbursement) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        {auth.user?.permissions?.includes('approve-reimbursements') && reimbursement.status === '0' && (
                            <>
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="sm" onClick={() => { setApprovalItem(reimbursement); setApprovedAmount(reimbursement.amount?.toString() || ''); }} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                            <CheckCircle className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Approve')}</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="sm" onClick={() => setRejectionItem(reimbursement)} className="h-8 w-8 p-0 text-red-600 hover:text-red-700">
                                            <XCircle className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Reject')}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </>
                        )}
                        {auth.user?.permissions?.includes('view-reimbursements') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="sm" onClick={() => setViewingItem(reimbursement)} className="h-8 w-8 p-0 text-green-600 hover:text-green-700">
                                        <Eye className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('View')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('edit-reimbursements') && reimbursement.status === '0' && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="sm" onClick={() => openModal('edit', reimbursement)} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                        <EditIcon className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Edit')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('delete-reimbursements') && reimbursement.status === '0' && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openDeleteDialog(reimbursement.id)}
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
                {label: t('Reimbursements')}
            ]}
            pageTitle={t('Manage Reimbursements')}
            pageActions={
                <TooltipProvider>
                    {auth.user?.permissions?.includes('create-reimbursements') && (
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
            <Head title={t('Reimbursements')} />

            {/* Main Content Card */}
            <Card className="shadow-sm">
                {/* Search & Controls Header */}
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.reimbursement_number}
                                onChange={(value) => setFilters({...filters, reimbursement_number: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search by Reimbursement Number...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="petty-cash-management.reimbursements.index"
                                filters={{...filters, per_page: perPage}}
                            />
                            <PerPageSelector
                                routeName="petty-cash-management.reimbursements.index"
                                filters={{...filters, view: viewMode}}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [filters.user_id, filters.category_id, filters.status].filter(f => f !== '' && f !== null && f !== undefined).length;
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

                {/* Advanced Filters */}
                {showFilters && (
                    <CardContent className="p-6 bg-blue-50/30 border-b">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('User')}</label>
                                <Select value={filters.user_id} onValueChange={(value) => setFilters({...filters, user_id: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by User')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {users?.map((item: any) => (
                                            <SelectItem key={item.id} value={item.id.toString()}>
                                                {item.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Category')}</label>
                                <Select value={filters.category_id} onValueChange={(value) => setFilters({...filters, category_id: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories?.map((item: any) => (
                                            <SelectItem key={item.id} value={item.id.toString()}>
                                                {item.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Status')}</label>
                                <Select value={filters.status} onValueChange={(value) => setFilters({...filters, status: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">{t('Pending')}</SelectItem>
                                        <SelectItem value="1">{t('Approved')}</SelectItem>
                                        <SelectItem value="2">{t('Rejected')}</SelectItem>
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

                {/* Table Content */}
                <CardContent className="p-0">
                    {viewMode === 'list' ? (
                        <div className="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100 max-h-[70vh] rounded-none w-full">
                            <div className="min-w-[800px]">
                            <DataTable
                                data={reimbursements?.data || []}
                                columns={tableColumns}
                                onSort={handleSort}
                                sortKey={sortField}
                                sortDirection={sortDirection as 'asc' | 'desc'}
                                className="rounded-none"
                                emptyState={
                                    <NoRecordsFound
                                        icon={ReceiptIcon}
                                        title={t('No Reimbursements found')}
                                        description={t('Get started by creating your first Reimbursement.')}
                                        hasFilters={!!(filters.reimbursement_number || filters.user_id || filters.category_id || filters.status)}
                                        onClearFilters={clearFilters}
                                        createPermission="create-reimbursements"
                                        onCreateClick={() => openModal('add')}
                                        createButtonText={t('Create Reimbursement')}
                                        className="h-auto"
                                    />
                                }
                            />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {reimbursements?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {reimbursements?.data?.map((reimbursement) => (
                                        <Card key={reimbursement.id} className="p-0 hover:shadow-lg transition-all duration-200 relative overflow-hidden flex flex-col h-full min-w-0">
                                            {/* Header */}
                                            <div className="p-4 bg-gradient-to-r from-primary/5 to-transparent border-b flex-shrink-0">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <ReceiptIcon className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="font-semibold text-sm text-gray-900">{reimbursement.reimbursement_number || '-'}</h3>
                                                        <p className="text-xs font-medium text-primary">{reimbursement.user?.name || '-'}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Body */}
                                            <div className="p-4 flex-1 min-h-0">
                                                <div className="grid grid-cols-2 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Category')}</p>
                                                        <p className="font-medium text-xs">{reimbursement.category?.name || '-'}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Requested Amount')}</p>
                                                        <p className="font-medium text-xs">{reimbursement.amount ? formatCurrency(reimbursement.amount) : '-'}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Approved Amount')}</p>
                                                        <p className="font-medium text-xs">{reimbursement.approved_amount ? formatCurrency(reimbursement.approved_amount) : '-'}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Request Date')}</p>
                                                        <p className="font-medium text-xs">{reimbursement.created_at ? formatDate(reimbursement.created_at) : '-'}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Status')}</p>
                                                        <span className={`px-2 py-1 rounded-full text-xs font-medium inline-block ${
                                                            reimbursement.status === '0' ? 'bg-yellow-100 text-yellow-800' :
                                                            reimbursement.status === '1' ? 'bg-green-100 text-green-800' :
                                                            reimbursement.status === '2' ? 'bg-red-100 text-red-800' :
                                                            'bg-gray-100 text-gray-800'
                                                        }`}>
                                                            {reimbursement.status === '0' ? t('Pending') : reimbursement.status === '1' ? t('Approved') : reimbursement.status === '2' ? t('Rejected') : t('Unknown')}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Actions Footer */}
                                            <div className="flex justify-end gap-2 p-3 border-t bg-gray-50/50 flex-shrink-0 mt-auto">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('approve-reimbursements') && reimbursement.status === '0' && (
                                                        <>
                                                            <Tooltip delayDuration={300}>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" onClick={() => { setApprovalItem(reimbursement); setApprovedAmount(reimbursement.amount?.toString() || ''); }} className="h-9 w-9 p-0 text-blue-600 hover:text-blue-700">
                                                                        <CheckCircle className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>{t('Approve')}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip delayDuration={300}>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" onClick={() => setRejectionItem(reimbursement)} className="h-9 w-9 p-0 text-red-600 hover:text-red-700">
                                                                        <XCircle className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>{t('Reject')}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </>
                                                    )}
                                                    {auth.user?.permissions?.includes('view-reimbursements') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => setViewingItem(reimbursement)} className="h-9 w-9 p-0 text-green-600 hover:text-green-700">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('View')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('edit-reimbursements') && reimbursement.status === '0' && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => openModal('edit', reimbursement)} className="h-9 w-9 p-0 text-blue-600 hover:text-blue-700">
                                                                    <EditIcon className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('Edit')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('delete-reimbursements') && reimbursement.status === '0' && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openDeleteDialog(reimbursement.id)}
                                                                    className="h-9 w-9 p-0 text-destructive hover:text-destructive"
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
                                    icon={ReceiptIcon}
                                    title={t('No Reimbursements found')}
                                    description={t('Get started by creating your first Reimbursement.')}
                                    hasFilters={!!(filters.reimbursement_number || filters.user_id || filters.category_id || filters.status)}
                                    onClearFilters={clearFilters}
                                    createPermission="create-reimbursements"
                                    onCreateClick={() => openModal('add')}
                                    createButtonText={t('Create Reimbursement')}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                {/* Pagination Footer */}
                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={reimbursements || { data: [], links: [], meta: {} }}
                        routeName="petty-cash-management.reimbursements.index"
                        filters={{...filters, per_page: perPage, view: viewMode}}
                    />
                </CardContent>
            </Card>

            <Dialog open={modalState.isOpen} onOpenChange={closeModal}>
                {modalState.mode === 'add' && (
                    <Create onSuccess={closeModal} />
                )}
                {modalState.mode === 'edit' && modalState.data && (
                    <EditReimbursement
                        reimbursement={modalState.data}
                        onSuccess={closeModal}
                    />
                )}
            </Dialog>

            <Dialog open={!!viewingItem} onOpenChange={() => setViewingItem(null)}>
                {viewingItem && <View reimbursement={viewingItem} />}
            </Dialog>

            <Dialog open={!!approvalItem} onOpenChange={() => setApprovalItem(null)}>
                {approvalItem && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Approve Reimbursement')}</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div>
                                <Label className="text-sm font-medium">{t('User')}</Label>
                                <p className="mt-1 p-2 bg-gray-50 rounded border">{approvalItem.user?.name}</p>
                            </div>
                            <div>
                                <Label className="text-sm font-medium">{t('Category')}</Label>
                                <p className="mt-1 p-2 bg-gray-50 rounded border">{approvalItem.category?.name || '-'}</p>
                            </div>
                            <div>
                                <Label className="text-sm font-medium">{t('Requested Amount')}</Label>
                                <p className="mt-1 p-2 bg-gray-50 rounded border">{formatCurrency(approvalItem.amount)}</p>
                            </div>
                            <CurrencyInput
                                label={t('Approved Amount')}
                                value={approvedAmount}
                                onChange={(value) => setApprovedAmount(value)}
                                required
                            />
                        </div>
                        <div className="flex justify-end gap-2 mt-6">
                            <Button variant="outline" onClick={() => setApprovalItem(null)}>
                                {t('Cancel')}
                            </Button>
                            <Button onClick={handleApproval} disabled={!approvedAmount}>
                                {t('Approve')}
                            </Button>
                        </div>
                    </DialogContent>
                )}
            </Dialog>

            <Dialog open={!!rejectionItem} onOpenChange={() => setRejectionItem(null)}>
                {rejectionItem && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Reject Reimbursement')}</DialogTitle>
                        </DialogHeader>
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="rejectionReason" className="text-sm font-medium">{t('Rejection Reason')}</Label>
                                <Textarea
                                    id="rejectionReason"
                                    value={rejectionReason}
                                    onChange={(e) => setRejectionReason(e.target.value)}
                                    placeholder={t('Enter reason for rejection')}
                                    className="mt-1"
                                    rows={3}
                                    required
                                />
                            </div>
                        </div>
                        <div className="flex justify-end gap-2 mt-6">
                            <Button variant="outline" onClick={() => setRejectionItem(null)}>
                                {t('Cancel')}
                            </Button>
                            <Button variant="destructive" onClick={handleRejection} disabled={!rejectionReason}>
                                {t('Reject')}
                            </Button>
                        </div>
                    </DialogContent>
                )}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Reimbursement')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

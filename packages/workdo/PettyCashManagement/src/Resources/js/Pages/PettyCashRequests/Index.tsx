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
import { Plus, Edit as EditIcon, Trash2, Eye, FileText as FileTextIcon, Download, FileImage, CheckCircle, XCircle } from "lucide-react";
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import Create from './Create';
import EditPettyCashRequest from './Edit';
import View from './View';
import NoRecordsFound from '@/components/no-records-found';
import { PettyCashRequest, PettyCashRequestsIndexProps, PettyCashRequestFilters, PettyCashRequestModalState } from './types';
import { router as inertiaRouter } from '@inertiajs/react';
import { formatDate, formatTime, formatDateTime, formatCurrency, getImagePath } from '@/utils/helpers';

export default function Index() {
    const { t } = useTranslation();
    const { pettycashrequests, auth, users, pettycashcategories } = usePage<PettyCashRequestsIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState<PettyCashRequestFilters>({
        request_number: urlParams.get('request_number') || '',
        user_id: urlParams.get('user_id') || '',
        categorie_id: urlParams.get('categorie_id') || '',
        status: urlParams.get('status') || '',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');
    const [modalState, setModalState] = useState<PettyCashRequestModalState>({
        isOpen: false,
        mode: '',
        data: null
    });
    const [viewingItem, setViewingItem] = useState<PettyCashRequest | null>(null);
    const [approvalItem, setApprovalItem] = useState<PettyCashRequest | null>(null);
    const [rejectionItem, setRejectionItem] = useState<PettyCashRequest | null>(null);
    const [approvedAmount, setApprovedAmount] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');

    const [showFilters, setShowFilters] = useState(false);



    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'petty-cash-management.petty-cash-requests.destroy',
        defaultMessage: t('Are you sure you want to delete this petty cash request?')
    });

    const handleFilter = () => {
        router.get(route('petty-cash-management.petty-cash-requests.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('petty-cash-management.petty-cash-requests.index'), {...filters, per_page: perPage, sort: field, direction, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({
            request_number: '',
            user_id: '',
            categorie_id: '',
            status: '',
        });
        router.get(route('petty-cash-management.petty-cash-requests.index'), {per_page: perPage, view: viewMode});
    };

    const openModal = (mode: 'add' | 'edit', data: PettyCashRequest | null = null) => {
        setModalState({ isOpen: true, mode, data });
    };

    const closeModal = () => {
        setModalState({ isOpen: false, mode: '', data: null });
    };

    const handleApproval = () => {
        if (!approvalItem || !approvedAmount) return;

        const currentDateTime = new Date().toISOString().slice(0, 19).replace('T', ' ');

        inertiaRouter.put(route('petty-cash-management.petty-cash-requests.update-status', approvalItem.id), {
            status: '1',
            approved_at: currentDateTime,
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

        inertiaRouter.put(route('petty-cash-management.petty-cash-requests.update-status', rejectionItem.id), {
            status: '2',
            approved_at: null,
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
            key: 'request_number',
            header: t('Request Number'),
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
            key: 'requested_amount',
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
            render: (value: string) => value ? formatDate(value) : '-'
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
        ...(auth.user?.permissions?.some((p: string) => ['view-petty-cash-requests', 'edit-petty-cash-requests', 'delete-petty-cash-requests'].includes(p)) ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, pettycashrequest: PettyCashRequest) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        {auth.user?.permissions?.includes('approve-petty-cash-requests') && pettycashrequest.status === '0' && (
                            <>
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="sm" onClick={() => { setApprovalItem(pettycashrequest); setApprovedAmount(pettycashrequest.requested_amount?.toString() || ''); }} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                            <CheckCircle className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Approve')}</p>
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip delayDuration={0}>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="sm" onClick={() => setRejectionItem(pettycashrequest)} className="h-8 w-8 p-0 text-red-600 hover:text-red-700">
                                            <XCircle className="h-4 w-4" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{t('Reject')}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </>
                        )}
                        {auth.user?.permissions?.includes('view-petty-cash-requests') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="sm" onClick={() => setViewingItem(pettycashrequest)} className="h-8 w-8 p-0 text-green-600 hover:text-green-700">
                                        <Eye className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('View')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('edit-petty-cash-requests') && pettycashrequest.status === '0' && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="sm" onClick={() => openModal('edit', pettycashrequest)} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                        <EditIcon className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Edit')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('delete-petty-cash-requests') && pettycashrequest.status === '0' && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openDeleteDialog(pettycashrequest.id)}
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
                {label: t('Petty Cash Requests')}
            ]}
            pageTitle={t('Manage Petty Cash Requests')}
            pageActions={
                <TooltipProvider>
                    {auth.user?.permissions?.includes('create-petty-cash-requests') && (
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
            <Head title={t('Petty Cash Requests')} />

            {/* Main Content Card */}
            <Card className="shadow-sm">
                {/* Search & Controls Header */}
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.request_number}
                                onChange={(value) => setFilters({...filters, request_number: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search by Request Number...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="petty-cash-management.petty-cash-requests.index"
                                filters={{...filters, per_page: perPage}}
                            />
                            <PerPageSelector
                                routeName="petty-cash-management.petty-cash-requests.index"
                                filters={{...filters, view: viewMode}}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [filters.user_id, filters.categorie_id, filters.status].filter(f => f !== '' && f !== null && f !== undefined).length;
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
                                <Select value={filters.categorie_id} onValueChange={(value) => setFilters({...filters, categorie_id: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Category')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {pettycashcategories?.map((item: any) => (
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
                                data={pettycashrequests?.data || []}
                                columns={tableColumns}
                                onSort={handleSort}
                                sortKey={sortField}
                                sortDirection={sortDirection as 'asc' | 'desc'}
                                className="rounded-none"
                                emptyState={
                                    <NoRecordsFound
                                        icon={FileTextIcon}
                                        title={t('No Petty Cash Requests found')}
                                        description={t('Get started by creating your first Petty Cash Request.')}
                                        hasFilters={!!(filters.request_number || filters.user_id || filters.categorie_id || filters.status)}
                                        onClearFilters={clearFilters}
                                        createPermission="create-petty-cash-requests"
                                        onCreateClick={() => openModal('add')}
                                        createButtonText={t('Create Petty Cash Request')}
                                        className="h-auto"
                                    />
                                }
                            />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {pettycashrequests?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {pettycashrequests?.data?.map((pettycashrequest) => (
                                        <Card key={pettycashrequest.id} className="p-0 hover:shadow-lg transition-all duration-200 relative overflow-hidden flex flex-col h-full min-w-0">
                                            {/* Header */}
                                            <div className="p-4 bg-gradient-to-r from-primary/5 to-transparent border-b flex-shrink-0">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <FileTextIcon className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="font-semibold text-sm text-gray-900">{pettycashrequest.request_number}</h3>
                                                        <p className="text-xs font-medium text-primary">{pettycashrequest.user?.name || '-'}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Body */}
                                            <div className="p-4 flex-1 min-h-0">
                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Category')}</p>
                                                        <p className="font-medium text-xs break-words">{pettycashrequest.category?.name || '-'}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Requested Amount')}</p>
                                                        <p className="font-medium text-xs">{pettycashrequest.requested_amount ? formatCurrency(pettycashrequest.requested_amount) : '-'}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Approved Amount')}</p>
                                                        <p className="font-medium text-xs">{pettycashrequest.approved_amount ? formatCurrency(pettycashrequest.approved_amount) : '-'}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Request Date')}</p>
                                                        <p className="font-medium text-xs">{pettycashrequest.created_at ? formatDate(pettycashrequest.created_at) : '-'}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Status')}</p>
                                                        {(() => {
                                                            const statusMap: Record<string, { label: string; className: string }> = {
                                                                '0': { label: 'Pending', className: 'bg-yellow-100 text-yellow-800' },
                                                                '1': { label: 'Approved', className: 'bg-green-100 text-green-800' },
                                                                '2': { label: 'Rejected', className: 'bg-red-100 text-red-800' },
                                                            };
                                                            const status = statusMap[pettycashrequest.status] || { label: pettycashrequest.status, className: 'bg-gray-100 text-gray-800' };
                                                            return <span className={`px-2 py-1 rounded-full text-xs font-medium inline-block ${status.className}`}>{t(status.label)}</span>;
                                                        })()}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Actions Footer */}
                                            <div className="flex justify-end gap-2 p-3 border-t bg-gray-50/50 flex-shrink-0 mt-auto">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('approve-petty-cash-requests') && pettycashrequest.status === '0' && (
                                                        <>
                                                            <Tooltip delayDuration={300}>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" onClick={() => { setApprovalItem(pettycashrequest); setApprovedAmount(pettycashrequest.requested_amount?.toString() || ''); }} className="h-9 w-9 p-0 text-blue-600 hover:text-blue-700">
                                                                        <CheckCircle className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>{t('Approve')}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip delayDuration={300}>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="sm" onClick={() => setRejectionItem(pettycashrequest)} className="h-9 w-9 p-0 text-red-600 hover:text-red-700">
                                                                        <XCircle className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>{t('Reject')}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        </>
                                                    )}
                                                    {auth.user?.permissions?.includes('view-petty-cash-requests') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => setViewingItem(pettycashrequest)} className="h-9 w-9 p-0 text-green-600 hover:text-green-700">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('View')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('edit-petty-cash-requests') && pettycashrequest.status === '0' && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => openModal('edit', pettycashrequest)} className="h-9 w-9 p-0 text-blue-600 hover:text-blue-700">
                                                                    <EditIcon className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('Edit')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('delete-petty-cash-requests') && pettycashrequest.status === '0' && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openDeleteDialog(pettycashrequest.id)}
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
                                    icon={FileTextIcon}
                                    title={t('No Petty Cash Requests found')}
                                    description={t('Get started by creating your first Petty Cash Request.')}
                                    hasFilters={!!(filters.request_number || filters.user_id || filters.categorie_id || filters.status)}
                                    onClearFilters={clearFilters}
                                    createPermission="create-petty-cash-requests"
                                    onCreateClick={() => openModal('add')}
                                    createButtonText={t('Create Petty Cash Request')}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                {/* Pagination Footer */}
                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={pettycashrequests || { data: [], links: [], meta: {} }}
                        routeName="petty-cash-management.petty-cash-requests.index"
                        filters={{...filters, per_page: perPage, view: viewMode}}
                    />
                </CardContent>
            </Card>

            <Dialog open={modalState.isOpen} onOpenChange={closeModal}>
                {modalState.mode === 'add' && (
                    <Create onSuccess={closeModal} />
                )}
                {modalState.mode === 'edit' && modalState.data && (
                    <EditPettyCashRequest
                        pettycashrequest={modalState.data}
                        onSuccess={closeModal}
                    />
                )}
            </Dialog>

            <Dialog open={!!viewingItem} onOpenChange={() => setViewingItem(null)}>
                {viewingItem && <View pettycashrequest={viewingItem} />}
            </Dialog>

            <Dialog open={!!approvalItem} onOpenChange={() => setApprovalItem(null)}>
                {approvalItem && (
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Approve Request')}</DialogTitle>
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
                                <p className="mt-1 p-2 bg-gray-50 rounded border">{formatCurrency(approvalItem.requested_amount)}</p>
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
                            <DialogTitle>{t('Reject Request')}</DialogTitle>
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
                title={t('Delete Petty Cash Request')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

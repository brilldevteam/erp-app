import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Eye, DollarSign as DollarSignIcon } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { Input } from "@/components/ui/input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';

import View from './View';
import NoRecordsFound from '@/components/no-records-found';
import { PettyCashExpense, PettyCashExpensesIndexProps, PettyCashExpenseFilters, PettyCashExpenseModalState } from './types';
import { formatDate, formatTime, formatDateTime, formatCurrency, getImagePath } from '@/utils/helpers';

export default function Index() {
    const { t } = useTranslation();
    const { pettycashexpenses, auth, pettycashrequests, reimbursements, pettycashes, users } = usePage<PettyCashExpensesIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState<PettyCashExpenseFilters>({
        user_name: urlParams.get('user_name') || '',
        type: urlParams.get('type') || '',
        request_number: urlParams.get('request_number') || '',
        reimbursement_number: urlParams.get('reimbursement_number') || '',
        pettycash_number: urlParams.get('pettycash_number') || '',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');

    const [viewingItem, setViewingItem] = useState<PettyCashExpense | null>(null);

    const [showFilters, setShowFilters] = useState(false);



    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'petty-cash-management.petty-cash-expenses.destroy',
        defaultMessage: t('Are you sure you want to delete this expense?')
    });

    const handleFilter = () => {
        router.get(route('petty-cash-management.petty-cash-expenses.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('petty-cash-management.petty-cash-expenses.index'), {...filters, per_page: perPage, sort: field, direction, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({
            user_name: '',
            type: '',
            request_number: '',
            reimbursement_number: '',
            pettycash_number: '',
        });
        router.get(route('petty-cash-management.petty-cash-expenses.index'), {per_page: perPage, view: viewMode});
    };



    const tableColumns = [
        {
            key: 'pettycash_number',
            header: t('Petty Cash Number'),
            sortable: false,
            render: (value: string, row: any) => {
                return row.petty_cash?.pettycash_number || '-';
            }
        },
        {
            key: 'request_number',
            header: t('Request/Reimbursement Number'),
            sortable: false,
            render: (value: string, row: any) => {
                return row.request?.request_number || row.reimbursement?.reimbursement_number || '-';
            }
        },
        {
            key: 'user',
            header: t('User'),
            sortable: false,
            render: (value: string, row: any) => row.request?.user?.name || row.reimbursement?.user?.name || '-'
        },
        {
            key: 'category',
            header: t('Category'),
            sortable: false,
            render: (value: string, row: any) => row.request?.category?.name || row.reimbursement?.category?.name || '-'
        },
        {
            key: 'type',
            header: t('Type'),
            sortable: false,
            render: (value: string) => {
                const options: any = {
                    "pettycash": t('Petty Cash'),
                    "reimbursement": t('Reimbursement')
                };
                return options[value] || value || '-';
            }
        },
        {
            key: 'amount',
            header: t('Amount'),
            sortable: false,
            render: (value: string) => formatCurrency(value) || '-'
        },
        {
            key: 'approved_at',
            header: t('Approved At'),
            sortable: false,
            render: (value: string) => value ? formatDate(value) : '-'
        },
        {
            key: 'approved_by',
            header: t('Approved By'),
            sortable: false,
            render: (value: string, row: any) => row.approver?.name || '-'
        },
        ...(auth.user?.permissions?.includes('view-petty-cash-expenses') ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, pettycashexpense: PettyCashExpense) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        <Tooltip delayDuration={0}>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="sm" onClick={() => setViewingItem(pettycashexpense)} className="h-8 w-8 p-0 text-green-600 hover:text-green-700">
                                    <Eye className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('View')}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            )
        }] : [])
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                {label: t('Petty Cash Management')},
                {label: t('Expenses')}
            ]}
            pageTitle={t('Manage Expenses')}
pageActions={null}
        >
            <Head title={t('Expenses')} />

            {/* Main Content Card */}
            <Card className="shadow-sm">
                {/* Search & Controls Header */}
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.user_name}
                                onChange={(value) => setFilters({...filters, user_name: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search by User Name...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="petty-cash-management.petty-cash-expenses.index"
                                filters={{...filters, per_page: perPage}}
                            />
                            <PerPageSelector
                                routeName="petty-cash-management.petty-cash-expenses.index"
                                filters={{...filters, view: viewMode}}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [filters.type, filters.request_number, filters.reimbursement_number, filters.pettycash_number].filter(f => f !== '' && f !== null && f !== undefined).length;
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
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Type')}</label>
                                <Select value={filters.type} onValueChange={(value) => setFilters({...filters, type: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Type')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="reimbursement">{t('Reimbursement')}</SelectItem>
                                        <SelectItem value="pettycash">{t('Petty Cash')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Request Number')}</label>
                                <Select value={filters.request_number} onValueChange={(value) => setFilters({...filters, request_number: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Request Number')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Array.from(new Set(pettycashrequests?.map((item: any) => item.request_number).filter(Boolean)))
                                            .map((requestNumber: string) => (
                                            <SelectItem key={requestNumber} value={requestNumber}>
                                                {requestNumber}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Reimbursement Number')}</label>
                                <Select value={filters.reimbursement_number} onValueChange={(value) => setFilters({...filters, reimbursement_number: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Reimbursement Number')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Array.from(new Set(reimbursements?.map((item: any) => item.reimbursement_number).filter(Boolean)))
                                            .map((reimbursementNumber: string) => (
                                            <SelectItem key={reimbursementNumber} value={reimbursementNumber}>
                                                {reimbursementNumber}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Petty Cash Number')}</label>
                                <Select value={filters.pettycash_number} onValueChange={(value) => setFilters({...filters, pettycash_number: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Petty Cash Number')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Array.from(new Set(pettycashes?.map((item: any) => item.pettycash_number).filter(Boolean)))
                                            .map((pettycashNumber: string) => (
                                            <SelectItem key={pettycashNumber} value={pettycashNumber}>
                                                {pettycashNumber}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2 col-span-full">
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
                                data={pettycashexpenses?.data || []}
                                columns={tableColumns}
                                onSort={handleSort}
                                sortKey={sortField}
                                sortDirection={sortDirection as 'asc' | 'desc'}
                                className="rounded-none"
                                emptyState={
                                    <NoRecordsFound
                                        icon={DollarSignIcon}
                                        title={t('No Expenses found')}
                                        description={t('Get started by creating your first Expense.')}
                                        hasFilters={!!(filters.user_name || filters.type || filters.request_number || filters.reimbursement_number || filters.pettycash_number)}
                                        onClearFilters={clearFilters}

                                        className="h-auto"
                                    />
                                }
                            />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {pettycashexpenses?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {pettycashexpenses?.data?.map((pettycashexpense) => (
                                        <Card key={pettycashexpense.id} className="p-0 hover:shadow-lg transition-all duration-200 relative overflow-hidden flex flex-col h-full min-w-0">
                                            {/* Header */}
                                            <div className="p-4 bg-gradient-to-r from-primary/5 to-transparent border-b flex-shrink-0">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <DollarSignIcon className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="font-semibold text-sm text-gray-900">{pettycashexpense.request?.request_number || pettycashexpense.reimbursement?.reimbursement_number || '-'}</h3>
                                                        <p className="text-xs font-medium text-primary">{pettycashexpense.request?.user?.name || pettycashexpense.reimbursement?.user?.name || '-'}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Body */}
                                            <div className="p-4 flex-1 min-h-0">
                                                <div className="grid grid-cols-2 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Petty Cash Number')}</p>
                                                        <p className="font-medium text-xs">{pettycashexpense.petty_cash?.pettycash_number || '-'}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Category')}</p>
                                                        <p className="font-medium text-xs">{pettycashexpense.request?.category?.name || pettycashexpense.reimbursement?.category?.name || '-'}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Type')}</p>
                                                        <p className="font-medium text-xs">{(() => {
                                                            const options: any = {
                                                                "reimbursement": t('Reimbursement'),
                                                                "pettycash": t('Petty Cash')
                                                            };
                                                            return options[pettycashexpense.type] || '-';
                                                        })()}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Amount')}</p>
                                                        <p className="font-medium text-xs">{formatCurrency(pettycashexpense.amount) || '-'}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Approved At')}</p>
                                                        <p className="font-medium text-xs">{pettycashexpense.approved_at ? formatDate(pettycashexpense.approved_at) : '-'}</p>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Approved By')}</p>
                                                        <p className="font-medium text-xs">{pettycashexpense.approver?.name || '-'}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Actions Footer */}
                                            <div className="flex justify-end gap-2 p-3 border-t bg-gray-50/50 flex-shrink-0 mt-auto">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('view-petty-cash-expenses') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => setViewingItem(pettycashexpense)} className="h-9 w-9 p-0 text-green-600 hover:text-green-700">
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('View')}</p>
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
                                    icon={DollarSignIcon}
                                    title={t('No Expenses found')}
                                    description={t('Get started by creating your first Expense.')}
                                    hasFilters={!!(filters.user_name || filters.type || filters.request_number || filters.pettycash_number)}
                                    onClearFilters={clearFilters}

                                />
                            )}
                        </div>
                    )}
                </CardContent>

                {/* Pagination Footer */}
                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={pettycashexpenses || { data: [], links: [], meta: {} }}
                        routeName="petty-cash-management.petty-cash-expenses.index"
                        filters={{...filters, per_page: perPage, view: viewMode}}
                    />
                </CardContent>
            </Card>



            <Dialog open={!!viewingItem} onOpenChange={() => setViewingItem(null)}>
                {viewingItem && <View pettycashexpense={viewingItem} />}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Expense')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

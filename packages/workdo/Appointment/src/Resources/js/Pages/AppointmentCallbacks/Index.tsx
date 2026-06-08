import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import View from './View';
import { Trash2, CalendarDays, Eye, Check, X } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import NoRecordsFound from '@/components/no-records-found';
import { formatDate, formatTime } from '@/utils/helpers';

interface AppointmentCallback {
    id: number;
    unique_code: string;
    reason: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    schedule: {
        name: string;
        email: string;
        phone: string;
    };
    appointment: {
        appointment_name: string;
        appointment_type: string;
    };
}

interface User {
    id: number;
    name: string;
}

interface CallbacksIndexProps {
    callbacks: {
        data: AppointmentCallback[];
        links: any[];
        meta: any;
    };
    users: User[];
    auth: {
        user: {
            permissions: string[];
        };
    };
}

export default function Index() {
    const { t } = useTranslation();
    const { callbacks, users, auth } = usePage<CallbacksIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState({
        name: urlParams.get('name') || '',
        status: urlParams.get('status') || '',
        date_from: urlParams.get('date_from') || '',
        date_to: urlParams.get('date_to') || '',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');
    const [showFilters, setShowFilters] = useState(false);
    const [selectedCallback, setSelectedCallback] = useState<AppointmentCallback | null>(null);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [isProcessing, setIsProcessing] = useState(false);

    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'appointment.callbacks.destroy',
        defaultMessage: t('Are you sure you want to delete this callback?')
    });

    const handleFilter = () => {
        router.get(route('appointment.callbacks.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('appointment.callbacks.index'), {...filters, per_page: perPage, sort: field, direction, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({ name: '', status: '', date_from: '', date_to: '' });
        router.get(route('appointment.callbacks.index'), {per_page: perPage, view: viewMode});
    };

    const getStatusBadge = (status: string) => {
        const colors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'approved': 'bg-green-100 text-green-800',
            'reject': 'bg-red-100 text-red-800',
            'complete': 'bg-blue-100 text-blue-800'
        };
        return (
            <span className={`px-2 py-1 rounded-full text-sm ${colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800'}`}>
                {t(status)}
            </span>
        );
    };

    const tableColumns = [
        {
            key: 'unique_code',
            header: t('Unique Code'),
            sortable: true,
            render: (value: string, callback: AppointmentCallback) =>
                auth.user?.permissions?.includes('view-appointment-callbacks') ? (
                    <span className="text-blue-600 hover:text-blue-700 cursor-pointer" onClick={() => {
                        setSelectedCallback(callback);
                        setShowDetailModal(true);
                    }}>{value || '-'}</span>
                ) : (
                    value || '-'
                )
        },
        {
            key: 'schedule.name',
            header: t('Name'),
            sortable: false,
            render: (_: any, callback: AppointmentCallback) => callback.schedule?.name || '-'
        },
        {
            key: 'date',
            header: t('Date'),
            sortable: true,
            render: (value: string) => formatDate(value)
        },
        {
            key: 'start_time',
            header: t('Starting Time'),
            sortable: false,
            render: (value: string) => formatTime(value)
        },
        {
            key: 'end_time',
            header: t('Ending Time'),
            sortable: false,
            render: (value: string) => formatTime(value)
        },
        {
            key: 'appointment.appointment_name',
            header: t('Appointment Name'),
            sortable: false,
            render: (_: any, callback: AppointmentCallback) => callback.appointment?.appointment_name || '-'
        },
        {
            key: 'status',
            header: t('Status'),
            sortable: true,
            render: (value: string) => getStatusBadge(value)
        },
        ...(auth.user?.permissions?.some((p: string) => ['view-appointment-callbacks', 'delete-appointment-callbacks'].includes(p)) ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, callback: AppointmentCallback) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        {auth.user?.permissions?.includes('view-appointment-callbacks') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSelectedCallback(callback);
                                            setShowDetailModal(true);
                                        }}
                                        className="h-8 w-8 p-0 text-green-600 hover:text-green-700"
                                    >
                                        <Eye className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('View')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('delete-appointment-callbacks') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openDeleteDialog(callback.id)}
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
                { label: t('Appointment'), url: route('appointment.index') },
                {label: t('Appointment Callbacks')}
            ]}
            pageTitle={t('Manage Appointment Callbacks')}
        >
            <Head title={t('Appointment Callbacks')} />

            <Card className="shadow-sm">
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.name}
                                onChange={(value) => setFilters({...filters, name: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search Callbacks...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="appointment.callbacks.index"
                                filters={{...filters, per_page: perPage}}
                            />
                            <PerPageSelector
                                routeName="appointment.callbacks.index"
                                filters={{...filters, view: viewMode}}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [filters.status, filters.date_from, filters.date_to].filter(f => f !== '' && f !== null && f !== undefined).length;
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
                        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Status')}</label>
                                <Select value={filters.status} onValueChange={(value) => setFilters({...filters, status: value})}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Status')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pending">{t('Pending')}</SelectItem>
                                        <SelectItem value="approved">{t('Approved')}</SelectItem>
                                        <SelectItem value="reject">{t('Reject')}</SelectItem>
                                        <SelectItem value="complete">{t('Complete')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Start Date')}</label>
                                <DatePicker
                                    value={filters.date_from}
                                    onChange={(value) => setFilters({...filters, date_from: value})}
                                    placeholder={t('Select start date')}
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('End Date')}</label>
                                <DatePicker
                                    value={filters.date_to}
                                    onChange={(value) => setFilters({...filters, date_to: value})}
                                    placeholder={t('Select end date')}
                                    minDate={filters.date_from ? new Date(filters.date_from) : undefined}
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={handleFilter} size="sm">{t('Apply')}</Button>
                                <Button variant="outline" onClick={clearFilters} size="sm">{t('Clear')}</Button>
                            </div>
                        </div>
                    </CardContent>
                )}

                <CardContent className="p-0">
                    {viewMode === 'list' ? (
                        <div className="overflow-y-auto scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-100 max-h-[70vh] rounded-none w-full">
                            <div className="min-w-[800px]">
                            <DataTable
                                data={callbacks?.data || []}
                                columns={tableColumns}
                                onSort={handleSort}
                                sortKey={sortField}
                                sortDirection={sortDirection as 'asc' | 'desc'}
                                className="rounded-none"
                                emptyState={
                                    <NoRecordsFound
                                        icon={CalendarDays}
                                        title={t('No Callbacks found')}
                                        description={t('No callbacks have been created yet.')}
                                        hasFilters={!!(filters.name || filters.status)}
                                        onClearFilters={clearFilters}
                                        className="h-auto"
                                    />
                                }
                            />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {callbacks?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {callbacks?.data?.map((callback) => (
                                        <Card key={callback.id} className="p-0 hover:shadow-lg transition-all duration-200 relative overflow-hidden flex flex-col h-full min-w-0">

                                            {/* Header */}
                                            <div className="p-4 bg-gradient-to-r from-primary/5 to-transparent border-b flex-shrink-0">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <CalendarDays className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="font-semibold text-sm text-gray-900">{callback.schedule?.name}</h3>
                                                        {auth.user?.permissions?.includes('view-appointment-callbacks') ? (
                                                            <p className="text-xs text-blue-600 hover:text-blue-700 cursor-pointer" onClick={() => {
                                                                setSelectedCallback(callback);
                                                                setShowDetailModal(true);
                                                            }}>#{callback.unique_code || callback.id}</p>
                                                        ) : (
                                                            <p className="text-xs text-muted-foreground">#{callback.unique_code || callback.id}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Body */}
                                            <div className="p-4 flex-1 min-h-0">
                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Customer Name')}</p>
                                                        <p className="font-medium">{callback.schedule?.name}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Email')}</p>
                                                        <p className="font-medium">{callback.schedule?.email}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Date & Time')}</p>
                                                        <div className="flex flex-wrap gap-1">
                                                            <span className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                                                {formatDate(callback.date)}
                                                            </span>
                                                            <span className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                                                {formatTime(callback.start_time)} - {formatTime(callback.end_time)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="text-xs min-w-0">
                                                    <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Appointment')}</p>
                                                    <p className="font-medium">{callback.appointment?.appointment_name || '-'}</p>
                                                </div>
                                            </div>

                                            {/* Actions Footer */}
                                            <div className="flex justify-between items-center p-3 border-t bg-gray-50/50 flex-shrink-0 mt-auto">
                                                {getStatusBadge(callback.status)}
                                                <div className="flex gap-2">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('view-appointment-callbacks') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => {
                                                                        setSelectedCallback(callback);
                                                                        setShowDetailModal(true);
                                                                    }}
                                                                    className="h-8 w-8 p-0 text-green-600 hover:text-green-700"
                                                                >
                                                                    <Eye className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('View')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('delete-appointment-callbacks') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openDeleteDialog(callback.id)}
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
                                            </div>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <NoRecordsFound
                                    icon={CalendarDays}
                                    title={t('No Callbacks found')}
                                    description={t('No callbacks have been created yet.')}
                                    hasFilters={!!(filters.name || filters.status)}
                                    onClearFilters={clearFilters}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={callbacks || { data: [], links: [], meta: {} }}
                        routeName="appointment.callbacks.index"
                        filters={{...filters, per_page: perPage, view: viewMode}}
                    />
                </CardContent>
            </Card>

            <Dialog open={showDetailModal} onOpenChange={setShowDetailModal}>
                {selectedCallback && (
                    <View
                        callback={selectedCallback}
                        onClose={() => setShowDetailModal(false)}
                    />
                )}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Callback')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

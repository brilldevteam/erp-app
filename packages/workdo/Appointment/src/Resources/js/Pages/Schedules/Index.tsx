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
import { Dialog, DialogContent } from '@/components/ui/dialog';
import View from './View';
import { Trash2, CalendarDays, Eye } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import NoRecordsFound from '@/components/no-records-found';
import { formatDate, formatTime } from '@/utils/helpers';

interface Schedule {
    id: number;
    unique_id: string;
    name: string;
    email: string;
    phone: string;
    date: string;
    start_time: string;
    end_time: string;
    appointment: {
        appointment_name: string;
    };
    status: string;
}

interface User {
    id: number;
    name: string;
}

interface SchedulesIndexProps {
    schedules: {
        data: Schedule[];
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
    const { schedules, users, auth } = usePage<SchedulesIndexProps>().props;
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
    const [selectedSchedule, setSelectedSchedule] = useState<Schedule | null>(null);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [userSelectionError, setUserSelectionError] = useState<string>('');

    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'appointment.schedules.destroy',
        defaultMessage: t('Are you sure you want to delete this schedule?')
    });

    const handleFilter = () => {
        router.get(route('appointment.schedules.index'), {...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('appointment.schedules.index'), {...filters, per_page: perPage, sort: field, direction, view: viewMode}, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({ name: '', status: '', date_from: '', date_to: '' });
        router.get(route('appointment.schedules.index'), {per_page: perPage, view: viewMode});
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
            key: 'unique_id',
            header: t('Unique Code'),
            sortable: true,
            render: (value: string, schedule: Schedule) =>
                auth.user?.permissions?.includes('view-schedules') ? (
                    <span className="text-blue-600 hover:text-blue-700 cursor-pointer" onClick={() => {
                        setSelectedSchedule(schedule);
                        setShowDetailModal(true);
                    }}>{value || '-'}</span>
                ) : (
                    value || '-'
                )
        },
        {
            key: 'name',
            header: t('Scheduler Name'),
            sortable: true
        },
        {
            key: 'email',
            header: t('Email'),
            sortable: true
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
            key: 'appointment',
            header: t('Appointment Name'),
            sortable: false,
            render: (value: any) => value?.appointment_name || '-'
        },
        {
            key: 'status',
            header: t('Status'),
            sortable: true,
            render: (value: string) => getStatusBadge(value)
        },
        ...(auth.user?.permissions?.some((p: string) => ['view-schedules', 'delete-schedules'].includes(p)) ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, schedule: Schedule) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        {auth.user?.permissions?.includes('view-schedules') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSelectedSchedule(schedule);
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
                        {auth.user?.permissions?.includes('delete-schedules') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openDeleteDialog(schedule.id)}
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
                {
                    label: t('Appointment'),
                    url: urlParams.get('appointment_id')
                        ? route('appointment.appointments.index')
                        : route('appointment.index')
                },
                {label: t('Schedules')}
            ]}
            pageTitle={t('Manage Schedules')}
        >
            <Head title={t('Schedules')} />

            <Card className="shadow-sm">
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.name}
                                onChange={(value) => setFilters({...filters, name: value})}
                                onSearch={handleFilter}
                                placeholder={t('Search Schedules...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="appointment.schedules.index"
                                filters={{...filters, per_page: perPage}}
                            />
                            <PerPageSelector
                                routeName="appointment.schedules.index"
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
                                data={schedules?.data || []}
                                columns={tableColumns}
                                onSort={handleSort}
                                sortKey={sortField}
                                sortDirection={sortDirection as 'asc' | 'desc'}
                                className="rounded-none"
                                emptyState={
                                    <NoRecordsFound
                                        icon={CalendarDays}
                                        title={t('No Schedules found')}
                                        description={t('No schedules have been created yet.')}
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
                            {schedules?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {schedules?.data?.map((schedule) => (
                                        <Card key={schedule.id} className="p-0 hover:shadow-lg transition-all duration-200 relative overflow-hidden flex flex-col h-full min-w-0">

                                            {/* Header */}
                                            <div className="p-4 bg-gradient-to-r from-primary/5 to-transparent border-b flex-shrink-0">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <CalendarDays className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="font-semibold text-sm text-gray-900">{schedule.name}</h3>
                                                        {auth.user?.permissions?.includes('view-schedules') ? (
                                                            <p className="text-xs text-blue-600 hover:text-blue-700 cursor-pointer" onClick={() => {
                                                                setSelectedSchedule(schedule);
                                                                setShowDetailModal(true);
                                                            }}>{schedule.unique_id || schedule.id}</p>
                                                        ) : (
                                                            <p className="text-xs text-muted-foreground">#{schedule.unique_id || schedule.id}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Body */}
                                            <div className="p-4 flex-1 min-h-0">
                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Scheduler Name')}</p>
                                                        <p className="font-medium">{schedule.name}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Email')}</p>
                                                        <p className="font-medium">{schedule.email}</p>
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Date & Time')}</p>
                                                        <div className="flex flex-wrap gap-1">
                                                            <span className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                                                {formatDate(schedule.date)}
                                                            </span>
                                                            <span className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                                                {formatTime(schedule.start_time)} - {formatTime(schedule.end_time)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="text-xs min-w-0">
                                                    <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Appointment')}</p>
                                                    <p className="font-medium">{schedule.appointment?.appointment_name || '-'}</p>
                                                </div>
                                            </div>

                                            {/* Actions Footer */}
                                            <div className="flex justify-between items-center p-3 border-t bg-gray-50/50 flex-shrink-0 mt-auto">
                                                {getStatusBadge(schedule.status)}
                                                <div className="flex gap-2">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('view-schedules') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => {
                                                                        setSelectedSchedule(schedule);
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
                                                    {auth.user?.permissions?.includes('delete-schedules') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openDeleteDialog(schedule.id)}
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
                                    title={t('No Schedules found')}
                                    description={t('No schedules have been created yet.')}
                                    hasFilters={!!(filters.name || filters.status)}
                                    onClearFilters={clearFilters}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={schedules || { data: [], links: [], meta: {} }}
                        routeName="appointment.schedules.index"
                        filters={{...filters, per_page: perPage, view: viewMode}}
                    />
                </CardContent>
            </Card>

            <Dialog open={showDetailModal} onOpenChange={setShowDetailModal}>
                {selectedSchedule && (
                    <View
                        schedule={selectedSchedule}
                        users={users}
                        auth={auth}
                        onClose={() => {
                            setShowDetailModal(false);
                            setSelectedUserId('');
                            setUserSelectionError('');
                        }}
                    />
                )}
            </Dialog>

            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Schedule')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

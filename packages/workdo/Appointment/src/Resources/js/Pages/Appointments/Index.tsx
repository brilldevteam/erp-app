import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useDeleteHandler } from '@/hooks/useDeleteHandler';
import { usePageButtons } from '@/hooks/usePageButtons';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { DataTable } from "@/components/ui/data-table";
import { Dialog } from "@/components/ui/dialog";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Plus, Edit as EditIcon, Trash2, Eye, Calendar as CalendarIcon, Download, FileImage, Clock, Copy, Link } from "lucide-react";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { FilterButton } from '@/components/ui/filter-button';
import { Pagination } from "@/components/ui/pagination";
import { SearchInput } from "@/components/ui/search-input";
import { ListGridToggle } from '@/components/ui/list-grid-toggle';
import { PerPageSelector } from '@/components/ui/per-page-selector';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Create from './Create';
import EditAppointment from './Edit';
import ViewAppointment from './View';


import NoRecordsFound from '@/components/no-records-found';
import { Appointment, AppointmentsIndexProps, AppointmentFilters, AppointmentModalState } from './types';

export default function Index() {
    const { t } = useTranslation();
    const { appointments, auth } = usePage<AppointmentsIndexProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [filters, setFilters] = useState<AppointmentFilters>({
        appointment_name: urlParams.get('appointment_name') || '',
        appointment_type: urlParams.get('appointment_type') || '',
    });

    const [perPage] = useState(urlParams.get('per_page') || '10');
    const [sortField, setSortField] = useState(urlParams.get('sort') || '');
    const [sortDirection, setSortDirection] = useState(urlParams.get('direction') || 'asc');
    const [viewMode, setViewMode] = useState<'list' | 'grid'>(urlParams.get('view') as 'list' | 'grid' || 'list');
    const [modalState, setModalState] = useState<AppointmentModalState>({
        isOpen: false,
        mode: '',
        data: null
    });

    const [showFilters, setShowFilters] = useState(false);
    const [copiedCode, setCopiedCode] = useState<string | null>(null);

    const pageButtons = usePageButtons('appointmentBtn', 'Appointments');

    useFlashMessages();

    const { deleteState, openDeleteDialog, closeDeleteDialog, confirmDelete } = useDeleteHandler({
        routeName: 'appointment.appointments.destroy',
        defaultMessage: t('Are you sure you want to delete this appointment?')
    });

    const handleFilter = () => {
        router.get(route('appointment.appointments.index'), { ...filters, per_page: perPage, sort: sortField, direction: sortDirection, view: viewMode }, {
            preserveState: true,
            replace: true
        });
    };

    const handleSort = (field: string) => {
        const direction = sortField === field && sortDirection === 'asc' ? 'desc' : 'asc';
        setSortField(field);
        setSortDirection(direction);
        router.get(route('appointment.appointments.index'), { ...filters, per_page: perPage, sort: field, direction, view: viewMode }, {
            preserveState: true,
            replace: true
        });
    };

    const clearFilters = () => {
        setFilters({
            appointment_name: '',
            appointment_type: '',
        });
        router.get(route('appointment.appointments.index'), { per_page: perPage, view: viewMode });
    };

    const copyAppointmentLink = async (encryptedId: string) => {
        const userSlug = auth.user?.slug || 'default';
        const link = route('appointment.public.book', { userSlug, encryptedId });
        const fullUrl = link.startsWith('http') ? link : window.location.origin + link;
        try {
            await navigator.clipboard.writeText(fullUrl);
            setCopiedCode(encryptedId);
            setTimeout(() => setCopiedCode(null), 2000);
        } catch (err) {
        }
    };

    const openModal = (mode: 'add' | 'edit' | 'view', data: Appointment | null = null) => {
        setModalState({ isOpen: true, mode, data });
    };

    const closeModal = () => {
        setModalState({ isOpen: false, mode: '', data: null });
    };

    const tableColumns = [
        {
            key: 'appointment_name',
            header: t('Appointment Name'),
            sortable: true
        },
        {
            key: 'week_day',
            header: t('Week Day'),
            sortable: false,
            render: (value: string[] | string) => {
                if (!value) return '-';
                let items = [];
                if (typeof value === 'string') {
                    try {
                        items = JSON.parse(value);
                    } catch {
                        items = [value];
                    }
                } else if (Array.isArray(value)) {
                    items = value;
                }
                if (items.length === 0) return '-';
                const optionMap: any = { "monday": "monday", "option0": "monday", "tuesday": "tuesday", "option1": "tuesday", "wednesday": "wednesday", "option2": "wednesday", "thursday": "thursday", "option3": "thursday", "friday": "friday", "option4": "friday", "saturday": "saturday", "option5": "saturday", "sunday": "sunday", "option6": "sunday" };
                return (
                    <div className="flex flex-wrap gap-1">
                        {items.slice(0, 2).map((item: any, index: number) => (
                            <span key={index} className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                {optionMap[item] || item}
                            </span>
                        ))}
                        {items.length > 2 && (
                            <span className="text-xs text-gray-500">+{items.length - 2}</span>
                        )}
                    </div>
                );
            }
        },
        {
            key: 'duration',
            header: t('Duration (Minutes)'),
            sortable: false,
            render: (value: number) => value || '-'
        },
        {
            key: 'appointment_type',
            header: t('Appointment Type'),
            sortable: false,
            render: (value: any) => {
                const options: any = { "0": "Paid", "1": "Free" };
                const type = options[value] || value;
                return (
                    <span className={`px-2 py-1 rounded-full text-sm ${value === '0' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                        {type}
                    </span>
                );
            }
        },
        {
            key: 'enabled',
            header: t('Enabled'),
            sortable: false,
            render: (value: boolean) => (
                <span className={`px-2 py-1 rounded-full text-sm ${value ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                    }`}>
                    {value ? t('On') : t('Off')}
                </span>
            )
        },
        ...(auth.user?.permissions?.some((p: string) => ['copy-appointment-link', 'view-appointments', 'edit-appointments', 'delete-appointments'].includes(p)) ? [{
            key: 'actions',
            header: t('Actions'),
            render: (_: any, appointment: Appointment) => (
                <div className="flex gap-1">
                    <TooltipProvider>
                        {auth.user?.permissions?.includes('copy-appointment-link') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => copyAppointmentLink(appointment.encrypted_id)}
                                        className={`h-8 w-8 p-0 transition-colors ${
                                            copiedCode === appointment.encrypted_id
                                                ? 'text-green-600 hover:text-green-700 bg-green-50'
                                                : 'text-purple-600 hover:text-purple-700'
                                        }`}
                                    >
                                        {copiedCode === appointment.encrypted_id ? <Copy className="h-4 w-4" /> : <Link className="h-4 w-4" />}
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{copiedCode === appointment.encrypted_id ? t('Copied!') : t('Copy Link')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('view-schedules') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.get(route('appointment.schedules.index', { appointment_id: appointment.id }))}
                                        className="h-8 w-8 p-0 text-orange-600 hover:text-orange-700"
                                    >
                                        <Clock className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('View Schedules')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('view-appointments') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openModal('view', appointment)}
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
                        {auth.user?.permissions?.includes('edit-appointments') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="sm" onClick={() => openModal('edit', appointment)} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                        <EditIcon className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Edit')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('delete-appointments') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => openDeleteDialog(appointment.id)}
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
                { label: t('Appointments') }
            ]}
            pageTitle={t('Manage Appointments')}
            pageActions={
                <div className="flex gap-2">
                    <TooltipProvider>
                        {pageButtons.map((button) => (
                            <div key={button.id}>{button.component}</div>
                        ))}
                        {auth.user?.permissions?.includes('view-appointments-calendar') && (
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button size="sm" variant="outline" onClick={() => router.get(route('appointment.appointments.calendar'))}>
                                        <CalendarIcon className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Calendar View')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}
                        {auth.user?.permissions?.includes('create-appointments') && (
                            <>
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
                            </>
                        )}
                    </TooltipProvider>
                </div>
            }
        >
            <Head title={t('Appointments')} />

            {/* Main Content Card */}
            <Card className="shadow-sm">
                {/* Search & Controls Header */}
                <CardContent className="p-6 border-b bg-gray-50/50">
                    <div className="flex items-center justify-between gap-4">
                        <div className="flex-1 max-w-md">
                            <SearchInput
                                value={filters.appointment_name}
                                onChange={(value) => setFilters({ ...filters, appointment_name: value })}
                                onSearch={handleFilter}
                                placeholder={t('Search Appointments...')}
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <ListGridToggle
                                currentView={viewMode}
                                routeName="appointment.appointments.index"
                                filters={{ ...filters, per_page: perPage }}
                            />
                            <PerPageSelector
                                routeName="appointment.appointments.index"
                                filters={{ ...filters, view: viewMode }}
                            />
                            <div className="relative">
                                <FilterButton
                                    showFilters={showFilters}
                                    onToggle={() => setShowFilters(!showFilters)}
                                />
                                {(() => {
                                    const activeFilters = [filters.appointment_type].filter(f => f !== '' && f !== null && f !== undefined).length;
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
                        <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">{t('Appointment Type')}</label>
                                <Select value={filters.appointment_type} onValueChange={(value) => setFilters({ ...filters, appointment_type: value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Filter by Appointment Type')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="0">{t('paid')}</SelectItem>
                                        <SelectItem value="1">{t('free')}</SelectItem>
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
                                    data={appointments?.data || []}
                                    columns={tableColumns}
                                    onSort={handleSort}
                                    sortKey={sortField}
                                    sortDirection={sortDirection as 'asc' | 'desc'}
                                    className="rounded-none"
                                    emptyState={
                                        <NoRecordsFound
                                            icon={CalendarIcon}
                                            title={t('No Appointments found')}
                                            description={t('Get started by creating your first Appointment.')}
                                            hasFilters={!!(filters.appointment_name || filters.appointment_type)}
                                            onClearFilters={clearFilters}
                                            createPermission="create-appointments"
                                            onCreateClick={() => openModal('add')}
                                            createButtonText={t('Create Appointment')}
                                            className="h-auto"
                                        />
                                    }
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-auto max-h-[70vh] p-6">
                            {appointments?.data?.length > 0 ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                                    {appointments?.data?.map((appointment) => (
                                        <Card key={appointment.id} className="p-0 hover:shadow-lg transition-all duration-200 relative overflow-hidden flex flex-col h-full min-w-0">

                                            {/* Header */}
                                            <div className="p-4 bg-gradient-to-r from-primary/5 to-transparent border-b flex-shrink-0">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-primary/10 rounded-lg">
                                                        <CalendarIcon className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <h3 className="font-semibold text-sm text-gray-900">{appointment.appointment_name}</h3>
                                                        <p className="text-xs font-medium text-primary">{appointment.duration ? `${appointment.duration} min` : '-'}</p>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Body */}
                                            <div className="p-4 flex-1 min-h-0">
                                                <div className="grid grid-cols-1 gap-4 mb-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Week Days')}</p>
                                                        {(() => {
                                                            let items = [];
                                                            if (typeof appointment.week_day === 'string') {
                                                                try {
                                                                    items = JSON.parse(appointment.week_day);
                                                                } catch {
                                                                    items = [appointment.week_day];
                                                                }
                                                            } else if (Array.isArray(appointment.week_day)) {
                                                                items = appointment.week_day;
                                                            }
                                                            const optionMap: any = { "monday": "Monday", "0": "Monday", "tuesday": "Tuesday", "1": "Tuesday", "wednesday": "Wednesday", "2": "Wednesday", "thursday": "Thursday", "3": "Thursday", "friday": "Friday", "4": "Friday", "saturday": "Saturday", "5": "Saturday", "sunday": "Sunday", "6": "Sunday" };
                                                            return items.length > 0 ? (
                                                                <div className="flex flex-wrap gap-1">
                                                                    {items.map((item: any, index: number) => (
                                                                        <span key={index} className="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs">
                                                                            {optionMap[item] || item}
                                                                        </span>
                                                                    ))}
                                                                </div>
                                                            ) : (
                                                                <span className="text-gray-500 text-xs">-</span>
                                                            );
                                                        })()}
                                                    </div>
                                                </div>

                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Type')}</p>
                                                        <span className={`px-2 py-1 rounded-full text-xs font-medium inline-block ${appointment.appointment_type === '0' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {(() => { const options: any = { "0": "Paid", "1": "Free" }; return options[appointment.appointment_type] || appointment.appointment_type || '-'; })()}
                                                        </span>
                                                    </div>
                                                    <div className="text-xs min-w-0">
                                                        <p className="text-muted-foreground mb-1 text-xs uppercase tracking-wide">{t('Status')}</p>
                                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${appointment.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                            {appointment.enabled ? t('Active') : t('Inactive')}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Actions Footer */}
                                            <div className="flex justify-end items-center p-3 border-t bg-gray-50/50 flex-shrink-0 mt-auto">
                                                <div className="flex gap-2">
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('copy-appointment-link') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => copyAppointmentLink(appointment.encrypted_id)}
                                                                    className={`h-8 w-8 p-0 transition-all duration-200 ${
                                                                        copiedCode === appointment.encrypted_id
                                                                            ? 'text-green-600 hover:text-green-700 scale-110'
                                                                            : 'text-purple-600 hover:text-purple-700'
                                                                    }`}
                                                                >
                                                                    {copiedCode === appointment.encrypted_id ? <Copy className="h-4 w-4" /> : <Link className="h-4 w-4" />}
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{copiedCode === appointment.encrypted_id ? t('Copied!') : t('Copy Link')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('view-schedules') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => router.get(route('appointment.schedules.index', { appointment_id: appointment.id }))}
                                                                    className="h-8 w-8 p-0 text-orange-600 hover:text-orange-700"
                                                                >
                                                                    <Clock className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('View Schedules')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('view-appointments') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openModal('view', appointment)}
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
                                                    {auth.user?.permissions?.includes('edit-appointments') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button variant="ghost" size="sm" onClick={() => openModal('edit', appointment)} className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700">
                                                                    <EditIcon className="h-4 w-4" />
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{t('Edit')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                    {auth.user?.permissions?.includes('delete-appointments') && (
                                                        <Tooltip delayDuration={300}>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => openDeleteDialog(appointment.id)}
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
                                    icon={CalendarIcon}
                                    title={t('No Appointments found')}
                                    description={t('Get started by creating your first Appointment.')}
                                    hasFilters={!!(filters.appointment_name || filters.appointment_type)}
                                    onClearFilters={clearFilters}
                                    createPermission="create-appointments"
                                    onCreateClick={() => openModal('add')}
                                    createButtonText={t('Create Appointment')}
                                />
                            )}
                        </div>
                    )}
                </CardContent>

                {/* Pagination Footer */}
                <CardContent className="px-4 py-2 border-t bg-gray-50/30">
                    <Pagination
                        data={appointments || { data: [], links: [], meta: {} }}
                        routeName="appointment.appointments.index"
                        filters={{ ...filters, per_page: perPage, view: viewMode }}
                    />
                </CardContent>
            </Card>

            <Dialog open={modalState.isOpen} onOpenChange={closeModal}>
                {modalState.mode === 'add' && (
                    <Create onSuccess={closeModal} />
                )}
                {modalState.mode === 'edit' && modalState.data && (
                    <EditAppointment
                        appointment={modalState.data}
                        onSuccess={closeModal}
                    />
                )}
                {modalState.mode === 'view' && modalState.data && (
                    <ViewAppointment
                        appointment={modalState.data}
                        onClose={closeModal}
                    />
                )}
            </Dialog>
            <ConfirmationDialog
                open={deleteState.isOpen}
                onOpenChange={closeDeleteDialog}
                title={t('Delete Appointment')}
                message={deleteState.message}
                confirmText={t('Delete')}
                onConfirm={confirmDelete}
                variant="destructive"
            />
        </AuthenticatedLayout>
    );
}

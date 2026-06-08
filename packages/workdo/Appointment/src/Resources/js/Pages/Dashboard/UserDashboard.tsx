import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Calendar, CalendarDays, Users, CheckCircle, Clock, User } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { PieChart } from '@/components/charts/PieChart';
import { formatDate, formatTime } from '@/utils/helpers';
import CalendarView from '@/components/calendar-view';

interface UserDashboardProps {
    stats: {
        assigned_schedules: number;
        pending_schedules: number;
        approved_schedules: number;
        completed_schedules: number;
        rejected_schedules: number;
    };
    recent_schedules: Array<{
        id: number;
        unique_id: string;
        name: string;
        email: string;
        date: string;
        start_time: string;
        status: string;
        appointment_name: string;
    }>;
    calendar_events: Array<{
        id: number;
        title: string;
        startDate: string;
        endDate: string;
        time: string;
        status: string;
        name: string;
        description: string;
        type: string;
    }>;
    performance_chart: Array<{
        name: string;
        value: number;
        color?: string;
    }>;
}

export default function UserDashboard({ stats, recent_schedules = [], calendar_events = [], performance_chart = [] }: UserDashboardProps) {
    const { t } = useTranslation();

    // Provide default values for stats
    const defaultStats = {
        assigned_schedules: 0,
        pending_schedules: 0,
        approved_schedules: 0,
        completed_schedules: 0,
        rejected_schedules: 0,
        ...stats
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

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Appointment Dashboard') }]}
            pageTitle={t('Appointment Dashboard')}
        >
            <Head title={t('Appointment Dashboard')} />

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <Card className="bg-gradient-to-r from-blue-50 to-blue-100 border-blue-200 cursor-pointer hover:shadow-md transition-shadow" onClick={() => router.get(route('appointment.schedules.index'))}>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-blue-700">{t('Assigned Schedules')}</CardTitle>
                        <User className="h-8 w-8 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-blue-700">{defaultStats.assigned_schedules}</div>
                    </CardContent>
                </Card>
                <Card className="bg-gradient-to-r from-green-50 to-green-100 border-green-200">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-green-700">{t('Approved')}</CardTitle>
                        <CheckCircle className="h-8 w-8 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-green-700">{defaultStats.approved_schedules}</div>
                    </CardContent>
                </Card>
                <Card className="bg-gradient-to-r from-blue-50 to-blue-100 border-blue-200">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-blue-700">{t('Completed')}</CardTitle>
                        <CheckCircle className="h-8 w-8 text-blue-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-blue-700">{defaultStats.completed_schedules}</div>
                    </CardContent>
                </Card>
                <Card className="bg-gradient-to-r from-yellow-50 to-yellow-100 border-yellow-200">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-yellow-700">{t('Pending')}</CardTitle>
                        <Clock className="h-8 w-8 text-yellow-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-yellow-700">{defaultStats.pending_schedules}</div>
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* My Calendar */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span className="flex items-center gap-2">
                                <CalendarDays className="h-5 w-5" />
                                {t('My Appointment Calendar')}
                            </span>

                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <CalendarView
                            events={calendar_events.map(event => ({
                                ...event,
                                color: event.status === 'pending' ? '#f59e0b' :
                                    event.status === 'approved' ? '#10b981' :
                                        event.status === 'reject' ? '#ef4444' :
                                            event.status === 'complete' ? '#3b82f6' : '#6b7280'
                            }))}
                        />
                    </CardContent>
                </Card>

                {/* Performance and Recent Schedules Combined */}
                <Card className="flex flex-col lg:col-span-1">
                    {/* Performance Section */}
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            {t('Status Distribution')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col lg:flex-row gap-4">
                            {/* Left: PieChart */}
                            <div className="flex-1">
                                {performance_chart.every(item => item.value === 0) ? (
                                    <PieChart
                                        data={[{ name: t('No Data'), value: 1, color: '#3b82f6' }]}
                                        dataKey="value"
                                        nameKey="name"
                                        height={200}
                                        donut={true}
                                        showTooltip={false}
                                        showLegend={false}
                                    />
                                ) : (
                                    <PieChart
                                        data={performance_chart}
                                        dataKey="value"
                                        nameKey="name"
                                        height={200}
                                        donut={true}
                                        showTooltip={true}
                                        showLegend={false}
                                    />
                                )}
                            </div>

                            {/* Right: Status Labels */}
                            <div className="flex flex-col justify-center gap-2">
                                <div className="flex justify-between items-center p-2 bg-blue-50 rounded">
                                    <span className='text-sm mr-2'>{t('Completed')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-blue-500 text-white`}>
                                        {defaultStats.completed_schedules}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center p-2 bg-green-50 rounded">
                                    <span className='text-sm'>{t('Approved')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-green-500 text-white`}>
                                        {defaultStats.approved_schedules}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center p-2 bg-yellow-50 rounded">
                                    <span className='text-sm'>{t('Pending')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800`}>
                                        {defaultStats.pending_schedules}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>

                    {/* My Recent Schedules Section */}
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 cursor-pointer hover:text-blue-600 transition-colors" onClick={() => router.get(route('appointment.schedules.index'))}>
                            <Clock className="h-5 w-5" />
                            {t('My Recent Schedules')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex-1 max-h-[500px] overflow-y-auto">
                        {recent_schedules.length > 0 ? (
                            recent_schedules.slice(0, 8).map((schedule) => (
                                <div key={schedule.id} className="flex justify-between items-center p-2 border rounded mb-2">
                                    <div className="flex-1">
                                        <p className="text-sm font-medium">{schedule.appointment_name}</p>
                                        <p className="text-xs text-muted-foreground">{schedule.name}</p>
                                        <p className="text-xs text-muted-foreground">{formatDate(schedule.date)} • {formatTime(schedule.start_time)}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {getStatusBadge(schedule.status)}
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="flex items-center justify-center h-40 text-gray-500">
                                <div className="text-center">
                                    <Clock className="h-12 w-12 mx-auto mb-3 opacity-40" />
                                    <p className="text-sm font-medium">{t('No assigned schedules')}</p>
                                    <p className="text-xs text-gray-400 mt-1">{t('Assigned schedules will appear here')}</p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>


        </AuthenticatedLayout>
    );
}
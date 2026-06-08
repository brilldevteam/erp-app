import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Calendar, CalendarDays, Users, CheckCircle, XCircle, Clock, Copy, Link, ChevronLeft, ChevronRight } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { toast } from 'sonner';
import { PieChart } from '@/components/charts/PieChart';
import { formatDate, formatTime } from '@/utils/helpers';
import CalendarView from '@/components/calendar-view';
import { ImageSlider } from '@/components/ui/image-slider';

interface AppointmentDashboardProps {
    stats: {
        total_appointments: number;
        total_approved: number;
        total_rejected: number;
        total_pending: number;
    };
    recent_appointments: Array<{
        id: number;
        encrypted_id: string;
        appointment_name: string;
        appointment_type: string;
        week_day: string;
        created_at: string;
    }>;
    recent_schedules: Array<{
        id: number;
        unique_id: string;
        name: string;
        date: string;
        start_time: string;
        status: string;
        appointment: {
            appointment_name: string;
        };
    }>;
    calendar_events: Array<{
        id: number;
        title: string;
        date: string;
        time: string;
        status: string;
        name: string;
    }>;
    chart_data: {
        total: number;
        approved: number;
        rejected: number;
        pending: number;
        complete: number;
    };
}

export default function AppointmentIndex({ stats, recent_appointments, recent_schedules, calendar_events, chart_data }: AppointmentDashboardProps) {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: any }>().props;
    const [currentSlide, setCurrentSlide] = useState(0);
    const [copiedCode, setCopiedCode] = useState<string | null>(null);

    const chartData = [
        { name: t('Complete'), value: chart_data.complete },
        { name: t('Approved'), value: chart_data.approved },
        { name: t('Pending'), value: chart_data.pending },
        { name: t('Rejected'), value: chart_data.rejected },
    ];

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

    const copyAppointmentLink = async (encryptedId: string) => {
        const userSlug = auth?.user?.slug || 'default';
        const link = route('appointment.public.book', { userSlug, encryptedId });
        const fullUrl = link.startsWith('http') ? link : window.location.origin + link;
        try {
            await navigator.clipboard.writeText(fullUrl);
            setCopiedCode(encryptedId);
            setTimeout(() => setCopiedCode(null), 2000);
        } catch (err) {
        }
    };

    const nextSlide = () => {
        setCurrentSlide((prev) => {
            const nextGroup = Math.floor(prev / 4) + 1;
            return nextGroup * 4 >= recent_appointments.length ? 0 : nextGroup * 4;
        });
    };

    const prevSlide = () => {
        setCurrentSlide((prev) => {
            const prevGroup = Math.floor(prev / 4) - 1;
            return prevGroup < 0 ? Math.floor((recent_appointments.length - 1) / 4) * 4 : prevGroup * 4;
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Appointment Dashboard') }]}
            pageTitle={t('Appointment Dashboard')}
        >
            <Head title={t('Appointment Dashboard')} />

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <Card className="bg-gradient-to-r from-pink-50 to-pink-100 border-pink-200 cursor-pointer hover:shadow-md transition-shadow" onClick={() => router.get(route('appointment.appointments.index'))}>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-pink-700">{t('Total Appointments')}</CardTitle>
                        <Calendar className="h-8 w-8 text-pink-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-pink-700">{stats.total_appointments}</div>
                    </CardContent>
                </Card>
                <Card className="bg-gradient-to-r from-green-50 to-green-100 border-green-200">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-green-700">{t('Total Approved')}</CardTitle>
                        <CheckCircle className="h-8 w-8 text-green-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-green-700">{stats.total_approved}</div>
                    </CardContent>
                </Card>
                <Card className="bg-gradient-to-r from-red-50 to-red-100 border-red-200">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-red-700">{t('Total Rejected')}</CardTitle>
                        <XCircle className="h-8 w-8 text-red-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-red-700">{stats.total_rejected}</div>
                    </CardContent>
                </Card>
                <Card className="bg-gradient-to-r from-yellow-50 to-yellow-100 border-yellow-200">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-yellow-700">{t('Total Pending')}</CardTitle>
                        <Clock className="h-8 w-8 text-yellow-600" />
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold text-yellow-700">{stats.total_pending}</div>
                    </CardContent>
                </Card>
            </div>

            {/* Appointments Slider */}
            <Card className="mb-6">
                <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                        <span>{t('Show Appointments')}</span>
                        <TooltipProvider>
                            <Tooltip delayDuration={0}>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => router.get(route('appointment.appointments.calendar'))}
                                    >
                                        <Calendar className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className='font-medium'>{t('Events list')}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    {recent_appointments.length > 0 ? (
                        <div className="relative">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                {recent_appointments.slice(currentSlide, currentSlide + 4).map((appointment) => (
                                    <Card key={appointment.id} className="border-l-4 border-l-primary">
                                        <CardContent className="p-4">
                                            <div className="flex items-center justify-between mb-2">
                                                <h4 className="font-semibold text-sm">{appointment.appointment_name}</h4>
                                                <TooltipProvider>
                                                    {auth.user?.permissions?.includes('copy-appointment-link') && (
                                                        <Tooltip>
                                                            <TooltipTrigger asChild>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => copyAppointmentLink(appointment.encrypted_id)}
                                                                    className={`h-6 w-6 p-0 transition-colors ${copiedCode === appointment.encrypted_id
                                                                        ? 'text-green-600 hover:text-green-700 bg-green-50'
                                                                        : 'text-purple-600 hover:text-purple-700'
                                                                        }`}
                                                                >
                                                                    {copiedCode === appointment.encrypted_id ? <Copy className="h-3 w-3" /> : <Link className="h-3 w-3" />}
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                <p>{copiedCode === appointment.encrypted_id ? t('Copied!') : t('Copy Link')}</p>
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    )}
                                                </TooltipProvider>
                                            </div>
                                            <p className="text-xs text-gray-600 mb-1">
                                                <span className="font-medium">{t('Type')}:</span> {appointment.appointment_type === '0' ? t('Paid') : t('Free')}
                                            </p>
                                            <p className="text-xs text-gray-600 mb-2">
                                                <span className="font-medium">{t('Weekday')}:</span> {JSON.parse(appointment.week_day || '[]').map(day => day.charAt(0).toUpperCase() + day.slice(1)).join(', ')}
                                            </p>
                                            <div className="flex items-center justify-between">
                                                <span className="text-xs text-gray-500">
                                                    {formatDate(appointment.created_at)}
                                                </span>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>

                            {/* Navigation buttons at bottom */}
                            {recent_appointments.length > 4 && (
                                <div className="flex items-center justify-center gap-4 mt-6">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={prevSlide}
                                        className="h-8 w-8"
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>

                                    <div className="flex gap-1.5">
                                        {Array.from({ length: Math.ceil(recent_appointments.length / 4) }).map((_, index) => (
                                            <button
                                                key={index}
                                                className={`w-2 h-2 rounded-full transition-all duration-200 ${Math.floor(currentSlide / 4) === index ? 'bg-primary scale-125' : 'bg-gray-300 hover:bg-gray-400'
                                                    }`}
                                                onClick={() => setCurrentSlide(index * 4)}
                                            />
                                        ))}
                                    </div>

                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={nextSlide}
                                        className="h-8 w-8"
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="text-center py-8 text-gray-500">
                            <Calendar className="h-12 w-12 mx-auto mb-2 opacity-50" />
                            <p>{t('No appointments found')}</p>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Appointments Calendar */}
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarDays className="h-5 w-5" />
                            {t('Appointments Calendar')}
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

                {/* Status Distribution and Recent Schedules Combined */}
                <Card className="flex flex-col lg:col-span-1">
                    {/* Status Distribution Section */}
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
                                {(chart_data.complete === 0 && chart_data.approved === 0 && chart_data.pending === 0 && chart_data.rejected === 0) ? (
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
                                        data={chartData}
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
                                    <span className='text-sm mr-2'>{t('Complete')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-blue-500 text-white`}>
                                        {chart_data.complete ?? 0}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center p-2 bg-green-50 rounded">
                                    <span className='text-sm'>{t('Approved')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-green-500 text-white`}>
                                        {stats.total_approved ?? 0}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center p-2 bg-yellow-50 rounded">
                                    <span className='text-sm'>{t('Pending')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-yellow-100 text-yellow-800`}>
                                        {stats.total_pending ?? 0}
                                    </span>
                                </div>
                                <div className="flex justify-between items-center p-2 bg-red-50 rounded">
                                    <span className='text-sm'>{t('Rejected')}</span>
                                    <span className={`px-2 py-1 rounded-full text-sm bg-red-100 text-red-800`}>
                                        {stats.total_rejected ?? 0}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>

                    {/* Recent Schedules Section */}
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 cursor-pointer hover:text-blue-600 transition-colors" onClick={() => router.get(route('appointment.schedules.index'))}>
                            <Clock className="h-5 w-5" />
                            {t('Recent Schedules')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex-1 max-h-[500px] overflow-y-auto">
                        {recent_schedules.length > 0 ? (
                            recent_schedules.slice(0, 8).map((schedule) => (
                                <div key={schedule.id} className="flex justify-between items-center p-2 border rounded mb-2">
                                    <div className="flex-1">
                                        <p className="text-sm font-medium">{schedule.appointment.appointment_name}</p>
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
                                    <p className="text-sm font-medium">{t('No recent schedules')}</p>
                                    <p className="text-xs text-gray-400 mt-1">{t('Schedules will appear here once created')}</p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

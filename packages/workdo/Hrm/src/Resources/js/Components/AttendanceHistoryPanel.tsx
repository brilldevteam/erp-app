import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, CalendarDays, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate, formatDateTime } from '@/utils/helpers';

type AttendanceFilter = 'today' | 'yesterday' | 'date' | 'month' | 'range' | 'all';

interface AttendanceFilters {
    filter: AttendanceFilter;
    date: string;
    month: string;
    range_from: string;
    range_to: string;
    label: string;
}

interface AttendanceSummary {
    total_records: number;
    present_count: number;
    half_day_count: number;
    absent_count: number;
    completed_count: number;
    working_count: number;
    net_hours: number;
    overtime_hours: number;
}

interface Props {
    history: any;
    filters: AttendanceFilters;
    summary: AttendanceSummary;
    canReview?: boolean;
}

export default function AttendanceHistoryPanel({ history, filters, summary, canReview = false }: Props) {
    const { t } = useTranslation();
    const [filter, setFilter] = useState<AttendanceFilter>(filters.filter);
    const [date, setDate] = useState(filters.date);
    const [month, setMonth] = useState(filters.month);
    const [from, setFrom] = useState(filters.range_from);
    const [to, setTo] = useState(filters.range_to);

    useEffect(() => {
        setFilter(filters.filter);
        setDate(filters.date);
        setMonth(filters.month);
        setFrom(filters.range_from);
        setTo(filters.range_to);
    }, [filters]);

    const navigate = (selectedFilter: AttendanceFilter) => {
        setFilter(selectedFilter);
        const params: Record<string, string> = { tab: 'attendance', attendance_filter: selectedFilter };
        if (selectedFilter === 'date') params.attendance_date = date;
        if (selectedFilter === 'month') params.attendance_month = month;
        if (selectedFilter === 'range') {
            params.attendance_from = from;
            params.attendance_to = to;
        }
        router.get(window.location.pathname, params, { preserveScroll: true, preserveState: false, replace: true });
    };

    const review = (id: number, decision: 'approved' | 'rejected') => {
        const note = window.prompt(t('Enter a required decision note'));
        if (!note?.trim()) return;
        router.put(route('hrm.attendance-corrections.review', id), { decision, decision_note: note }, { preserveScroll: true });
    };

    return <div className="space-y-4">
        <Card>
            <CardContent className="space-y-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="flex items-center gap-2 font-medium"><CalendarDays className="h-4 w-4" />{t('Attendance Period')}</p>
                        <p className="mt-1 text-sm text-muted-foreground">{filters.label}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {(['today', 'yesterday', 'month', 'all'] as AttendanceFilter[]).map((value) =>
                            <Button key={value} size="sm" variant={filter === value ? 'default' : 'outline'} onClick={() => navigate(value)}>
                                {t(value === 'month' ? 'This Month' : value === 'all' ? 'All Time' : value.charAt(0).toUpperCase() + value.slice(1))}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 border-t pt-4 md:grid-cols-[180px_1fr_auto] md:items-end">
                    <label className="space-y-1 text-sm">
                        <span>{t('Calendar Filter')}</span>
                        <select className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm" value={filter} onChange={(event) => {
                            const selectedFilter = event.target.value as AttendanceFilter;
                            setFilter(selectedFilter);
                            if (['today', 'yesterday', 'month', 'all'].includes(selectedFilter)) {
                                navigate(selectedFilter);
                            }
                        }}>
                            <option value="today">{t('Today')}</option>
                            <option value="yesterday">{t('Yesterday')}</option>
                            <option value="date">{t('Exact Date')}</option>
                            <option value="month">{t('Month')}</option>
                            <option value="range">{t('Custom Range')}</option>
                            <option value="all">{t('All Time')}</option>
                        </select>
                    </label>
                    <div className="grid gap-2 sm:grid-cols-2">
                        {filter === 'date' && <Input type="date" value={date} onChange={(event) => setDate(event.target.value)} />}
                        {filter === 'month' && <Input type="month" value={month} onChange={(event) => setMonth(event.target.value)} />}
                        {filter === 'range' && <><Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} /><Input type="date" value={to} min={from} onChange={(event) => setTo(event.target.value)} /></>}
                        {(filter === 'today' || filter === 'yesterday' || filter === 'all') && <p className="self-center text-sm text-muted-foreground">{t('Apply this quick period to the attendance history.')}</p>}
                    </div>
                    <div className="flex gap-2">
                        <Button size="sm" onClick={() => navigate(filter)} disabled={(filter === 'date' && !date) || (filter === 'month' && !month) || (filter === 'range' && (!from || !to || to < from))}>{t('Apply')}</Button>
                        <Button size="sm" variant="outline" onClick={() => navigate('month')}>{t('Reset')}</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div className="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-8">
            <Metric label={t('Records')} value={summary.total_records} />
            <Metric label={t('Present')} value={summary.present_count} />
            <Metric label={t('Half Day')} value={summary.half_day_count} />
            <Metric label={t('Absent')} value={summary.absent_count} />
            <Metric label={t('Completed')} value={summary.completed_count} />
            <Metric label={t('Working')} value={summary.working_count} />
            <Metric label={t('Net Hours')} value={`${summary.net_hours}h`} />
            <Metric label={t('Overtime')} value={`${summary.overtime_hours}h`} />
        </div>

        {!history?.data?.length && <div className="py-10 text-center text-sm text-muted-foreground">{t('No attendance records found for this period')}</div>}

        {history?.data?.map((attendance: any) => <Card key={attendance.id} className={attendance.is_abnormally_long ? 'border-amber-300' : ''}>
            <CardContent className="space-y-3 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div><p className="font-medium">{formatDate(attendance.date)}</p><p className="text-xs text-muted-foreground">{attendance.clock_in ? formatDateTime(attendance.clock_in) : '-'} – {attendance.clock_out ? formatDateTime(attendance.clock_out) : t('Open')}</p></div>
                    <div className="flex items-center gap-2"><span className="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium">{t(attendance.work_status || 'completed')}</span>{attendance.is_manual && <span className="rounded-full bg-violet-100 px-2 py-1 text-xs text-violet-800">{t('Manual')}</span>}{attendance.is_abnormally_long && <AlertTriangle className="h-4 w-4 text-amber-600" />}</div>
                </div>
                <div className="grid grid-cols-2 gap-2 text-sm md:grid-cols-4"><Metric label={t('Net Worked')} value={`${attendance.total_hour || 0}h`} /><Metric label={t('Unpaid Pauses')} value={`${attendance.break_hour || 0}h`} /><Metric label={t('Official Duty')} value={`${((attendance.paid_outside_seconds || 0) / 3600).toFixed(2)}h`} /><Metric label={t('Overtime')} value={`${attendance.overtime_hours || 0}h`} /></div>
                {attendance.work_update && <div className="rounded-md bg-slate-50 p-3"><p className="text-xs font-medium">{t('Daily Work Update')}</p><p className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">{attendance.work_update}</p></div>}
                {!!attendance.intervals?.length && <div className="space-y-1"><p className="text-xs font-medium">{t('Timeline')}</p>{attendance.intervals.map((interval: any) => <div key={interval.id} className="flex flex-wrap justify-between gap-2 border-l-2 pl-3 text-xs"><span className="flex items-center gap-1"><MapPin className="h-3 w-3" />{t(interval.reason.replaceAll('_', ' '))}{interval.details ? ` — ${interval.details}` : ''}</span><span className="text-muted-foreground">{formatDateTime(interval.started_at)} – {interval.ended_at ? formatDateTime(interval.ended_at) : t('Now')} · {interval.counts_as_work ? t('Paid') : t('Unpaid')}</span></div>)}</div>}
                {!!attendance.correction_requests?.length && <div className="space-y-2 border-t pt-3"><p className="text-xs font-medium">{t('Correction Requests')}</p>{attendance.correction_requests.map((request: any) => <div key={request.id} className="rounded-md border p-3 text-sm"><div className="flex flex-wrap justify-between gap-2"><span>{request.requester?.name}: {request.reason}</span><span className="font-medium">{t(request.status)}</span></div>{request.decision_note && <p className="mt-1 text-xs text-muted-foreground">{t('Decision')}: {request.decision_note}</p>}{canReview && request.status === 'pending' && <div className="mt-2 flex gap-2"><Button size="sm" onClick={() => review(request.id, 'approved')}>{t('Approve')}</Button><Button size="sm" variant="destructive" onClick={() => review(request.id, 'rejected')}>{t('Reject')}</Button></div>}</div>)}</div>}
                {canReview && !!attendance.action_logs?.length && <details className="border-t pt-2 text-xs"><summary className="cursor-pointer font-medium">{t('Audit Trail')}</summary><div className="mt-2 space-y-1">{attendance.action_logs.map((log: any) => <div key={log.id} className="flex justify-between rounded bg-slate-50 p-2"><span>{log.actor?.name || t('System')} · {t(log.action.replaceAll('_', ' '))}</span><span>{formatDateTime(log.created_at)}</span></div>)}</div></details>}
            </CardContent>
        </Card>)}
        {history?.links && history.last_page > 1 && <div className="flex flex-wrap gap-1">{history.links.map((link: any, index: number) => <Button key={index} size="sm" variant={link.active ? 'default' : 'outline'} disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
    </div>;
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return <div className="rounded-md bg-slate-50 p-2"><p className="text-xs text-muted-foreground">{label}</p><p className="font-medium">{value}</p></div>;
}

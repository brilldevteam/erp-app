import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Clock, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDate, formatDateTime } from '@/utils/helpers';

export default function AttendanceHistoryPanel({ history, canReview = false }: { history: any; canReview?: boolean }) {
    const { t } = useTranslation();

    const review = (id: number, decision: 'approved' | 'rejected') => {
        const note = window.prompt(t('Enter a required decision note'));
        if (!note?.trim()) return;
        router.put(route('hrm.attendance-corrections.review', id), { decision, decision_note: note }, { preserveScroll: true });
    };

    if (!history?.data?.length) return <div className="py-10 text-center text-sm text-muted-foreground">{t('No attendance records found')}</div>;

    return <div className="space-y-3">
        {history.data.map((attendance: any) => <Card key={attendance.id} className={attendance.is_abnormally_long ? 'border-amber-300' : ''}>
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
        {history.links && <div className="flex flex-wrap gap-1">{history.links.map((link: any, index: number) => <Button key={index} size="sm" variant={link.active ? 'default' : 'outline'} disabled={!link.url} onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div>}
    </div>;
}

function Metric({ label, value }: { label: string; value: string }) {
    return <div className="rounded-md bg-slate-50 p-2"><p className="text-xs text-muted-foreground">{label}</p><p className="font-medium">{value}</p></div>;
}

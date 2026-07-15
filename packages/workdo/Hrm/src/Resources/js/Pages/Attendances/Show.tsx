import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Clock, MapPin } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Attendance } from './types';
import { formatDate, formatTime, formatDateTime, formatCurrency } from '@/utils/helpers';
import { useTimeClockDeviceAccess } from '../../Hooks/useTimeClockDeviceAccess';

interface ViewAttendanceProps {
    attendance: Attendance;
    onSuccess: () => void;
}

export default function View({ attendance, onSuccess }: ViewAttendanceProps) {
    const { t } = useTranslation();
    const { auth, timeClockDeviceAccess } = usePage<any>().props;
    const deviceAccess = useTimeClockDeviceAccess(timeClockDeviceAccess);
    const [correctionOpen, setCorrectionOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [requestedClockIn, setRequestedClockIn] = useState('');
    const [requestedClockOut, setRequestedClockOut] = useState('');

    const formatStatus = (status: string) => {
        return status.split(' ').map(word =>
            word.charAt(0).toUpperCase() + word.slice(1)
        ).join(' ');
    };

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <Clock className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Attendance Details')}</DialogTitle>
                    </div>
                </div>
            </DialogHeader>

            <div className="overflow-y-auto flex-1 p-4 space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Employee Name')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.user?.name || '-'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Shift')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.shift?.shift_name || '-'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Clock In Time')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.clock_in ? formatDateTime(attendance.clock_in) : '-'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Clock Out Time')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.clock_out ? formatDateTime(attendance.clock_out) : '-'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Break Hours')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.break_hour ? `${attendance.break_hour}h` : '0h'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Total Hours')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.total_hour ? `${attendance.total_hour}h` : '0h'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Overtime Hours')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.overtime_hours ? `${attendance.overtime_hours}h` : '0h'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Overtime Amount')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">
                            {attendance.overtime_amount ? formatCurrency(attendance.overtime_amount) : 0}
                        </p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Date')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.date ? formatDate(attendance.date) : '-'}</p>
                    </div>
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Status')}</label>
                        <div className="rounded">
                            <span className={`px-2 py-1 rounded-full text-xs ${attendance.status === 'present' ? 'bg-green-100 text-green-800 text-sm font-medium' :
                                        attendance.status === 'half day' ? 'bg-yellow-100 text-yellow-800 text-xs font-medium' :
                                        attendance.status === 'absent' ? 'bg-red-100 text-red-800 text-sm font-medium' :
                                            'bg-gray-100 text-gray-800'
                                }`}>
                                {formatStatus(attendance.status || 'Unknown')}
                            </span>
                        </div>
                    </div>
                </div>

                {attendance.notes && (
                    <div className="space-y-2">
                        <label className="text-sm font-medium text-gray-700">{t('Notes')}</label>
                        <p className="text-sm text-gray-900 bg-gray-50 p-2 rounded">{attendance.notes}</p>
                    </div>
                )}
                {attendance.work_update && <div className="space-y-2"><label className="text-sm font-medium text-gray-700">{t('Daily Work Update')}</label><p className="whitespace-pre-wrap rounded bg-gray-50 p-3 text-sm">{attendance.work_update}</p></div>}

                {!!attendance.intervals?.length && <div className="space-y-2"><label className="text-sm font-medium text-gray-700">{t('Timeline')}</label>{attendance.intervals.map(interval => <div key={interval.id} className="flex flex-wrap justify-between gap-2 rounded border p-3 text-sm"><span className="flex items-center gap-2"><MapPin className="h-4 w-4" />{t(interval.reason.replaceAll('_', ' '))}{interval.details ? ` — ${interval.details}` : ''}</span><span className="text-muted-foreground">{formatDateTime(interval.started_at)} – {interval.ended_at ? formatDateTime(interval.ended_at) : t('Now')} · {interval.counts_as_work ? t('Paid') : t('Unpaid')}</span></div>)}</div>}
                {auth.user?.permissions?.includes('review-attendance-corrections') && !!attendance.action_logs?.length && <div className="space-y-2"><label className="text-sm font-medium text-gray-700">{t('Audit Trail')}</label>{attendance.action_logs.map((log: any) => <div key={log.id} className="flex justify-between gap-2 rounded bg-slate-50 p-2 text-xs"><span>{log.actor?.name || t('System')} · {t(log.action.replaceAll('_', ' '))}</span><span>{formatDateTime(log.created_at)}</span></div>)}</div>}

                {deviceAccess.allowed && auth.user?.permissions?.includes('request-attendance-correction') && attendance.work_status === 'completed' && <div className="space-y-3 border-t pt-4">{!correctionOpen ? <Button variant="outline" onClick={() => setCorrectionOpen(true)}>{t('Request Correction')}</Button> : <><p className="text-sm font-medium">{t('Request Attendance Correction')}</p><div className="grid gap-3 md:grid-cols-2"><div><label className="text-xs">{t('Requested Clock In')}</label><Input type="datetime-local" value={requestedClockIn} onChange={event => setRequestedClockIn(event.target.value)} /></div><div><label className="text-xs">{t('Requested Clock Out')}</label><Input type="datetime-local" value={requestedClockOut} onChange={event => setRequestedClockOut(event.target.value)} /></div></div><Textarea value={reason} onChange={event => setReason(event.target.value)} placeholder={t('Explain why this correction is needed')} /><div className="flex gap-2"><Button disabled={!reason.trim() || (!requestedClockIn && !requestedClockOut)} onClick={() => router.post(route('hrm.attendances.corrections.store', attendance.id), { requested_clock_in: requestedClockIn || null, requested_clock_out: requestedClockOut || null, reason }, { preserveScroll: true, onSuccess: onSuccess })}>{t('Submit Request')}</Button><Button variant="outline" onClick={() => setCorrectionOpen(false)}>{t('Cancel')}</Button></div></>}</div>}

                {!!attendance.correction_requests?.length && <div className="space-y-2 border-t pt-4"><label className="text-sm font-medium">{t('Correction Requests')}</label>{attendance.correction_requests.map((request: any) => <div key={request.id} className="rounded border p-3 text-sm"><div className="flex justify-between gap-2"><span>{request.requester?.name}: {request.reason}</span><span className="font-medium">{t(request.status)}</span></div>{request.decision_note && <p className="mt-1 text-xs text-muted-foreground">{request.decision_note}</p>}{auth.user?.permissions?.includes('review-attendance-corrections') && request.status === 'pending' && <div className="mt-2 flex gap-2"><Button size="sm" onClick={() => { const note = window.prompt(t('Enter a required decision note')); if (note?.trim()) router.put(route('hrm.attendance-corrections.review', request.id), { decision: 'approved', decision_note: note }, { preserveScroll: true, onSuccess }); }}>{t('Approve')}</Button><Button size="sm" variant="destructive" onClick={() => { const note = window.prompt(t('Enter a required decision note')); if (note?.trim()) router.put(route('hrm.attendance-corrections.review', request.id), { decision: 'rejected', decision_note: note }, { preserveScroll: true, onSuccess }); }}>{t('Reject')}</Button></div>}</div>)}</div>}
            </div>
        </DialogContent>
    );
}

import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Clock, Pause, Play, Save, Square, MapPin, AlertTriangle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

type Interval = {
    id: number;
    reason: string;
    details?: string;
    counts_as_work: boolean;
    started_at: string;
    ended_at?: string;
};

export type TimeClockStatus = {
    id?: number;
    work_status: 'not_started' | 'working' | 'paused' | 'completed';
    clock_in_time?: string;
    clock_out_time?: string;
    elapsed_seconds?: number;
    unpaid_pause_seconds?: number;
    paid_outside_seconds?: number;
    worked_seconds?: number;
    work_update?: string;
    is_abnormally_long?: boolean;
    server_time?: string;
    timeline?: Interval[];
};

interface Props {
    initialStatus?: TimeClockStatus | null;
    permissions?: string[];
}

const formatDuration = (seconds = 0) => {
    const safe = Math.max(0, Math.floor(seconds));
    const hours = Math.floor(safe / 3600);
    const minutes = Math.floor((safe % 3600) / 60);
    const secs = safe % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
};

const reasonLabel = (reason: string) => ({
    break: 'Break', personal: 'Personal', official_duty: 'Official Duty Outside', other: 'Other',
}[reason] || reason);

export default function TimeClockCard({ initialStatus, permissions = [] }: Props) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<TimeClockStatus>(initialStatus || { work_status: 'not_started' });
    const [tick, setTick] = useState(0);
    const [processing, setProcessing] = useState(false);
    const [pauseOpen, setPauseOpen] = useState(false);
    const [reason, setReason] = useState('break');
    const [details, setDetails] = useState('');
    const [workUpdate, setWorkUpdate] = useState(initialStatus?.work_update || '');

    const isActive = status.work_status === 'working' || status.work_status === 'paused';
    const mayClockIn = permissions.includes('clock-in');
    const mayClockOut = permissions.includes('clock-out');
    const mayPause = permissions.includes('pause-attendance');
    const mayUpdate = permissions.includes('update-own-work-update');

    const fetchStatus = async () => {
        try {
            const response = await fetch(route('hrm.attendances.clock-status'), { headers: { Accept: 'application/json' } });
            if (response.ok) {
                const data = await response.json();
                setStatus(data);
                setWorkUpdate(data.work_update || '');
            }
        } catch {
            // The next poll or Inertia navigation will restore authoritative state.
        }
    };

    useEffect(() => {
        const timer = window.setInterval(() => setTick(value => value + 1), 1000);
        const poll = window.setInterval(fetchStatus, 30000);
        return () => { window.clearInterval(timer); window.clearInterval(poll); };
    }, []);

    useEffect(() => {
        if (initialStatus) {
            setStatus(initialStatus);
            setWorkUpdate(initialStatus.work_update || '');
        }
    }, [initialStatus]);

    const liveStatus = useMemo(() => {
        if (!isActive || !status.server_time) return status;
        const added = Math.max(0, Math.floor((Date.now() - new Date(status.server_time).getTime()) / 1000));
        const openInterval = status.timeline?.find(interval => !interval.ended_at);
        const unpaidAdded = status.work_status === 'paused' && openInterval && !openInterval.counts_as_work ? added : 0;
        const paidOutsideAdded = status.work_status === 'paused' && openInterval?.counts_as_work ? added : 0;
        return {
            ...status,
            elapsed_seconds: (status.elapsed_seconds || 0) + added,
            unpaid_pause_seconds: (status.unpaid_pause_seconds || 0) + unpaidAdded,
            paid_outside_seconds: (status.paid_outside_seconds || 0) + paidOutsideAdded,
            worked_seconds: (status.worked_seconds || 0) + added - unpaidAdded,
        };
    }, [status, tick, isActive]);

    const submit = (method: 'post' | 'put', endpoint: string, data: Record<string, unknown> = {}) => {
        setProcessing(true);
        router[method](endpoint, data, {
            preserveScroll: true,
            onSuccess: fetchStatus,
            onFinish: () => setProcessing(false),
        });
    };

    const submitPause = () => {
        submit('post', route('hrm.attendances.pause'), { reason, details });
        setPauseOpen(false);
        setDetails('');
    };

    const statusLabel = status.work_status === 'not_started' ? t('Not Started')
        : status.work_status === 'working' ? t('Working')
        : status.work_status === 'paused' ? t('Paused') : t('Completed');

    return (
        <>
            <Card className="border-slate-200 shadow-sm">
                <CardHeader className="pb-3">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <CardTitle className="flex items-center gap-2"><Clock className="h-5 w-5" />{t('My Time Clock')}</CardTitle>
                        <span className={`rounded-full px-3 py-1 text-sm font-medium ${status.work_status === 'working' ? 'bg-green-100 text-green-800' : status.work_status === 'paused' ? 'bg-amber-100 text-amber-800' : status.work_status === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700'}`}>{statusLabel}</span>
                    </div>
                </CardHeader>
                <CardContent className="space-y-5">
                    {status.is_abnormally_long && <div className="flex gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800"><AlertTriangle className="h-5 w-5 shrink-0" />{t('This is an unusually long attendance session and will be visible to HR.')}</div>}

                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Metric label={t('Net Worked')} value={formatDuration(liveStatus.worked_seconds)} />
                        <Metric label={t('Unpaid Pauses')} value={formatDuration(liveStatus.unpaid_pause_seconds)} />
                        <Metric label={t('Official Duty')} value={formatDuration(liveStatus.paid_outside_seconds)} />
                        <Metric label={t('Elapsed Time')} value={formatDuration(liveStatus.elapsed_seconds)} />
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {status.work_status === 'not_started' && mayClockIn && <Button disabled={processing} onClick={() => submit('post', route('hrm.attendances.clock-in'))}><Play className="mr-2 h-4 w-4" />{t('Clock In')}</Button>}
                        {status.work_status === 'working' && mayPause && <Button variant="outline" disabled={processing} onClick={() => setPauseOpen(true)}><Pause className="mr-2 h-4 w-4" />{t('Pause')}</Button>}
                        {status.work_status === 'paused' && mayPause && <Button disabled={processing} onClick={() => submit('post', route('hrm.attendances.resume'))}><Play className="mr-2 h-4 w-4" />{t('Resume')}</Button>}
                        {isActive && mayClockOut && <Button variant="destructive" disabled={processing} onClick={() => submit('post', route('hrm.attendances.clock-out'), { work_update: workUpdate })}><Square className="mr-2 h-4 w-4" />{t('Clock Out')}</Button>}
                    </div>

                    {isActive && mayUpdate && <div className="space-y-2"><label className="text-sm font-medium">{t('Daily Work Update')} <span className="font-normal text-muted-foreground">({t('optional')})</span></label><Textarea value={workUpdate} onChange={event => setWorkUpdate(event.target.value)} maxLength={5000} rows={4} placeholder={t('What did you work on today?')} /><Button variant="outline" size="sm" disabled={processing} onClick={() => submit('put', route('hrm.attendances.work-update'), { work_update: workUpdate })}><Save className="mr-2 h-4 w-4" />{t('Save Update')}</Button></div>}
                    {status.work_status === 'completed' && status.work_update && <div className="rounded-lg border bg-slate-50 p-3"><p className="mb-1 text-sm font-medium">{t('Daily Work Update')}</p><p className="whitespace-pre-wrap text-sm text-muted-foreground">{status.work_update}</p></div>}

                    {!!status.timeline?.length && <div className="space-y-2"><p className="text-sm font-medium">{t('Today’s Timeline')}</p>{status.timeline.map(interval => <div key={interval.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"><div className="flex items-center gap-2"><MapPin className="h-4 w-4 text-muted-foreground" /><span className="font-medium">{t(reasonLabel(interval.reason))}</span>{interval.details && <span className="text-muted-foreground">— {interval.details}</span>}</div><div className="text-muted-foreground">{new Date(interval.started_at).toLocaleTimeString()} – {interval.ended_at ? new Date(interval.ended_at).toLocaleTimeString() : t('Now')} · {interval.counts_as_work ? t('Paid') : t('Unpaid')}</div></div>)}</div>}
                </CardContent>
            </Card>

            <Dialog open={pauseOpen} onOpenChange={setPauseOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>{t('Pause Work Timer')}</DialogTitle></DialogHeader>
                    <div className="space-y-4">
                        <div><label className="mb-2 block text-sm font-medium">{t('Reason')}</label><Select value={reason} onValueChange={setReason}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="break">{t('Break')}</SelectItem><SelectItem value="personal">{t('Personal')}</SelectItem><SelectItem value="official_duty">{t('Official Duty Outside')}</SelectItem><SelectItem value="other">{t('Other')}</SelectItem></SelectContent></Select></div>
                        <div><label className="mb-2 block text-sm font-medium">{t('Details')} {reason === 'other' ? '*' : `(${t('optional')})`}</label><Textarea value={details} onChange={event => setDetails(event.target.value)} maxLength={1000} /></div>
                        <p className="text-xs text-muted-foreground">{reason === 'official_duty' ? t('Official duty continues to count as working time.') : t('This pause will be deducted from working time.')}</p>
                        <Button className="w-full" disabled={processing || (reason === 'other' && !details.trim())} onClick={submitPause}>{t('Pause Timer')}</Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return <div className="rounded-lg bg-slate-50 p-3"><p className="text-xs text-muted-foreground">{label}</p><p className="mt-1 font-mono text-lg font-semibold">{value}</p></div>;
}

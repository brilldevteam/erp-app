import { FormEvent, Fragment, useEffect, useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Input as BaseInput } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { TimePicker } from '@/components/ui/time-picker';
import { CalendarDays, CheckCircle2, ChevronDown, ChevronUp, Clock3, Eye, FileCheck2, FolderKanban, Gauge, ListChecks, Pencil, Plus, Settings2, Trash2, Video } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import JobForm from './Jobs/JobForm';
import SettingsForm from './Settings/SettingsForm';

type Kind = 'shoot'|'deliverable'|'revision'|'time'|'evidence';
type Field = [string,string,string?,string[]?];
const opts:any = {
  yes:['Yes','Partial','No','N/A'], shoot:['Planned','Unplanned'], content:['Reel','Static','Other'], via:['WhatsApp','Email','Meeting / MOM','Other'],
  revision:['Client Creative Change','Medical Correction','Brill Correction','Additional Scope','Text / Subtitle','B-roll / Insert','Other'],
  delivery:['Awaiting Inputs','Ready for Production','Editing','V1 Sent','Waiting for DOC','Revision in Progress','Under Review','Approved','Delivered','On Hold'],
  activity:['Shooting','Editing','Revision','Static Design','Planning','Meeting','Travel / Setup','Administration','Other'], scope:['Included Scope','Extra Support','Out of Scope','Internal'],
  linked:['Shoot','Deliverable','Revision','General'], evidenceLinked:['Shoot','Deliverable','Revision','Script / Plan','Approval','Meeting / MOM','Other'],
  evidence:['WhatsApp Screenshot','Email','PDF / Document','Video / Raw File','Drive Folder','Approval Screenshot','Other']
};
const fields:Record<Kind,Field[]> = {
 shoot:[['shoot_date','Shoot Date','date'],['branch_location','Branch / Location'],['shoot_type','Shoot Type','select',opts.shoot],['shoot_confirmed_at','Shoot Confirmed','datetime-local'],['doctor_subject','Doctor / Subject'],['planned_start','Planned Start','time'],['actual_start','Actual Start','time'],['actual_end','Actual End','time'],['content_plan_received','Content Plan Received','select',['Yes','Partial','No']],['script_received_at','Script / Plan Received','datetime-local'],['script_type','Script Type','select',['Video Script','Speech Script','None']],['planning_contribution','Brill Contributed to Planning','select',['Yes','No']],['b_roll_requirements','B-roll Requirements','select',opts.yes],['editing_references','Editing References','select',opts.yes],['props_requirements','Props / Special Requirements','select',opts.yes],['reels_shot','Reels Shot','number'],['additional_topics','Additional Topics Added on Shoot Day','textarea'],['shoot_notes','Shoot Notes','textarea'],['proof_image','Proof Image','image'],['evidence_folder_link','Evidence Folder Link','url'],['client_confirmation','DOC Confirmation','select',['Confirmed','No Response','Disputed','Not Sent']]],
 deliverable:[['content_type','Content Type','select',opts.content],['content_name','Reel / Content Name'],['shoot_id','Shoot ID'],['doctor_department','Doctor / Department'],['shoot_date','Shoot Date','date'],['script_received_at','Script / Plan Received','datetime-local'],['b_roll_defined','B-roll / Inserts Defined','select',opts.yes],['editing_reference','Editing Reference Provided','select',opts.yes],['input_status','Input Status','select',['Awaiting Inputs','Ready for Production']],['complete_inputs_received_at','Complete Inputs Received','datetime-local'],['editing_started_at','Editing Started','datetime-local'],['v1_delivered_at','V1 Delivered','datetime-local'],['v1_evidence_link','V1 Evidence Link','url'],['client_v1_response_at','Client V1 Response','datetime-local'],['v1_response_status','V1 Response Status','select',['Approved','Revision Required','Still Under Review','No Response']],['revision_1_requested_at','Revision 01 Requested','datetime-local'],['revision_1_summary','Revision 01 Summary','textarea'],['revision_1_category','Revision 01 Category','select',opts.revision],['revision_1_delivered_at','Revision 01 Delivered','datetime-local'],['revision_2_requested_at','Revision 02 Requested','datetime-local'],['revision_2_summary','Revision 02 Summary','textarea'],['revision_2_category','Revision 02 Category','select',opts.revision],['revision_2_delivered_at','Revision 02 Delivered','datetime-local'],['revision_3_requested_at','Revision 03 Requested','datetime-local'],['revision_3_summary','Revision 03 Summary','textarea'],['revision_3_category','Revision 03 Category','select',opts.revision],['revision_3_delivered_at','Revision 03 Delivered','datetime-local'],['final_approval_date','Final Approval Date','date'],['final_delivery_date','Final Delivery Date','date'],['final_evidence_link','Final Evidence Link','url'],['current_status','Current Status','select',opts.delivery],['evidence_folder_link','Evidence Folder Link','url'],['notes','Notes','textarea']],
 revision:[['deliverable_id','Deliverable ID'],['revision_no','Revision No.','number'],['requested_at','Requested Date & Time','datetime-local'],['requested_by','Requested By'],['requested_via','Requested Via','select',opts.via],['request_summary','Revision Request Summary','textarea'],['category','Category','select',opts.revision],['brill_started_at','Brill Started','datetime-local'],['delivered_at','Delivered','datetime-local'],['client_next_response_at','Client Next Response','datetime-local'],['evidence_link','Evidence Link','url'],['notes','Notes','textarea']],
 time:[['date','Date','date'],['team_member','Team Member'],['activity_type','Activity Type','select',opts.activity],['linked_type','Linked Type','select',opts.linked],['linked_id','Linked ID'],['start_time','Start Time','time'],['end_time','End Time','time'],['classification','Classification','select',opts.scope],['notes','Notes','textarea'],['evidence_link','Evidence Link','url']],
 evidence:[['linked_type','Linked Type','select',opts.evidenceLinked],['linked_id','Linked ID'],['evidence_at','Evidence Date & Time','datetime-local'],['evidence_type','Evidence Type','select',opts.evidence],['description','Description','textarea'],['file_folder_link','File / Folder Link','url'],['captured_by','Captured By'],['notes','Notes','textarea']]
};
const titles:any={shoot:'Shooting Log',deliverable:'Deliverables',revision:'Revisions',time:'Time Log',evidence:'Evidence Register'};
const calculated: Record<Kind, Field[]> = {
 shoot: [['total_hours','Total Hours'],['contract_hours','Contract Hours'],['extra_hours','Extra Hours'],['working_days_before','Working Days Before Shoot'],['lead_requirement','Lead-time Requirement']],
 deliverable: [['working_days_before','Working Days Before Shoot'],['script_lead_category','Script Lead Category'],['v1_turnaround_days','V1 Brill Turnaround WD'],['client_review_days','Client Review WD'],['total_revisions','Total Revisions'],['revision_4_plus','Revision 4+ Flag'],['total_brill_days','Total Brill Work Days'],['total_client_wait_days','Total Client Wait Days']],
 revision: [['included_status','Within Included Revisions?'],['brill_turnaround_days','Brill Turnaround WD'],['client_review_days','Client Review WD']],
 time: [['total_hours','Total Hours']],
 evidence: [],
};

const displayFields = (kind: Kind) => [...fields[kind].filter(([, , type]) => type !== 'image'), ...calculated[kind]];

function ProofImageInput({ value, onChange }: { value?: File|string; onChange: (value: File|string) => void }) {
    const [preview, setPreview] = useState('');

    useEffect(() => {
        if (value instanceof File) {
            const objectUrl = URL.createObjectURL(value);
            setPreview(objectUrl);
            return () => URL.revokeObjectURL(objectUrl);
        }

        setPreview(typeof value === 'string' && value ? `/storage/${value}` : '');
    }, [value]);

    return <div className="space-y-2">
        <BaseInput type="file" accept="image/jpeg,image/png,image/webp" onChange={event => onChange(event.target.files?.[0] || '')} />
        <p className="text-xs text-muted-foreground">JPG, PNG or WebP. Maximum 5 MB.</p>
        {preview && <img src={preview} alt="Proof preview" className="h-28 w-full rounded-lg border object-contain bg-muted/30" />}
    </div>;
}

function Input({ type, value, onChange, ...props }: any) {
    const notify = (nextValue: string) => onChange?.({ target: { value: nextValue } });

    if (type === 'date' || type === 'datetime-local') {
        return <DateTimeField value={value} onChange={notify} />;
    }

    if (type === 'time') {
        return <TimePicker value={value} onChange={notify} placeholder="Select time" />;
    }

    if (type === 'image') {
        return <ProofImageInput value={value} onChange={notify} />;
    }

    return <BaseInput type={type} value={value} onChange={onChange} {...props} />;
}

function DateTimeField({ value, onChange }: { value?: string; onChange: (value: string) => void }) {
    const normalized = value?.replace(' ', 'T') || '';
    const [date = '', time = ''] = normalized.split('T');
    const updateDate = (nextDate: string) => onChange(nextDate ? `${nextDate}T${time || '00:00'}` : '');
    const updateTime = (nextTime: string) => onChange(date ? `${date}T${nextTime}` : '');

    return <div className="grid grid-cols-[minmax(0,1fr)_minmax(0,0.72fr)] gap-2">
        <DatePicker value={date} onChange={updateDate} placeholder="Select date" />
        <TimePicker value={time} onChange={updateTime} placeholder="Select time" disabled={!date} />
    </div>;
}

const summaryKeys: Record<Kind, string[]> = {
    shoot: ['shoot_date', 'branch_location', 'shoot_type', 'doctor_subject', 'actual_start', 'actual_end'],
    deliverable: ['content_name', 'content_type', 'doctor_department', 'current_status', 'final_delivery_date'],
    revision: ['deliverable_id', 'revision_no', 'category', 'requested_at', 'delivered_at'],
    time: ['date', 'team_member', 'activity_type', 'classification', 'total_hours'],
    evidence: ['evidence_at', 'linked_type', 'linked_id', 'evidence_type', 'captured_by'],
};

const fieldFor = (kind: Kind, key: string) => displayFields(kind).find(([fieldKey]) => fieldKey === key);

function formatRecordValue(value: any, type?: string) {
    if (value === null || value === undefined || value === '') return '-';

    if (type === 'date' || type === 'datetime-local') {
        const date = new Date(String(value).replace(' ', 'T'));
        if (!Number.isNaN(date.getTime())) {
            return new Intl.DateTimeFormat('en-GB', {
                day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true,
            }).format(date);
        }
    }

    if (type === 'time' && /^\d{2}:\d{2}/.test(String(value))) {
        const [hours, minutes] = String(value).split(':').map(Number);
        return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
            .format(new Date(2000, 0, 1, hours, minutes));
    }

    return String(value);
}

function formatDateOnly(value?: string) {
    if (!value) return '-';
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
}

function RecordValue({ value, type }: { value: any; type?: string }) {
    if (type === 'url' && value) {
        return <a href={value} target="_blank" rel="noreferrer" className="text-primary underline-offset-4 hover:underline">{value}</a>;
    }

    return <>{formatRecordValue(value, type)}</>;
}

function Manager({ kind, items, settings, jobId, jobs, nextRecordKey }: any) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [edit, setEdit] = useState<any>();
    const [expanded, setExpanded] = useState<number | null>(null);
    const blank = Object.fromEntries(fields[kind as Kind].map(field => [field[0], '']));
    const form = useForm({ production_job_id: jobId, record_key: '', recorded_at: '', status: '', data: blank });
    const compactFields = summaryKeys[kind as Kind].map(key => fieldFor(kind, key)).filter(Boolean) as Field[];

    const launch = (record?: any) => {
        if (!jobId) return;
        setEdit(record);
        form.setData({
            production_job_id: record?.production_job_id || jobId,
            record_key: record?.record_key || '',
            recorded_at: record?.recorded_at?.slice(0, 16) || '',
            status: record?.status || '',
            data: { ...blank, ...record?.data, proof_image: record?.data?.proof_image_path || '' },
        });
        setOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const data: any = { ...form.data.data };
        if (kind === 'shoot' && data.actual_start && data.actual_end) {
            let hours = (new Date(`2000-01-01T${data.actual_end}`).getTime() - new Date(`2000-01-01T${data.actual_start}`).getTime()) / 3600000;
            if (hours < 0) hours += 24;
            data.total_hours = +hours.toFixed(2);
            data.contract_hours = +settings.included_hours_per_shoot;
            data.extra_hours = Math.max(0, data.total_hours - data.contract_hours);
            data.lead_requirement = ['Unplanned', 'Urgent'].includes(data.shoot_type)
                ? 'N/A - Unplanned'
                : (!data.script_received_at ? 'No - Not Received' : (Math.floor((new Date(data.shoot_date).getTime() - new Date(data.script_received_at).getTime()) / 86400000) >= settings.required_lead_days ? 'Yes' : 'No'));
        }
        if (kind === 'time' && data.start_time && data.end_time) {
            let hours = (new Date(`2000-01-01T${data.end_time}`).getTime() - new Date(`2000-01-01T${data.start_time}`).getTime()) / 3600000;
            if (hours < 0) hours += 24;
            data.total_hours = +hours.toFixed(2);
        }
        if (kind === 'revision') data.included_status = +data.revision_no <= +settings.included_revisions ? 'Yes' : 'No - Revision 4+';

        form.transform(values => ({
            ...values,
            ...(edit ? { _method: 'put' } : {}),
            data,
            recorded_at: values.recorded_at || data.shoot_date || data.date || data.requested_at || data.evidence_at || data.final_delivery_date || '',
            status: data.current_status || values.status,
        }));
        form.post(edit ? route('video-production.records.update', [kind, edit.id]) : route('video-production.records.store', kind), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setOpen(false),
        });
    };

    return <Card className="overflow-hidden">
        <CardHeader className="flex-row items-center justify-between gap-4 border-b bg-muted/20">
            <div>
                <CardTitle>{t(titles[kind])}</CardTitle>
                <p className="mt-1 text-sm text-muted-foreground">{jobId ? t('Showing records for the selected production job.') : t('Create or select a production job before adding records.')}</p>
            </div>
            <Button disabled={!jobId} onClick={() => launch()}><Plus className="h-4 w-4" />{t('Add Record')}</Button>
        </CardHeader>
        <CardContent className="p-0">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="border-b bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="w-10 px-3 py-3" aria-label={t('Details')} />
                            <th className="px-3 py-3 text-left">{t('Record ID')}</th>
                            {compactFields.map(([key, label]) => <th className="px-3 py-3 text-left" key={key}>{t(label)}</th>)}
                            <th className="w-32 px-3 py-3 text-right">{t('Actions')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((record: any) => <Fragment key={record.id}>
                            <tr className="border-b transition-colors hover:bg-muted/30">
                                <td className="px-3 py-3">
                                    <Button type="button" variant="ghost" size="icon" title={t('View details')} aria-label={t('View details')} onClick={() => setExpanded(expanded === record.id ? null : record.id)}>
                                        {expanded === record.id ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                                    </Button>
                                </td>
                                <td className="px-3 py-3 font-semibold text-foreground">{record.record_key}</td>
                                {compactFields.map(([key, , type]) => <td className="max-w-52 px-3 py-3" key={key}>
                                    {type === 'select' && record.data?.[key]
                                        ? <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">{record.data[key]}</span>
                                        : <span className="line-clamp-2"><RecordValue value={record.data?.[key]} type={type} /></span>}
                                </td>)}
                                <td className="px-3 py-3 text-right">
                                    <div className="flex justify-end gap-1">
                                        <Button type="button" variant="ghost" size="icon" title={t('View details')} aria-label={t('View details')} onClick={() => setExpanded(expanded === record.id ? null : record.id)}><Eye className="h-4 w-4" /></Button>
                                        <Button type="button" variant="ghost" size="icon" title={t('Edit')} aria-label={t('Edit')} onClick={() => launch(record)}><Pencil className="h-4 w-4" /></Button>
                                        <Button type="button" variant="ghost" size="icon" title={t('Delete')} aria-label={t('Delete')} className="text-destructive hover:text-destructive" onClick={() => confirm(t('Delete this record?')) && router.delete(route('video-production.records.destroy', [kind, record.id]), { preserveScroll: true })}><Trash2 className="h-4 w-4" /></Button>
                                    </div>
                                </td>
                            </tr>
                            {expanded === record.id && <tr className="border-b bg-muted/20">
                                <td colSpan={compactFields.length + 3} className="p-5">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div><p className="font-semibold">{t('Record details')}</p><p className="text-xs text-muted-foreground">{record.record_key}</p></div>
                                        <Button type="button" variant="outline" size="sm" onClick={() => launch(record)}><Pencil className="h-3.5 w-3.5" />{t('Edit record')}</Button>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        {displayFields(kind).map(([key, label, type]) => <div className="rounded-lg border bg-background p-3" key={key}>
                                            <p className="text-xs font-medium text-muted-foreground">{t(label)}</p>
                                            <div className="mt-1 break-words font-medium"><RecordValue value={record.data?.[key]} type={type} /></div>
                                        </div>)}
                                        {kind === 'shoot' && record.data?.proof_image_path && <div className="rounded-lg border bg-background p-3 sm:col-span-2">
                                            <p className="mb-2 text-xs font-medium text-muted-foreground">{t('Proof Image')}</p>
                                            <img src={`/storage/${record.data.proof_image_path}`} alt={t('Proof Image')} className="max-h-64 w-full rounded-md object-contain" />
                                        </div>}
                                    </div>
                                </td>
                            </tr>}
                        </Fragment>)}
                    </tbody>
                </table>
                {!items.length && <div className="flex flex-col items-center justify-center gap-2 p-12 text-center text-muted-foreground"><FolderKanban className="h-9 w-9 opacity-40" /><p>{jobId ? t('No records found for this job.') : t('No production job selected.')}</p></div>}
            </div>
        </CardContent>
        <Dialog open={open} onOpenChange={setOpen}>{open && <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto">
            <DialogHeader><DialogTitle>{edit ? t('Edit') : t('Add')} {t(titles[kind])}</DialogTitle></DialogHeader>
            <form onSubmit={submit} className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div><Label>{t('Production Job')} *</Label><Select value={String(form.data.production_job_id || '')} onValueChange={value => form.setData('production_job_id', Number(value))}><SelectTrigger><SelectValue placeholder={t('Select a production job')} /></SelectTrigger><SelectContent>{jobs.map((job: any) => <SelectItem key={job.id} value={String(job.id)}>{job.reference} - {job.name}</SelectItem>)}</SelectContent></Select><InputError message={form.errors.production_job_id} /></div>
                <div><Label>{t('Record ID')} *</Label><div className="flex gap-2"><Input required value={form.data.record_key} onChange={event => form.setData('record_key', event.target.value)} /><Button type="button" variant="outline" className="shrink-0" disabled={!nextRecordKey} onClick={() => form.setData('record_key', nextRecordKey)}>{t('Generate ID')}</Button></div><InputError message={form.errors.record_key} /></div>
                {fields[kind as Kind].map(([key, label, type = 'text', options]) => <div key={key} className={type === 'textarea' || type === 'image' ? 'md:col-span-2 lg:col-span-3' : ''}><Label>{t(label)}</Label>{type === 'textarea' ? <Textarea value={(form.data.data as any)[key]} onChange={event => form.setData('data', { ...form.data.data, [key]: event.target.value })} /> : type === 'select' ? <Select value={(form.data.data as any)[key] || 'none'} onValueChange={value => form.setData('data', { ...form.data.data, [key]: value === 'none' ? '' : value })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="none">{t('Not specified')}</SelectItem>{options?.map(option => <SelectItem key={option} value={option}>{t(option)}</SelectItem>)}</SelectContent></Select> : <Input type={type} step={type === 'number' ? '0.01' : undefined} value={(form.data.data as any)[key]} onChange={event => form.setData('data', { ...form.data.data, [key]: event.target.value })} />}</div>)}
                <div className="flex justify-end gap-2 md:col-span-2 lg:col-span-3"><Button type="button" variant="outline" onClick={() => setOpen(false)}>{t('Cancel')}</Button><Button disabled={form.processing}>{t('Save')}</Button></div>
            </form>
        </DialogContent>}</Dialog>
    </Card>;
}

function JobsManager({ jobs, statuses, nextReference, permissions }: any) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<any>();
    const canCreate = permissions.includes('create-video-production-job');
    const canEdit = permissions.includes('edit-video-production-job');
    const canDelete = permissions.includes('delete-video-production-job');
    const launch = (job?: any) => { setEditing(job); setOpen(true); };
    return <Card><CardHeader className="flex-row items-center justify-between"><CardTitle>{t('Production Jobs')}</CardTitle>{canCreate && <Button onClick={() => launch()}><Plus className="h-4 w-4" />{t('Add Job')}</Button>}</CardHeader><CardContent><div className="overflow-x-auto rounded-lg border"><table className="w-full text-sm"><thead className="bg-muted"><tr><th className="p-3 text-left">{t('Job')}</th><th className="p-3 text-left">{t('Status')}</th><th className="p-3 text-left">{t('Dates')}</th><th className="p-3 text-right">{t('Actions')}</th></tr></thead><tbody>{jobs.map((job: any) => <tr className="border-t" key={job.id}><td className="p-3"><p className="font-medium">{job.name}</p><p className="text-xs text-muted-foreground">{job.reference || '-'}</p></td><td className="p-3 capitalize">{job.status.replaceAll('_', ' ')}</td><td className="p-3">{formatDateOnly(job.start_date)} / {formatDateOnly(job.end_date)}</td><td className="p-3 text-right">{canEdit && <Button variant="ghost" size="icon" onClick={() => launch(job)}><Pencil className="h-4 w-4" /></Button>}{canDelete && <Button variant="ghost" size="icon" className="text-destructive" onClick={() => confirm(t('Delete this production job and all of its linked shoots, deliverables, revisions, time entries, and evidence?')) && router.delete(route('video-production.jobs.destroy', job.id), { preserveScroll: true })}><Trash2 className="h-4 w-4" /></Button>}</td></tr>)}</tbody></table>{!jobs.length && <p className="p-10 text-center text-muted-foreground">{t('No production jobs yet.')}</p>}</div></CardContent><Dialog open={open} onOpenChange={setOpen}>{open && <JobForm job={editing} statuses={statuses} nextReference={nextReference} onClose={() => setOpen(false)} />}</Dialog></Card>;
}

export default function Dashboard() {
    const { t } = useTranslation();
    const { jobs, statuses, nextJobReference, nextRecordKeys, selectedJobId, settings, month, records, productionMetrics, auth } = usePage<any>().props;
    useFlashMessages();
    const tabs: any[] = [['overview','Overview',Gauge],['jobs','Production Jobs',FolderKanban],['shoot','Shooting Log',Video],['deliverable','Deliverables',FileCheck2],['revision','Revisions',ListChecks],['time','Time Log',Clock3],['evidence','Evidence',FolderKanban],['settings','Settings',Settings2]];
    const metrics = [['Shooting sessions',productionMetrics.shoots,Video],['Reels delivered',productionMetrics.reels_delivered,CheckCircle2],['Static posts delivered',productionMetrics.static_delivered,FileCheck2],['Work hours',productionMetrics.work_hours,Clock3]];
    const targets = [['Reels / month',settings.monthly_reel_target],['Static posts / month',settings.monthly_static_target],['Shoot sessions',`${settings.minimum_shoots} - ${settings.maximum_shoots}`],['Hours / shoot',settings.included_hours_per_shoot],['Script lead days',settings.required_lead_days],['Included revisions',settings.included_revisions],['Extra shooting hours',productionMetrics.extra_hours],['Waiting for DOC',productionMetrics.waiting_for_client],['Extra support hours',productionMetrics.extra_support_hours]];
    return <AuthenticatedLayout breadcrumbs={[{label:t('Project')},{label:t('Production')}]} pageTitle={t('Production')}>
        <Head title={t('Production')} />
        <Tabs defaultValue="overview" className="space-y-5">
            <div className="overflow-x-auto rounded-xl border bg-card p-2"><TabsList className="h-auto min-w-max bg-transparent">{tabs.map(([value,label,Icon]) => <TabsTrigger value={value} key={value} className="gap-2"><Icon className="h-4 w-4" />{t(label)}</TabsTrigger>)}</TabsList></div>
            <Card><CardContent className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"><div><Label>{t('Active Production Job')}</Label><p className="text-sm text-muted-foreground">{t('All logs, evidence, and dashboard totals are filtered by this job.')}</p></div><Select value={selectedJobId ? String(selectedJobId) : ''} onValueChange={value => router.get(route('video-production.dashboard'), { job_id: value, month }, { preserveState: true, replace: true })}><SelectTrigger className="w-full sm:w-80"><SelectValue placeholder={t('Select a production job')} /></SelectTrigger><SelectContent>{jobs.map((job:any) => <SelectItem key={job.id} value={String(job.id)}>{job.reference} - {job.name}</SelectItem>)}</SelectContent></Select></CardContent></Card>
            <TabsContent value="overview" className="space-y-5">
                <Card><CardContent className="flex items-center justify-between p-5"><div><p className="text-sm text-muted-foreground">{t('Monthly management view')}</p><h2 className="text-xl font-semibold">DOC Medical {t('Production')}</h2></div><div className="flex items-center gap-2"><CalendarDays className="h-4 w-4" /><Input type="month" className="w-44" value={month} onChange={e => router.get(route('video-production.dashboard'), {month:e.target.value}, {preserveState:true,replace:true})} /></div></CardContent></Card>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">{metrics.map(([label,value,Icon]:any) => <Card key={label}><CardContent className="flex items-center justify-between p-5"><div><p className="text-sm text-muted-foreground">{t(label)}</p><p className="text-3xl font-semibold">{value}</p></div><Icon className="h-8 w-8 text-primary" /></CardContent></Card>)}</div>
                <Card><CardHeader><CardTitle>{t('Agreed process and targets')}</CardTitle></CardHeader><CardContent className="grid gap-3 md:grid-cols-3">{targets.map(([label,value]) => <div className="flex justify-between rounded-lg bg-muted/50 p-3" key={label}><span>{t(label)}</span><strong>{value}</strong></div>)}</CardContent></Card>
            </TabsContent>
            <TabsContent value="jobs"><JobsManager jobs={jobs} statuses={statuses} nextReference={nextJobReference} permissions={auth.user.permissions || []} /></TabsContent>
            {(['shoot','deliverable','revision','time','evidence'] as Kind[]).map(kind => <TabsContent value={kind} key={kind}><Manager kind={kind} items={records[kind] || []} settings={settings} jobId={selectedJobId} jobs={jobs} nextRecordKey={nextRecordKeys?.[kind]} /></TabsContent>)}
            <TabsContent value="settings"><SettingsForm settings={settings} /></TabsContent>
        </Tabs>
    </AuthenticatedLayout>;
}

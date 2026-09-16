import { FormEvent, Fragment, useRef, useState } from 'react';
import html2pdf from 'html2pdf.js';
import axios from 'axios';
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
import { CalendarDays, CheckCircle2, ChevronDown, ChevronUp, Clock3, Download, Eye, FileCheck2, FileText, FolderKanban, Gauge, Mail, Pencil, Plus, Settings2, Trash2, Video, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import SettingsForm from './Settings/SettingsForm';

type Kind = 'shoot'|'deliverable';
type Field = [string,string,string?,string[]?];
const opts:any = {
  yes:['Yes','Partial','No','N/A'], shoot:['Planned','Unplanned'], content:['Reel','Static','Other'], via:['WhatsApp','Email','Meeting / MOM','Other'],
  revision:['Client Creative Change','Medical Correction','Brill Correction','Additional Scope','Text / Subtitle','B-roll / Insert','Other'],
  delivery:['Awaiting Inputs','Ready for Production','Editing','V1 Sent','Waiting for DOC','Revision in Progress','Under Review','Approved','Delivered','On Hold'],
  activity:['Shooting','Editing','Revision','Static Design','Planning','Meeting','Travel / Setup','Administration','Other'], scope:['Included Scope','Extra Support','Out of Scope','Internal'],
};
const fields:Record<Kind,Field[]> = {
 shoot:[['shoot_date','Shoot Date','date'],['branch_location','Branch / Location'],['shoot_type','Shoot Type','select',opts.shoot],['shoot_confirmed_at','Shoot Confirmed','datetime-local'],['doctor_subject','Doctor / Subject'],['planned_start','Planned Start','time'],['actual_start','Actual Start','time'],['actual_end','Actual End','time'],['content_plan_received','Content / Script Plan Received','select',['Yes','Partial','No']],['script_received_at','Script / Plan Received','datetime-local'],['script_type','Script Type','select',['Video Script','Speech Script','None']],['planning_contribution','Brill Contributed to Planning','select',['Yes','No']],['b_roll_requirements','B-roll Requirements','select',opts.yes],['editing_references','Editing References','select',opts.yes],['props_requirements','Props / Special Requirements','select',opts.yes],['reels_shot','Reels Shot','number'],['additional_topics','Additional Topics Added on Shoot Day','textarea'],['shoot_notes','Shoot Notes','textarea'],['supporting_files','Supporting Documents','files'],['evidence_links','Evidence Links','links'],['client_confirmation','DOC Confirmation','select',['Confirmed','No Response','Disputed','Not Sent']]],
 deliverable:[['content_type','Content Type','select',opts.content],['content_name','Reel / Content Name'],['episodes_reels_count','Episodes / Reels Count','number'],['shoot_id','Shoot ID','shoot-link'],['doctor_department','Doctor / Department'],['shoot_date','Shoot Date','date'],['script_received_at','Script / Plan Received','datetime-local'],['b_roll_defined','B-roll / Inserts Defined','select',opts.yes],['editing_reference','Editing Reference Provided','select',opts.yes],['input_status','Input Status','select',['Awaiting Inputs','Ready for Production']],['complete_inputs_received_at','Complete Inputs Received','datetime-local'],['editing_started_at','Editing Started','datetime-local'],['v1_delivered_at','V1 Delivered','datetime-local'],['v1_evidence_link','V1 Evidence Link','url'],['client_v1_response_at','Client V1 Response','datetime-local'],['v1_response_status','V1 Response Status','select',['Approved','Revision Required','Still Under Review','No Response']],['revision_1_requested_at','Revision 01 Requested','datetime-local'],['revision_1_summary','Revision 01 Summary','textarea'],['revision_1_category','Revision 01 Category','select',opts.revision],['revision_1_delivered_at','Revision 01 Delivered','datetime-local'],['revision_1_files','Revision 01 Evidence Files','files'],['revision_1_evidence_links','Revision 01 Evidence Links','links'],['revision_2_requested_at','Revision 02 Requested','datetime-local'],['revision_2_summary','Revision 02 Summary','textarea'],['revision_2_category','Revision 02 Category','select',opts.revision],['revision_2_delivered_at','Revision 02 Delivered','datetime-local'],['revision_2_files','Revision 02 Evidence Files','files'],['revision_2_evidence_links','Revision 02 Evidence Links','links'],['revision_3_requested_at','Revision 03 Requested','datetime-local'],['revision_3_summary','Revision 03 Summary','textarea'],['revision_3_category','Revision 03 Category','select',opts.revision],['revision_3_delivered_at','Revision 03 Delivered','datetime-local'],['revision_3_files','Revision 03 Evidence Files','files'],['revision_3_evidence_links','Revision 03 Evidence Links','links'],['additional_revision_count','Additional Revisions (4+)','number'],['final_approval_date','Final Approval Date','date'],['final_delivery_date','Final Delivery Date','date'],['final_evidence_link','Final Evidence Link','url'],['current_status','Current Status','select',opts.delivery],['evidence_folder_link','Evidence Folder Link','url'],['supporting_files','Supporting Documents','files'],['evidence_links','Evidence Links','links'],['notes','Notes','textarea']],
};
const titles:any={shoot:'Shooting Log',deliverable:'Deliverables'};
const calculated: Record<Kind, Field[]> = {
 shoot: [['total_hours','Total Hours'],['contract_hours','Contract Hours'],['extra_hours','Extra Hours'],['working_days_before','Working Days Before Shoot'],['lead_requirement','Lead-time Requirement']],
 deliverable: [['working_days_before','Working Days Before Shoot'],['script_lead_category','Script Lead Category'],['v1_turnaround_days','V1 Brill Turnaround WD'],['client_review_days','Client Review WD'],['total_revisions','Total Revisions'],['revision_4_plus','Revision 4+ Flag'],['total_brill_days','Total Brill Work Days'],['total_client_wait_days','Total Client Wait Days']],
};

const displayFields = (kind: Kind) => [...fields[kind].filter(([, , type]) => !['files', 'links'].includes(type || '')), ...calculated[kind]];
const shootingReportHiddenFields = new Set([
    'content_plan_received',
    'b_roll_requirements',
    'editing_references',
    'props_requirements',
    'client_confirmation',
    'contract_hours',
    'extra_hours',
    'working_days_before',
    'lead_requirement',
]);
const reportFields = (kind: Kind) => displayFields(kind).filter(([key]) => kind !== 'shoot' || !shootingReportHiddenFields.has(key));

function SupportingFilesInput({ existing, selected, onChange }: { existing: any[]; selected: File[]; onChange: (files: File[]) => void }) {
    return <div className="space-y-2">
        <BaseInput type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx" onChange={event => onChange(Array.from(event.target.files || []))} />
        <p className="text-xs text-muted-foreground">Up to 10 files. JPG, PNG, WebP, PDF, DOC, DOCX, XLS or XLSX. Maximum 10 MB each.</p>
        {[...existing.map(file => ({ name: file.name, saved: true })), ...selected.map(file => ({ name: file.name, saved: false }))].map((file, index) => <div key={`${file.name}-${index}`} className="flex items-center gap-2 rounded-md border bg-muted/20 px-3 py-2 text-sm"><FileText className="h-4 w-4 text-muted-foreground" /><span className="min-w-0 flex-1 truncate">{file.name}</span>{!file.saved && <span className="text-xs text-muted-foreground">New</span>}</div>)}
    </div>;
}

function EvidenceLinksInput({ value, onChange }: { value: string[]; onChange: (links: string[]) => void }) {
    const links = value.length ? value : [''];
    return <div className="space-y-2">
        {links.map((link, index) => <div className="flex gap-2" key={index}><BaseInput type="url" placeholder="https://..." value={link} onChange={event => onChange(links.map((item, itemIndex) => itemIndex === index ? event.target.value : item))} /><Button type="button" variant="outline" size="icon" disabled={links.length === 1} onClick={() => onChange(links.filter((_, itemIndex) => itemIndex !== index))} aria-label="Remove link"><X className="h-4 w-4" /></Button></div>)}
        <Button type="button" variant="outline" size="sm" disabled={links.length >= 10} onClick={() => onChange([...links, ''])}><Plus className="h-4 w-4" />Add another link</Button>
        <p className="text-xs text-muted-foreground">Add up to 10 evidence or shared-folder links.</p>
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
    deliverable: ['content_name', 'content_type', 'episodes_reels_count', 'current_status', 'v1_delivered_at'],
};

const summaryLabels: Partial<Record<string, string>> = {
    v1_delivered_at: 'V1 Delivery Date',
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

function RecordValue({ value, type }: { value: any; type?: string }) {
    if (type === 'url' && value) {
        return <a href={value} target="_blank" rel="noreferrer" className="text-primary underline-offset-4 hover:underline">{value}</a>;
    }

    return <>{formatRecordValue(value, type)}</>;
}

function storedFiles(data: any, key = 'supporting_files'): any[] {
    const files = Array.isArray(data?.[key]) ? data[key] : [];
    if (key === 'supporting_files' && data?.proof_image_path && !files.some((file: any) => file.path === data.proof_image_path)) {
        return [{ path: data.proof_image_path, name: data.proof_image_path.split('/').pop() || 'Proof image' }, ...files];
    }
    return files;
}

function evidenceLinks(data: any, key = 'evidence_links'): string[] {
    if (Array.isArray(data?.[key])) return [...new Set<string>(data[key].filter(Boolean))];
    return key === 'evidence_links' && data?.evidence_folder_link ? [data.evidence_folder_link] : [];
}

function isImageFile(file: any): boolean {
    return String(file?.type || '').startsWith('image/') || /\.(jpe?g|png|webp|gif)$/i.test(String(file?.name || file?.path || ''));
}

const splitEmails = (value: string) => value.split(/[\n,;]+/).map(email => email.trim()).filter(Boolean);

function RecordPdfButton({ record, kind, companyName, project, settings, canEmail }: { record: any; kind: Kind; companyName: string; project: any; settings: any; canEmail: boolean }) {
    const { t } = useTranslation();
    const reportRef = useRef<HTMLDivElement>(null);
    const [generating, setGenerating] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [emailOpen, setEmailOpen] = useState(false);
    const [emailSending, setEmailSending] = useState(false);
    const [emailStatus, setEmailStatus] = useState<{ type: 'success'|'error'; message: string } | null>(null);
    const [emailData, setEmailData] = useState({ recipients: '', cc: '', subject: '', message: '' });

    const waitForReportImages = async () => {
        if (!reportRef.current) return;
        const images = Array.from(reportRef.current.querySelectorAll('img'));
        await Promise.all(images.map(image => image.complete
            ? Promise.resolve()
            : new Promise<void>(resolve => {
                image.addEventListener('load', () => resolve(), { once: true });
                image.addEventListener('error', () => resolve(), { once: true });
            })));
    };
    const createPdfWorker = () => {
        if (!reportRef.current) throw new Error(t('The report preview is not ready.'));
        return html2pdf().set({
                filename: `${record.record_key}.pdf`,
                margin: 8,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['css', 'legacy'], before: '.pdf-evidence-page', avoid: '.pdf-field' },
                enableLinks: true,
            }).from(reportRef.current);
    };
    const downloadPdf = async () => {
        setGenerating(true);
        try {
            await waitForReportImages();
            await createPdfWorker().save();
        } finally {
            setGenerating(false);
        }
    };
    const openEmail = () => {
        const subjectTemplate = settings.report_email_subject || 'Production Report: {record_id}';
        setEmailData({
            recipients: (settings.report_email_recipients || []).join('\n'),
            cc: (settings.report_email_cc || []).join('\n'),
            subject: subjectTemplate.replaceAll('{record_id}', record.record_key).replaceAll('{project_name}', project.name),
            message: settings.report_email_message || 'Hello,\n\nPlease find the production report attached.\n\nRegards',
        });
        setEmailStatus(null);
        setEmailOpen(true);
    };
    const sendEmail = async () => {
        const recipients = splitEmails(emailData.recipients);
        if (!recipients.length) {
            setEmailStatus({ type: 'error', message: t('Enter at least one recipient email address.') });
            return;
        }
        setEmailSending(true);
        setEmailStatus(null);
        try {
            await waitForReportImages();
            const output = await createPdfWorker().outputPdf('blob');
            const blob = output instanceof Blob ? output : new Blob([output], { type: 'application/pdf' });
            if (blob.size > 15 * 1024 * 1024) {
                throw new Error(t('The generated PDF is larger than 15 MB. Remove large supporting images and try again.'));
            }
            const payload = new FormData();
            payload.append('record_id', String(record.id));
            payload.append('type', kind);
            recipients.forEach(email => payload.append('recipients[]', email));
            splitEmails(emailData.cc).forEach(email => payload.append('cc[]', email));
            payload.append('subject', emailData.subject);
            payload.append('message', emailData.message);
            payload.append('report', blob, `${record.record_key}.pdf`);
            const response = await axios.post(route('video-production.reports.email', project.id), payload);
            setEmailStatus({ type: 'success', message: response.data.message || t('Report emailed successfully.') });
        } catch (error: any) {
            const validationErrors = error.response?.data?.errors;
            const firstError = validationErrors ? Object.values(validationErrors).flat()[0] : null;
            setEmailStatus({ type: 'error', message: String(firstError || error.response?.data?.message || error.message || t('The report could not be emailed.')) });
        } finally {
            setEmailSending(false);
        }
    };

    return <>
        <Button type="button" variant="outline" size="sm" onClick={() => setPreviewOpen(true)}><FileText className="h-3.5 w-3.5" />{t('PDF Report')}</Button>
        <Dialog open={previewOpen} onOpenChange={setPreviewOpen}>
            <DialogContent className="max-h-[95vh] max-w-5xl overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-4"><DialogTitle>{t('Client Report Preview')}</DialogTitle></DialogHeader>
                <div className="overflow-auto bg-slate-100 p-5">
                <div className="mx-auto min-h-[281mm] w-[194mm] max-w-full overflow-hidden bg-white p-10 font-sans text-slate-900 shadow-lg" ref={reportRef}>
            <div className="border-b-4 border-emerald-500 pb-5">
                <p className="text-sm font-semibold uppercase tracking-widest text-emerald-600">{companyName}</p>
                <h1 className="mt-2 text-3xl font-bold">{t(titles[kind])} Report</h1>
                <p className="mt-1 text-sm font-semibold text-slate-700">Project: {project.name}</p>
                <div className="mt-3 grid grid-cols-2 gap-4 text-sm text-slate-500"><span className="min-w-0 break-words">{record.record_key}</span><span className="text-right">Generated {new Intl.DateTimeFormat('en-GB', { dateStyle: 'medium' }).format(new Date())}</span></div>
            </div>
            <div className="mt-5 grid grid-cols-3 gap-2">
                {reportFields(kind).map(([key, label, type]) => <div className="pdf-field rounded-lg border border-slate-200 p-2.5" key={key}><p className="text-[10px] font-semibold uppercase leading-tight text-slate-500">{t(label)}</p><div className="mt-1 whitespace-pre-wrap break-words text-xs font-medium leading-snug"><RecordValue value={record.data?.[key]} type={type} /></div></div>)}
            </div>
            {kind === 'shoot' && <div className="pdf-field mt-6 rounded-lg border-2 border-amber-400 bg-amber-50 p-5 text-amber-950">
                <p className="text-sm font-bold uppercase tracking-wide">Note</p>
                <div className="mt-2 space-y-3 whitespace-normal break-words text-xs font-medium leading-relaxed">
                    <p>If the final script is not received at least 5 Brill Creations working days before the scheduled shoot, the full agreed pre-production period is considered unavailable. In such cases, Brill Creations may not have sufficient time to properly prepare the shot plan, B-roll requirements, visual direction, references, and other pre-production elements.</p>
                    <p>For record purposes, the session will be categorized as an "Unplanned Shoot", and "Brill Contributed to Planning" will be marked as "No".</p>
                    <p className="font-bold">Brill Creations working days are Sunday to Thursday only.</p>
                </div>
            </div>}
            {(storedFiles(record.data).length > 0 || evidenceLinks(record.data).length > 0) && <div className="pdf-evidence-page" />}
            {storedFiles(record.data).length > 0 && <div className="mt-6"><h2 className="text-lg font-bold">Supporting Documents</h2><div className="mt-3 grid grid-cols-2 gap-3">{storedFiles(record.data).map((file: any, index: number) => <div className="pdf-field overflow-hidden rounded-lg border border-slate-200" key={`${file.path}-${index}`}>{isImageFile(file) && <img src={`/storage/${file.path}`} alt={file.name} className="h-52 w-full bg-slate-50 object-contain" crossOrigin="anonymous" />}<div className="flex items-center gap-2 p-3"><FileText className="h-4 w-4 shrink-0 text-slate-500" /><span className="min-w-0 flex-1 truncate text-sm font-medium">{file.name}</span><a href={`/storage/${file.path}`} target="_blank" rel="noreferrer" className="text-xs font-semibold text-emerald-700 underline">View</a></div></div>)}</div></div>}
            {evidenceLinks(record.data).length > 0 && <div className="mt-6"><h2 className="text-lg font-bold">Evidence Links</h2><div className="mt-2 space-y-2">{evidenceLinks(record.data).map((link, index) => <a className="pdf-field block break-all rounded border border-slate-200 p-3 text-sm text-emerald-700 underline" href={link} key={`${link}-${index}`}>{link}</a>)}</div></div>}
            {kind === 'deliverable' && [1, 2, 3].map(number => {
                const files = storedFiles(record.data, `revision_${number}_files`);
                const links = evidenceLinks(record.data, `revision_${number}_evidence_links`);
                return (files.length > 0 || links.length > 0) && <div className="mt-6" key={number}><h2 className="text-lg font-bold">Revision {String(number).padStart(2, '0')} Evidence</h2><div className="mt-2 space-y-2">{files.map((file: any, index: number) => <a className="pdf-field block rounded border border-slate-200 p-3 text-sm text-emerald-700 underline" href={`/storage/${file.path}`} key={`${file.path}-${index}`}>{file.name}</a>)}{links.map((link, index) => <a className="pdf-field block break-all rounded border border-slate-200 p-3 text-sm text-emerald-700 underline" href={link} key={`${link}-${index}`}>{link}</a>)}</div></div>;
            })}
            <div className="mt-8 border-t pt-4 text-center text-xs text-slate-400">Generated from Wazely ERP Production Management</div>
                </div>
                </div>
                <div className="flex flex-wrap justify-end gap-2 border-t bg-background px-6 py-4"><Button type="button" variant="outline" onClick={() => setPreviewOpen(false)}>{t('Close')}</Button>{canEmail && settings.report_email_enabled && <Button type="button" variant="outline" onClick={openEmail}><Mail className="h-4 w-4" />{t('Send Email')}</Button>}<Button type="button" disabled={generating} onClick={downloadPdf}><Download className="h-4 w-4" />{generating ? t('Preparing PDF...') : t('Download PDF')}</Button></div>
            </DialogContent>
        </Dialog>
        <Dialog open={emailOpen} onOpenChange={setEmailOpen}>
            <DialogContent className="max-w-2xl">
                <DialogHeader><DialogTitle>{t('Email Production Report')}</DialogTitle></DialogHeader>
                <div className="space-y-4">
                    <div><Label required>{t('Recipients')}</Label><Textarea rows={3} value={emailData.recipients} onChange={event => setEmailData(data => ({ ...data, recipients: event.target.value }))} placeholder="client@example.com" /><p className="mt-1 text-xs text-muted-foreground">{t('Enter up to 10 addresses, separated by commas or one per line.')}</p></div>
                    <div><Label>{t('CC')}</Label><Textarea rows={2} value={emailData.cc} onChange={event => setEmailData(data => ({ ...data, cc: event.target.value }))} placeholder="manager@example.com" /></div>
                    <div><Label required>{t('Subject')}</Label><BaseInput value={emailData.subject} onChange={event => setEmailData(data => ({ ...data, subject: event.target.value }))} maxLength={200} /></div>
                    <div><Label>{t('Message')}</Label><Textarea rows={6} value={emailData.message} onChange={event => setEmailData(data => ({ ...data, message: event.target.value }))} maxLength={5000} /></div>
                    <div className="rounded-md border bg-muted/30 px-3 py-2 text-sm"><FileText className="mr-2 inline h-4 w-4" />{record.record_key}.pdf <span className="text-xs text-muted-foreground">({t('maximum 15 MB')})</span></div>
                    {emailStatus && <div className={`rounded-md border px-3 py-2 text-sm ${emailStatus.type === 'success' ? 'border-emerald-300 bg-emerald-50 text-emerald-800' : 'border-red-300 bg-red-50 text-red-700'}`}>{emailStatus.message}</div>}
                    <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setEmailOpen(false)}>{t('Close')}</Button><Button type="button" disabled={emailSending || !emailData.subject.trim()} onClick={sendEmail}><Mail className="h-4 w-4" />{emailSending ? t('Sending...') : t('Send Report')}</Button></div>
                </div>
            </DialogContent>
        </Dialog>
    </>;
}

const isOvertimeField = (record: any, key: string) => Number(record.data?.extra_hours || 0) > 0 && ['total_hours', 'extra_hours'].includes(key);

function calculateHours(start?: string, end?: string): number | null {
    if (!start || !end) return null;
    let hours = (new Date(`2000-01-01T${end}`).getTime() - new Date(`2000-01-01T${start}`).getTime()) / 3600000;
    if (hours < 0) hours += 24;
    return +hours.toFixed(2);
}

function Manager({ kind, items, settings, nextRecordKey, companyName, project, shoots = [], canEdit }: any) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [edit, setEdit] = useState<any>();
    const [expanded, setExpanded] = useState<number | null>(null);
    const [visibleRevisions, setVisibleRevisions] = useState(1);
    const blank = Object.fromEntries(fields[kind as Kind].map(([key, , type]) => [key, ['files', 'links'].includes(type || '') ? [] : (key === 'episodes_reels_count' ? '1' : '')]));
    const form = useForm({ record_key: '', recorded_at: '', status: '', data: blank });
    const compactFields = summaryKeys[kind as Kind].map(key => fieldFor(kind, key)).filter(Boolean) as Field[];
    const liveTotalHours = kind === 'shoot' ? calculateHours((form.data.data as any).actual_start, (form.data.data as any).actual_end) : null;
    const liveExtraHours = liveTotalHours === null ? null : Math.max(0, +(liveTotalHours - Number(settings.included_hours_per_shoot)).toFixed(2));

    const linkShoot = (shootId: string) => {
        const shoot = shoots.find((item: any) => item.record_key === shootId);
        const shootData = shoot?.data || {};
        form.setData('data', {
            ...form.data.data,
            shoot_id: shootId,
            doctor_department: shootData.doctor_subject || '',
            shoot_date: shootData.shoot_date || '',
            script_received_at: shootData.script_received_at || '',
            b_roll_defined: shootData.b_roll_requirements || '',
            editing_reference: shootData.editing_references || '',
            evidence_folder_link: evidenceLinks(shootData)[0] || '',
        });
    };

    const launch = (record?: any) => {
        const lastRevision = [3, 2, 1].find(number => Object.entries(record?.data || {}).some(([key, value]) =>
            key.startsWith(`revision_${number}_`) && (Array.isArray(value) ? value.length > 0 : Boolean(value))
        )) || 1;
        setVisibleRevisions(lastRevision);
        setEdit(record);
        form.setData({
            record_key: record?.record_key || '',
            recorded_at: record?.recorded_at?.slice(0, 16) || '',
            status: record?.status || '',
            data: {
                ...blank,
                ...record?.data,
                supporting_files: record?.data?.supporting_files || [],
                new_supporting_files: [],
                evidence_links: evidenceLinks(record?.data),
            },
        });
        setOpen(true);
    };

    const renderField = ([key, label, type = 'text', options]: Field) => {
        const value = (form.data.data as any)[key];
        const newFilesKey = `new_${key}`;
        const wide = ['textarea', 'files', 'links'].includes(type);

        return <div key={key} className={wide ? 'md:col-span-2 lg:col-span-3' : ''}>
            <Label>{t(label)}</Label>
            {type === 'textarea' ? <Textarea value={value || ''} onChange={event => form.setData('data', { ...form.data.data, [key]: event.target.value })} />
                : type === 'select' ? <Select value={value || 'none'} onValueChange={next => form.setData('data', { ...form.data.data, [key]: next === 'none' ? '' : next })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="none">{t('Not specified')}</SelectItem>{options?.map(option => <SelectItem key={option} value={option}>{t(option)}</SelectItem>)}</SelectContent></Select>
                : type === 'shoot-link' ? <Select value={value || 'none'} onValueChange={next => linkShoot(next === 'none' ? '' : next)}><SelectTrigger><SelectValue placeholder={t('Select Shooting Log record')} /></SelectTrigger><SelectContent><SelectItem value="none">{t('No linked shoot')}</SelectItem>{shoots.map((shoot: any) => <SelectItem key={shoot.id} value={shoot.record_key}>{shoot.record_key} - {shoot.data?.doctor_subject || shoot.data?.branch_location || t('Shooting Log')}</SelectItem>)}</SelectContent></Select>
                : type === 'files' ? <SupportingFilesInput existing={value || []} selected={(form.data.data as any)[newFilesKey] || []} onChange={files => form.setData('data', { ...form.data.data, [newFilesKey]: files })} />
                : type === 'links' ? <EvidenceLinksInput value={value || []} onChange={links => form.setData('data', { ...form.data.data, [key]: links })} />
                : <Input type={type} step={type === 'number' ? '1' : undefined} min={type === 'number' ? (key === 'episodes_reels_count' ? '1' : '0') : undefined} value={value || ''} onChange={event => form.setData('data', { ...form.data.data, [key]: event.target.value })} />}
            <InputError message={(form.errors as any)[`data.${key}`] || (form.errors as any)[`data.${newFilesKey}`]} />
        </div>;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const data: any = { ...form.data.data };
        if (kind === 'shoot' && data.actual_start && data.actual_end) {
            data.total_hours = calculateHours(data.actual_start, data.actual_end);
            data.contract_hours = +settings.included_hours_per_shoot;
            data.extra_hours = Math.max(0, data.total_hours - data.contract_hours);
            data.lead_requirement = ['Unplanned', 'Urgent'].includes(data.shoot_type)
                ? 'N/A - Unplanned'
                : (!data.script_received_at ? 'No - Not Received' : (Math.floor((new Date(data.shoot_date).getTime() - new Date(data.script_received_at).getTime()) / 86400000) >= settings.required_lead_days ? 'Yes' : 'No'));
        }
        form.transform(values => ({
            ...values,
            ...(edit ? { _method: 'put' } : {}),
            data,
            recorded_at: values.recorded_at || data.shoot_date || data.date || data.requested_at || data.evidence_at || data.final_delivery_date || '',
            status: data.current_status || values.status,
        }));
        form.post(edit ? route('video-production.records.update', [project.id, kind, edit.id]) : route('video-production.records.store', [project.id, kind]), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setOpen(false),
        });
    };

    return <Card className="overflow-hidden">
        <CardHeader className="flex-row items-center justify-between gap-4 border-b bg-muted/20">
            <div>
                <CardTitle>{t(titles[kind])}</CardTitle>
                <p className="mt-1 text-sm text-muted-foreground">{t('Showing all company production records.')}</p>
            </div>
            {canEdit && <Button onClick={() => launch()}><Plus className="h-4 w-4" />{t('Add Record')}</Button>}
        </CardHeader>
        <CardContent className="p-0">
            <div className="overflow-x-auto">
                <table className="w-full min-w-[900px] text-sm">
                    <thead className="border-b bg-muted/60 text-xs uppercase tracking-wide text-muted-foreground">
                        <tr>
                            <th className="w-10 px-3 py-3" aria-label={t('Details')} />
                            <th className="px-3 py-3 text-left">{t('Record ID')}</th>
                            {compactFields.map(([key, label]) => <th className="px-3 py-3 text-left" key={key}>{t(summaryLabels[key] || label)}</th>)}
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
                                <td className="px-3 py-3"><p className="font-semibold text-foreground">{record.record_key}</p><p className="mt-0.5 text-xs text-muted-foreground">{project.name}</p></td>
                                {compactFields.map(([key, , type]) => <td className="max-w-52 px-3 py-3" key={key}>
                                    {type === 'select' && record.data?.[key]
                                        ? <span className="inline-flex rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">{record.data[key]}</span>
                                        : <span className="line-clamp-2"><RecordValue value={record.data?.[key]} type={type} /></span>}
                                </td>)}
                                <td className="px-3 py-3 text-right">
                                    <div className="flex justify-end gap-1">
                                        <Button type="button" variant="ghost" size="icon" title={t('View details')} aria-label={t('View details')} onClick={() => setExpanded(expanded === record.id ? null : record.id)}><Eye className="h-4 w-4" /></Button>
                                        {canEdit && <Button type="button" variant="ghost" size="icon" title={t('Edit')} aria-label={t('Edit')} onClick={() => launch(record)}><Pencil className="h-4 w-4" /></Button>}
                                        {canEdit && <Button type="button" variant="ghost" size="icon" title={t('Delete')} aria-label={t('Delete')} className="text-destructive hover:text-destructive" onClick={() => confirm(t('Delete this record?')) && router.delete(route('video-production.records.destroy', [project.id, kind, record.id]), { preserveScroll: true })}><Trash2 className="h-4 w-4" /></Button>}
                                    </div>
                                </td>
                            </tr>
                            {expanded === record.id && <tr className="border-b bg-muted/20">
                                <td colSpan={compactFields.length + 3} className="p-5">
                                    <div className="mb-4 flex items-center justify-between">
                                        <div><p className="font-semibold">{t('Record details')}</p><p className="text-xs text-muted-foreground">{record.record_key}</p></div>
                                        <div className="flex gap-2"><RecordPdfButton record={record} kind={kind} companyName={companyName} project={project} settings={settings} canEmail={canEdit} />{canEdit && <Button type="button" variant="outline" size="sm" onClick={() => launch(record)}><Pencil className="h-3.5 w-3.5" />{t('Edit record')}</Button>}</div>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        {displayFields(kind).map(([key, label, type]) => <div className={`rounded-lg border p-3 ${isOvertimeField(record, key) ? 'border-red-300 bg-red-50 dark:border-red-900 dark:bg-red-950/30' : 'bg-background'}`} key={key}>
                                            <p className={`text-xs font-medium ${isOvertimeField(record, key) ? 'text-red-600' : 'text-muted-foreground'}`}>{t(label)}</p>
                                            <div className={`mt-1 break-words font-medium ${isOvertimeField(record, key) ? 'text-red-600' : ''}`}><RecordValue value={record.data?.[key]} type={type} />{key === 'extra_hours' && Number(record.data?.extra_hours || 0) > 0 && <span className="ml-2 text-xs">{t('Exceeded contract hours')}</span>}</div>
                                        </div>)}
                                        {storedFiles(record.data).length > 0 && <div className="rounded-lg border bg-background p-3 sm:col-span-2">
                                            <p className="mb-2 text-xs font-medium text-muted-foreground">{t('Supporting Documents')}</p>
                                            <div className="space-y-2">{storedFiles(record.data).map((file: any, index: number) => <div key={`${file.path}-${index}`} className="flex items-center gap-2 rounded-md border px-3 py-2"><FileText className="h-4 w-4 text-muted-foreground" /><span className="min-w-0 flex-1 truncate font-medium">{file.name}</span><Button type="button" variant="ghost" size="icon" asChild title={t('View file')}><a href={`/storage/${file.path}`} target="_blank" rel="noreferrer"><Eye className="h-4 w-4" /></a></Button></div>)}</div>
                                        </div>}
                                        {evidenceLinks(record.data).length > 0 && <div className="rounded-lg border bg-background p-3 sm:col-span-2">
                                            <p className="mb-2 text-xs font-medium text-muted-foreground">{t('Evidence Links')}</p>
                                            <div className="space-y-2">{evidenceLinks(record.data).map((link, index) => <div key={`${link}-${index}`} className="flex items-center gap-2 rounded-md border px-3 py-2"><span className="min-w-0 flex-1 truncate">{link}</span><Button type="button" variant="ghost" size="icon" asChild title={t('Open link')}><a href={link} target="_blank" rel="noreferrer"><Eye className="h-4 w-4" /></a></Button></div>)}</div>
                                        </div>}
                                        {kind === 'deliverable' && [1, 2, 3].map(number => {
                                            const files = storedFiles(record.data, `revision_${number}_files`);
                                            const links = evidenceLinks(record.data, `revision_${number}_evidence_links`);
                                            return (files.length > 0 || links.length > 0) && <div className="rounded-lg border bg-background p-3 sm:col-span-2" key={number}><p className="mb-2 text-xs font-medium text-muted-foreground">{t(`Revision ${String(number).padStart(2, '0')} Evidence`)}</p><div className="space-y-2">{files.map((file: any, index: number) => <div key={`${file.path}-${index}`} className="flex items-center gap-2 rounded-md border px-3 py-2"><FileText className="h-4 w-4 text-muted-foreground" /><span className="min-w-0 flex-1 truncate font-medium">{file.name}</span><Button type="button" variant="ghost" size="icon" asChild><a href={`/storage/${file.path}`} target="_blank" rel="noreferrer"><Eye className="h-4 w-4" /></a></Button></div>)}{links.map((link, index) => <div key={`${link}-${index}`} className="flex items-center gap-2 rounded-md border px-3 py-2"><span className="min-w-0 flex-1 truncate">{link}</span><Button type="button" variant="ghost" size="icon" asChild><a href={link} target="_blank" rel="noreferrer"><Eye className="h-4 w-4" /></a></Button></div>)}</div></div>;
                                        })}
                                    </div>
                                </td>
                            </tr>}
                        </Fragment>)}
                    </tbody>
                </table>
                {!items.length && <div className="flex flex-col items-center justify-center gap-2 p-12 text-center text-muted-foreground"><FolderKanban className="h-9 w-9 opacity-40" /><p>{t('No production records found.')}</p></div>}
            </div>
        </CardContent>
        <Dialog open={open} onOpenChange={setOpen}>{open && <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto">
            <DialogHeader><DialogTitle>{edit ? t('Edit') : t('Add')} {t(titles[kind])}</DialogTitle></DialogHeader>
            <form onSubmit={submit} className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <div><Label>{t('Record ID')} *</Label><div className="flex gap-2"><Input required value={form.data.record_key} onChange={event => form.setData('record_key', event.target.value)} /><Button type="button" variant="outline" className="shrink-0" disabled={!nextRecordKey} onClick={() => form.setData('record_key', nextRecordKey)}>{t('Generate ID')}</Button></div><InputError message={form.errors.record_key} /></div>
                {kind === 'shoot' && fields.shoot.map(renderField)}
                {kind === 'deliverable' && <>
                    <div className="md:col-span-2 lg:col-span-3"><h3 className="text-base font-semibold">{t('Deliverable information')}</h3><p className="text-sm text-muted-foreground">{t('Link a shoot to prefill its related production details.')}</p></div>
                    {fields.deliverable.slice(0, fields.deliverable.findIndex(([key]) => key === 'revision_1_requested_at')).map(renderField)}
                    <div className="space-y-4 md:col-span-2 lg:col-span-3">
                        {[1, 2, 3].slice(0, visibleRevisions).map(number => <div key={number} className="rounded-xl border bg-muted/20 p-5 shadow-sm">
                            <div className="mb-4 flex items-center justify-between"><div><h3 className="font-semibold">{t(`Revision ${String(number).padStart(2, '0')}`)}</h3><p className="text-xs text-muted-foreground">{t('Request, delivery and evidence for this revision.')}</p></div><span className="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{number} / 3</span></div>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">{fields.deliverable.filter(([key]) => key.startsWith(`revision_${number}_`)).map(renderField)}</div>
                        </div>)}
                        {visibleRevisions < 3 && <Button type="button" variant="outline" className="w-full border-dashed" onClick={() => setVisibleRevisions(count => count + 1)}><Plus className="h-4 w-4" />{t(`Add Revision ${String(visibleRevisions + 1).padStart(2, '0')}`)}</Button>}
                    </div>
                    <div className="md:col-span-2 lg:col-span-3"><h3 className="text-base font-semibold">{t('Approval, delivery and general evidence')}</h3></div>
                    {fields.deliverable.slice(fields.deliverable.findIndex(([key]) => key === 'additional_revision_count')).map(renderField)}
                </>}
                {kind === 'shoot' && liveTotalHours !== null && <div className={`rounded-lg border p-4 md:col-span-2 lg:col-span-3 ${liveExtraHours && liveExtraHours > 0 ? 'border-red-300 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/30' : 'bg-muted/30'}`}><div className="flex flex-wrap gap-8"><div><p className="text-xs font-medium">{t('Calculated Total Hours')}</p><p className="text-xl font-semibold">{liveTotalHours}</p></div><div><p className="text-xs font-medium">{t('Calculated Extra Hours')}</p><p className="text-xl font-semibold">{liveExtraHours}</p></div></div>{liveTotalHours === 0 && <p className="mt-2 text-xs">{t('Actual Start and Actual End are the same, so the calculated duration is 0 hours.')}</p>}{liveExtraHours !== null && liveExtraHours > 0 && <p className="mt-2 text-xs font-semibold">{t('The shoot exceeds the included contract hours.')}</p>}</div>}
                <div className="flex justify-end gap-2 md:col-span-2 lg:col-span-3"><Button type="button" variant="outline" onClick={() => setOpen(false)}>{t('Cancel')}</Button><Button disabled={form.processing}>{t('Save')}</Button></div>
            </form>
        </DialogContent>}</Dialog>
    </Card>;
}

export default function Dashboard() {
    const { t } = useTranslation();
    const { companyName, project, canEdit, canManageSettings, unassignedRecordCount, nextRecordKeys, settings, range, month, records, productionMetrics } = usePage<any>().props;
    useFlashMessages();
    const tabs: any[] = [['overview','Overview',Gauge],['shoot','Shooting Log',Video],['deliverable','Deliverables',FileCheck2],...(canManageSettings ? [['settings','Settings',Settings2]] : [])];
    const metrics = [['Shooting sessions',productionMetrics.shoots,Video],['Reels delivered',productionMetrics.reels_delivered,CheckCircle2],['Static posts delivered',productionMetrics.static_delivered,FileCheck2],['Work hours',productionMetrics.work_hours,Clock3]];
    const targets = [['Reels / month',settings.monthly_reel_target],['Static posts / month',settings.monthly_static_target],['Shoot sessions',`${settings.minimum_shoots} - ${settings.maximum_shoots}`],['Hours / shoot',settings.included_hours_per_shoot],['Script lead days',settings.required_lead_days],['Included revisions',settings.included_revisions],['Extra shooting hours',productionMetrics.extra_hours],['Waiting for client',productionMetrics.waiting_for_client]];
    return <AuthenticatedLayout breadcrumbs={[{label:t('Project'),url:route('project.index')},{label:project.name,url:route('project.show',project.id)},{label:t('Production')}]} pageTitle={`${project.name} - ${t('Production')}`}>
        <Head title={`${project.name} - ${t('Production')}`} />
        <Tabs defaultValue="overview" className="space-y-5">
            <div className="overflow-x-auto rounded-xl border bg-card p-2"><TabsList className="h-auto min-w-max bg-transparent">{tabs.map(([value,label,Icon]) => <TabsTrigger value={value} key={value} className="gap-2"><Icon className="h-4 w-4" />{t(label)}</TabsTrigger>)}</TabsList></div>
            <TabsContent value="overview" className="space-y-5">
                {unassignedRecordCount > 0 && <Card className="border-amber-300 bg-amber-50"><CardContent className="flex flex-col justify-between gap-4 p-5 text-amber-950 md:flex-row md:items-center"><div><p className="font-semibold">{t('Existing production records need a project')}</p><p className="mt-1 text-sm">{unassignedRecordCount} {t('record(s) are currently unassigned. Move them here only if they belong to this project.')}</p></div><Button type="button" variant="outline" className="border-amber-500 bg-white" onClick={() => confirm(t(`Move all ${unassignedRecordCount} unassigned records to ${project.name}?`)) && router.post(route('video-production.claim-unassigned', project.id), {}, { preserveScroll: true })}>{t('Move Existing Records Here')}</Button></CardContent></Card>}
                <Card><CardContent className="flex flex-col justify-between gap-4 p-5 md:flex-row md:items-center"><div><p className="text-sm text-muted-foreground">{range === 'monthly' ? t('Monthly management view') : t('All-time management view')}</p><h2 className="text-xl font-semibold">{project.name} {t('Production')}</h2></div><div className="flex flex-wrap items-center gap-2"><Select value={range || 'all'} onValueChange={value => router.get(route('video-production.dashboard', project.id), {range:value,month}, {preserveState:true,replace:true})}><SelectTrigger className="w-36"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">{t('All Time')}</SelectItem><SelectItem value="monthly">{t('Monthly')}</SelectItem></SelectContent></Select>{range === 'monthly' && <><CalendarDays className="h-4 w-4" /><Input type="month" className="w-44" value={month} onChange={e => router.get(route('video-production.dashboard', project.id), {range:'monthly',month:e.target.value}, {preserveState:true,replace:true})} /></>}</div></CardContent></Card>
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">{metrics.map(([label,value,Icon]:any) => <Card key={label}><CardContent className="flex items-center justify-between p-5"><div><p className="text-sm text-muted-foreground">{t(label)}</p><p className="text-3xl font-semibold">{value}</p></div><Icon className="h-8 w-8 text-primary" /></CardContent></Card>)}</div>
                <Card><CardHeader><CardTitle>{t('Agreed process and targets')}</CardTitle></CardHeader><CardContent className="grid gap-3 md:grid-cols-3">{targets.map(([label,value]) => <div className="flex justify-between rounded-lg bg-muted/50 p-3" key={label}><span>{t(label)}</span><strong>{value}</strong></div>)}</CardContent></Card>
            </TabsContent>
            {(['shoot','deliverable'] as Kind[]).map(kind => <TabsContent value={kind} key={kind}><Manager kind={kind} items={records[kind] || []} shoots={records.shoot || []} settings={settings} nextRecordKey={nextRecordKeys?.[kind]} companyName={companyName} project={project} canEdit={canEdit} /></TabsContent>)}
            {canManageSettings && <TabsContent value="settings"><SettingsForm settings={settings} projectId={project.id} /></TabsContent>}
        </Tabs>
    </AuthenticatedLayout>;
}

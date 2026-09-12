import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from 'react-i18next';

export default function JobForm({ job, statuses, nextReference, onClose }: any) {
    const { t } = useTranslation();
    const form = useForm({
        name: job?.name || '', reference: job?.reference || nextReference || '', description: job?.description || '',
        status: job?.status || 'draft', start_date: job?.start_date?.slice(0, 10) || '',
        end_date: job?.end_date?.slice(0, 10) || '',
    });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        job ? form.put(route('video-production.jobs.update', job.id), options) : form.post(route('video-production.jobs.store'), options);
    };
    return <DialogContent className="max-w-2xl"><DialogHeader><DialogTitle>{job ? t('Edit Production Job') : t('Create Production Job')}</DialogTitle></DialogHeader>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
            <div><Label>{t('Job Name')} *</Label><Input value={form.data.name} onChange={e => form.setData('name', e.target.value)} /><InputError message={form.errors.name} /></div>
            <div><Label>{t('Reference')}</Label><Input readOnly value={form.data.reference} className="bg-muted" /><p className="mt-1 text-xs text-muted-foreground">{t('Generated automatically when the job is created.')}</p><InputError message={form.errors.reference} /></div>
            <div><Label>{t('Status')} *</Label><Select value={form.data.status} onValueChange={value => form.setData('status', value)}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{statuses.map((status: string) => <SelectItem key={status} value={status}>{t(status.replaceAll('_', ' '))}</SelectItem>)}</SelectContent></Select><InputError message={form.errors.status} /></div>
            <div><Label>{t('Start Date')}</Label><Input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)} /><InputError message={form.errors.start_date} /></div>
            <div><Label>{t('End Date')}</Label><Input type="date" value={form.data.end_date} onChange={e => form.setData('end_date', e.target.value)} /><InputError message={form.errors.end_date} /></div>
            <div className="md:col-span-2"><Label>{t('Description')}</Label><Textarea rows={4} value={form.data.description} onChange={e => form.setData('description', e.target.value)} /><InputError message={form.errors.description} /></div>
            <div className="flex justify-end gap-2 md:col-span-2"><Button type="button" variant="outline" onClick={onClose}>{t('Cancel')}</Button><Button disabled={form.processing}>{job ? t('Update Job') : t('Create Job')}</Button></div>
        </form>
    </DialogContent>;
}

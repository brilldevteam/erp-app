import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
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
        name: job?.name || '', reference: job?.reference || '', description: job?.description || '',
        status: job?.status || 'draft', start_date: job?.start_date?.slice(0, 10) || '',
        end_date: job?.end_date?.slice(0, 10) || '',
    });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        job ? form.put(route('video-production.jobs.update', job.id), options) : form.post(route('video-production.jobs.store'), options);
    };

    const asLocalDate = (value: string) => value ? new Date(`${value}T00:00:00`) : undefined;

    return <DialogContent className="max-w-2xl"><DialogHeader><DialogTitle>{job ? t('Edit Production Job') : t('Create Production Job')}</DialogTitle></DialogHeader>
        <form onSubmit={submit} className="grid gap-4 md:grid-cols-2">
            <div><Label>{t('Job Name')} *</Label><Input value={form.data.name} onChange={e => form.setData('name', e.target.value)} /><InputError message={form.errors.name} /></div>
            <div>
                <Label>{t('Reference')}</Label>
                <div className="flex gap-2">
                    <Input readOnly value={form.data.reference} className="bg-muted" placeholder={t('Generate a reference')} />
                    {!job && (
                        <Button
                            type="button"
                            variant="outline"
                            className="shrink-0"
                            onClick={() => form.setData('reference', nextReference || '')}
                        >
                            {t('Generate Reference')}
                        </Button>
                    )}
                </div>
                <p className="mt-1 text-xs text-muted-foreground">
                    {job ? t('The reference cannot be changed after creation.') : t('Generate a reference before creating the job.')}
                </p>
                <InputError message={form.errors.reference} />
            </div>
            <div><Label>{t('Status')} *</Label><Select value={form.data.status} onValueChange={value => form.setData('status', value)}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{statuses.map((status: string) => <SelectItem key={status} value={status}>{t(status.replaceAll('_', ' '))}</SelectItem>)}</SelectContent></Select><InputError message={form.errors.status} /></div>
            <div><Label>{t('Start Date')}</Label><DatePicker value={form.data.start_date} onChange={value => form.setData('start_date', value)} placeholder={t('Select start date')} maxDate={asLocalDate(form.data.end_date)} /><InputError message={form.errors.start_date} /></div>
            <div><Label>{t('End Date')}</Label><DatePicker value={form.data.end_date} onChange={value => form.setData('end_date', value)} placeholder={t('Select end date')} minDate={asLocalDate(form.data.start_date)} /><InputError message={form.errors.end_date} /></div>
            <div className="md:col-span-2"><Label>{t('Description')}</Label><Textarea rows={4} value={form.data.description} onChange={e => form.setData('description', e.target.value)} /><InputError message={form.errors.description} /></div>
            <div className="flex justify-end gap-2 md:col-span-2"><Button type="button" variant="outline" onClick={onClose}>{t('Cancel')}</Button><Button disabled={form.processing || (!job && !form.data.reference)}>{job ? t('Update Job') : t('Create Job')}</Button></div>
        </form>
    </DialogContent>;
}

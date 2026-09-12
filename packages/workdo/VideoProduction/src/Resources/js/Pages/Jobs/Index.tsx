import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Edit, Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import JobForm from './JobForm';

export default function Index() {
    const { t } = useTranslation();
    const { jobs, statuses, nextJobReference, filters, auth } = usePage<any>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [editing, setEditing] = useState<any>(undefined);
    const [open, setOpen] = useState(false);
    useFlashMessages();
    const apply = () => router.get(route('video-production.jobs.index'), { search, status: status === 'all' ? '' : status }, { preserveState: true, replace: true });
    const openForm = (job: any = null) => { setEditing(job); setOpen(true); };
    const remove = (job: any) => { if (confirm(t('Delete this production job?'))) router.delete(route('video-production.jobs.destroy', job.id), { preserveScroll: true }); };

    return <AuthenticatedLayout breadcrumbs={[{ label: t('Project') }, { label: t('Video Production') }, { label: t('Production Jobs') }]} pageTitle={t('Production Jobs')} pageActions={auth.user.permissions.includes('create-video-production-job') && <Button size="sm" onClick={() => openForm()}><Plus className="h-4 w-4" />{t('Add Job')}</Button>}>
        <Head title={t('Production Jobs')} />
        <Card><CardContent className="p-5"><div className="mb-5 flex flex-col gap-3 sm:flex-row"><Input value={search} onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && apply()} placeholder={t('Search production jobs...')} /><Select value={status} onValueChange={setStatus}><SelectTrigger className="sm:w-64"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">{t('All statuses')}</SelectItem>{statuses.map((item: string) => <SelectItem key={item} value={item}>{t(item.replaceAll('_', ' '))}</SelectItem>)}</SelectContent></Select><Button onClick={apply}>{t('Search')}</Button></div>
            <div className="overflow-x-auto"><table className="w-full text-sm"><thead><tr className="border-b text-left"><th className="p-3">{t('Job')}</th><th className="p-3">{t('Status')}</th><th className="p-3">{t('Dates')}</th><th className="p-3 text-right">{t('Actions')}</th></tr></thead><tbody>{jobs.data.map((job: any) => <tr key={job.id} className="border-b"><td className="p-3"><p className="font-medium">{job.name}</p><p className="text-xs text-muted-foreground">{job.reference || '-'}</p></td><td className="p-3 capitalize">{job.status.replaceAll('_', ' ')}</td><td className="p-3">{job.start_date?.slice(0, 10) || '-'} / {job.end_date?.slice(0, 10) || '-'}</td><td className="p-3 text-right">{auth.user.permissions.includes('edit-video-production-job') && <Button variant="ghost" size="icon" onClick={() => openForm(job)}><Edit className="h-4 w-4" /></Button>}{auth.user.permissions.includes('delete-video-production-job') && <Button variant="ghost" size="icon" className="text-destructive" onClick={() => remove(job)}><Trash2 className="h-4 w-4" /></Button>}</td></tr>)}</tbody></table>{jobs.data.length === 0 && <p className="py-12 text-center text-muted-foreground">{t('No production jobs found.')}</p>}</div>
        </CardContent></Card>
        <Dialog open={open} onOpenChange={setOpen}>{open && <JobForm job={editing} statuses={statuses} nextReference={nextJobReference} onClose={() => setOpen(false)} />}</Dialog>
    </AuthenticatedLayout>;
}

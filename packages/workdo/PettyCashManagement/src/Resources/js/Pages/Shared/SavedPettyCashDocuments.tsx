import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Download, Eye, Paperclip, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/utils/helpers';
import PettyCashDocuments from './PettyCashDocuments';

export interface PettyCashAttachment {
    id: number;
    file_name: string;
    file_type: string;
    file_size: number;
    created_at: string;
    uploader?: { id: number; name: string } | null;
}

/** Saved supporting documents of a petty cash request or fund, with preview, download, add and remove. */
export default function SavedPettyCashDocuments({ type, recordId, attachments, canAttach, canRemove }: {
    type: 'request' | 'fund'; recordId: number; attachments: PettyCashAttachment[]; canAttach: boolean; canRemove: boolean;
}) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ attachments: [] as File[] });
    const [removing, setRemoving] = useState(false);

    return <div className="mt-6 space-y-3 border-t pt-6">
        <h3 className="flex items-center gap-2 text-lg font-semibold text-gray-900">
            <Paperclip className="h-4 w-4 text-gray-500" />{t('Supporting Documents')}
        </h3>
        {attachments.length === 0 && <div className="text-sm text-muted-foreground">{t('No attachments')}</div>}
        {attachments.map(file => <div key={file.id} className="flex flex-wrap items-center gap-2 border-b py-2 text-sm">
            <div className="min-w-0 flex-1 basis-40">
                <div className="break-all">{file.file_name}</div>
                <div className="text-xs text-muted-foreground">{Math.ceil(file.file_size / 1024)} KB · {file.uploader?.name || '-'} · {formatDate(file.created_at)}</div>
            </div>
            {['application/pdf', 'image/jpeg', 'image/png'].includes(file.file_type) && <Button variant="ghost" size="icon" asChild>
                <a title={t('Preview')} aria-label={t('Preview')} target="_blank" rel="noopener noreferrer" href={route('petty-cash-management.attachments.download', file.id) + '?preview=1'}><Eye className="h-4 w-4" /></a>
            </Button>}
            <Button variant="ghost" size="icon" asChild>
                <a title={t('Download')} aria-label={t('Download')} href={route('petty-cash-management.attachments.download', file.id)}><Download className="h-4 w-4" /></a>
            </Button>
            {canRemove && <Button type="button" variant="ghost" size="icon" title={t('Remove attachment')} aria-label={t('Remove attachment')} disabled={removing || processing} onClick={() => {
                if (!window.confirm(t('Remove this supporting document?'))) return;
                setRemoving(true);
                router.delete(route('petty-cash-management.attachments.destroy', file.id), { preserveScroll: true, preserveState: true, onFinish: () => setRemoving(false) });
            }}><Trash2 className="h-4 w-4 text-red-600" /></Button>}
        </div>)}
        {canAttach && <div className="space-y-3">
            <PettyCashDocuments files={data.attachments} onChange={files => { clearErrors(); setData('attachments', files); }} errors={errors} existingCount={attachments.length} disabled={processing || removing} />
            {data.attachments.length > 0 && <Button type="button" disabled={processing || removing} onClick={() => post(route('petty-cash-management.attachments.store', [type, recordId]), {
                preserveScroll: true, preserveState: true, forceFormData: true, onSuccess: () => reset(),
            })}>{processing ? t('Saving...') : t('Save Attachments')}</Button>}
        </div>}
    </div>;
}

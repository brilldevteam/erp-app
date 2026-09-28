import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Download, Eye, Paperclip, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

export interface PartyAttachment {
    id: number;
    file_name: string;
    file_type: string;
    file_size: number;
    created_at: string;
    uploader?: { name: string };
}

export default function SavedPartyDocuments({ partyType, partyId, attachments, canRemove = false }: {
    partyType: 'customers' | 'vendors';
    partyId: number;
    attachments: PartyAttachment[];
    canRemove?: boolean;
}) {
    const { t } = useTranslation();
    const routePrefix = `account.${partyType}.attachments`;

    return <div className="space-y-3 border-t pt-4">
        <h3 className="text-sm font-medium">{t('Supporting Documents')}</h3>
        {attachments.length === 0 && <p className="text-sm text-muted-foreground">{t('No attachments')}</p>}
        {attachments.map(file => <div key={file.id} className="flex items-center gap-2 border-b py-2 text-sm">
            <Paperclip className="h-4 w-4 shrink-0" />
            <div className="min-w-0 flex-1">
                <p className="break-all">{file.file_name}</p>
                <p className="text-xs text-muted-foreground">{Math.ceil(file.file_size / 1024)} KB{file.uploader?.name ? ` - ${file.uploader.name}` : ''}</p>
            </div>
            {['application/pdf', 'image/jpeg', 'image/png'].includes(file.file_type) && <Button variant="ghost" size="icon" asChild>
                <a title={t('Preview')} aria-label={t('Preview')} target="_blank" rel="noopener noreferrer" href={route(`${routePrefix}.download`, [partyId, file.id]) + '?preview=1'}><Eye className="h-4 w-4" /></a>
            </Button>}
            <Button variant="ghost" size="icon" asChild>
                <a title={t('Download')} aria-label={t('Download')} href={route(`${routePrefix}.download`, [partyId, file.id])}><Download className="h-4 w-4" /></a>
            </Button>
            {canRemove && <Button type="button" variant="ghost" size="icon" title={t('Remove attachment')} aria-label={t('Remove attachment')} onClick={() => {
                if (window.confirm(t('Remove this supporting document?'))) router.delete(route(`${routePrefix}.destroy`, [partyId, file.id]), { preserveScroll: true });
            }}><Trash2 className="h-4 w-4 text-red-600" /></Button>}
        </div>)}
    </div>;
}

import { useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Paperclip, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { InputError } from '@/components/ui/input-error';

export default function JournalDocuments({ files, onChange, errors = {}, disabled = false, existingCount = 0 }: {
    files: File[]; onChange: (files: File[]) => void; errors?: Record<string, string>; disabled?: boolean; existingCount?: number;
}) {
    const { t } = useTranslation();
    const id = useId();
    const input = useRef<HTMLInputElement>(null);
    const [error, setError] = useState('');
    return <div className="space-y-2">
        <label htmlFor={id} className="block text-sm font-medium">{t('Supporting Documents (Optional)')}</label>
        <input ref={input} id={id} type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.zip" className="sr-only" disabled={disabled} onChange={(event) => {
            const next = [...files, ...Array.from(event.target.files || [])];
            event.target.value = '';
            if (next.length + existingCount > 5) { setError(t('A journal can have up to 5 supporting documents.')); return; }
            if (next.some(file => file.size > 5 * 1024 * 1024 || !/\.(pdf|jpe?g|png|docx?|xlsx?|zip)$/i.test(file.name))) {
                setError(t('Select PDF, JPG, PNG, Word, Excel or ZIP files up to 5 MB each.')); return;
            }
            setError(''); onChange(next);
        }} />
        <Button type="button" variant="outline" size="sm" disabled={disabled || files.length + existingCount >= 5} onClick={() => input.current?.click()}>
            <Paperclip className="mr-2 h-4 w-4" />{t('Attach Files')}
        </Button>
        <InputError message={error || errors.attachments} />
        {files.map((file, index) => <div key={`${file.name}-${index}`}>
            <div className="flex min-w-0 items-center gap-2 border-b py-2 text-sm">
                <Paperclip className="h-4 w-4 shrink-0" />
                <span className="min-w-0 flex-1 break-all">{file.name}</span>
                <span className="shrink-0 text-muted-foreground">{Math.ceil(file.size / 1024)} KB</span>
                <Button type="button" variant="ghost" size="icon" title={t('Remove file')} aria-label={t('Remove file')} disabled={disabled} onClick={() => { setError(''); onChange(files.filter((_, i) => i !== index)); }}><X className="h-4 w-4" /></Button>
            </div>
            <InputError message={errors[`attachments.${index}`]} />
        </div>)}
    </div>;
}

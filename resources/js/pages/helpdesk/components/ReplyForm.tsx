import { useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { RichTextEditor } from "@/components/ui/rich-text-editor";
import { Paperclip, Send, X } from 'lucide-react';
import { toast } from 'sonner';
import { ReplyFormProps } from './types';

export default function ReplyForm({ ticketId, onReplyAdded, disabled }: ReplyFormProps) {
    const { t } = useTranslation();
    const [message, setMessage] = useState('');
    const [files, setFiles] = useState<File[]>([]);
    const fileInput = useRef<HTMLInputElement>(null);
    const [isInternal, setIsInternal] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editorKey, setEditorKey] = useState(0);
    const { auth } = usePage<any>().props;

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!message.trim() || isSubmitting) return;

        setIsSubmitting(true);
        
        try {
            const payload = new FormData();
            payload.append('message', message);
            payload.append('is_internal', isInternal ? '1' : '0');
            files.forEach((file) => payload.append('files[]', file));

            const response = await fetch(route('helpdesk-replies.store', ticketId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: payload
            });

            const data = await response.json();
            
            if (data.success) {
                setMessage('');
                setFiles([]);
                setIsInternal(false);
                setEditorKey(prev => prev + 1); // Force re-render to clear editor
                onReplyAdded(data.reply);
            } else {
                const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                toast.error((firstError as string) || data.error || data.message || t('The reply could not be sent.'));
            }
        } catch (error) {
            console.error('Error sending reply:', error);
            toast.error(t('The reply could not be sent.'));
        } finally {
            setIsSubmitting(false);
        }
    };



    return (
        <div className="border-t bg-gray-50/50 p-3">
            <div className="space-y-2">
                <div className="relative">
                    <RichTextEditor
                        key={editorKey}
                        content={message}
                        onChange={(content) => setMessage(content)}
                        placeholder={t('Type your message...')}
                        disabled={disabled || isSubmitting}
                        className="min-h-[80px]"
                        onKeyDown={(e: React.KeyboardEvent) => {
                            e.stopPropagation();
                        }}
                    />
                </div>
                
                {files.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                        {files.map((file, index) => (
                            <span key={`${file.name}-${index}`} className="inline-flex max-w-[240px] items-center gap-1 rounded bg-gray-200 px-2 py-1 text-xs">
                                <span className="truncate">{file.name}</span>
                                <button type="button" aria-label={t('Remove attachment')} onClick={() => setFiles((current) => current.filter((_, position) => position !== index))}>
                                    <X className="h-3 w-3" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}

                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3 flex-1">
                        <div className="flex items-center gap-1">
                            <input
                                ref={fileInput}
                                type="file"
                                multiple
                                className="hidden"
                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                onChange={(e) => {
                                    const chosen = Array.from(e.target.files ?? []);
                                    setFiles((current) => [...current, ...chosen].slice(0, 5));
                                    e.target.value = '';
                                }}
                            />
                            <Button type="button" variant="outline" size="sm" className="h-8" disabled={disabled || isSubmitting || files.length >= 5} onClick={() => fileInput.current?.click()}>
                                <Paperclip className="mr-1.5 h-3.5 w-3.5" />
                                <span className="text-xs">{t('Attach')}</span>
                            </Button>
                        </div>
                        
                        {auth.user?.permissions?.includes('create-helpdesk-internal-notes') && (
                            <div className="flex items-center gap-1.5">
                                <Checkbox
                                    id="is_internal"
                                    checked={isInternal}
                                    onCheckedChange={(checked) => setIsInternal(!!checked)}
                                    disabled={disabled || isSubmitting}
                                    className="h-4 w-4"
                                />
                                <label htmlFor="is_internal" className="text-xs text-gray-600 cursor-pointer whitespace-nowrap">
                                    {t('Internal')}
                                </label>
                            </div>
                        )}
                    </div>
                    
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={!message.trim() || disabled || isSubmitting}
                        size="sm"
                        className="flex items-center gap-1.5 px-4 py-2 h-8"
                    >
                        <Send className="h-3.5 w-3.5" />
                        <span className="text-xs font-medium">
                            {isSubmitting ? t('Sending...') : t('Send')}
                        </span>
                    </Button>
                </div>
            </div>
        </div>
    );
}

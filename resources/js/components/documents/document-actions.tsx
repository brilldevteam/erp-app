import { ReactNode, useState } from 'react';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Download, ExternalLink, History, Link, Link2Off, Mail, Send } from 'lucide-react';

interface Props {
    type: 'invoice' | 'quotation';
    id: number;
    number: string;
    customerEmail?: string;
    balance?: number;
}

export default function DocumentActions({ type, id, number, customerEmail = '', balance = 0 }: Props) {
    const { t } = useTranslation();
    const [emailOpen, setEmailOpen] = useState(false);
    const [historyOpen, setHistoryOpen] = useState(false);
    const [history, setHistory] = useState<any[]>([]);
    const [sending, setSending] = useState(false);
    const [form, setForm] = useState({ recipient: customerEmail, cc: '', bcc: '', subject: `${type === 'invoice' ? 'Invoice' : 'Quotation'} ${number}`, message: `Please find ${type} ${number} attached. You can also view it securely using the link in this email.` });

    const jsonHeaders = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' };

    const createLink = async () => {
        const response = await fetch(route('documents.share', { type, id }), { method: 'POST', headers: jsonHeaders, body: JSON.stringify({ expires_in_days: 30 }) });
        if (!response.ok) return;
        const data = await response.json();
        await navigator.clipboard.writeText(data.url);
        window.alert(t('Secure link copied to clipboard.'));
    };

    const loadHistory = async () => {
        const response = await fetch(route('documents.history', { type, id }), { headers: { Accept: 'application/json' } });
        const data = await response.json();
        setHistory([...(data.activities || []), ...(data.deliveries || []).map((item: any) => ({ ...item, action: `email_${item.status}` }))].sort((a, b) => String(b.created_at).localeCompare(String(a.created_at))));
        setHistoryOpen(true);
    };

    const send = () => {
        setSending(true);
        router.post(route('documents.send', { type, id }), { ...form, expires_in_days: 30 }, { preserveScroll: true, onFinish: () => { setSending(false); setEmailOpen(false); } });
    };

    const remind = () => {
        router.post(route('documents.remind', { id }), { recipient: customerEmail, subject: `Payment reminder: invoice ${number}`, message: `This is a friendly reminder that invoice ${number} has an outstanding balance. Please use the secure payment link in this email.` }, { preserveScroll: true });
    };

    return <>
        <Card><CardContent className="flex flex-wrap gap-2 p-4">
            <Button variant="outline" onClick={() => window.open(route('documents.preview', { type, id }), '_blank')}><ExternalLink className="mr-2 h-4 w-4" />{t('Preview')}</Button>
            <Button variant="outline" onClick={() => window.open(route('documents.pdf', { type, id }), '_blank')}><Download className="mr-2 h-4 w-4" />{t('PDF')}</Button>
            <Button variant="outline" onClick={createLink}><Link className="mr-2 h-4 w-4" />{t('Copy secure link')}</Button>
            <Button variant="outline" onClick={() => router.delete(route('documents.share.revoke', { type, id }), { preserveScroll: true })}><Link2Off className="mr-2 h-4 w-4" />{t('Revoke links')}</Button>
            <Button onClick={() => setEmailOpen(true)}><Mail className="mr-2 h-4 w-4" />{t('Send email')}</Button>
            {type === 'invoice' && balance > 0 && <Button variant="secondary" onClick={remind}><Send className="mr-2 h-4 w-4" />{t('Send reminder')}</Button>}
            <Button variant="ghost" onClick={loadHistory}><History className="mr-2 h-4 w-4" />{t('Activity')}</Button>
        </CardContent></Card>

        <Dialog open={emailOpen} onOpenChange={setEmailOpen}><DialogContent className="sm:max-w-xl"><DialogHeader><DialogTitle>{t('Send document')}</DialogTitle></DialogHeader>
            <div className="grid gap-4">
                <Field label={t('Recipient')}><Input type="email" value={form.recipient} onChange={(e) => setForm({ ...form, recipient: e.target.value })} /></Field>
                <div className="grid grid-cols-2 gap-3"><Field label="CC"><Input value={form.cc} onChange={(e) => setForm({ ...form, cc: e.target.value })} /></Field><Field label="BCC"><Input value={form.bcc} onChange={(e) => setForm({ ...form, bcc: e.target.value })} /></Field></div>
                <Field label={t('Subject')}><Input value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} /></Field>
                <Field label={t('Message')}><Textarea rows={7} value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} /></Field>
                <p className="text-xs text-muted-foreground">{t('The generated PDF and a 30-day secure customer link will be included.')}</p>
            </div><DialogFooter><Button variant="outline" onClick={() => setEmailOpen(false)}>{t('Cancel')}</Button><Button disabled={sending || !form.recipient || !form.subject || !form.message} onClick={send}>{sending ? t('Sending...') : t('Send')}</Button></DialogFooter>
        </DialogContent></Dialog>

        <Dialog open={historyOpen} onOpenChange={setHistoryOpen}><DialogContent className="sm:max-w-2xl"><DialogHeader><DialogTitle>{t('Document activity')}</DialogTitle></DialogHeader>
            <div className="max-h-[60vh] space-y-3 overflow-y-auto">{history.length === 0 ? <p className="text-muted-foreground">{t('No activity recorded yet.')}</p> : history.map((item) => <div key={`${item.action}-${item.id}-${item.created_at}`} className="rounded-md border p-3"><div className="font-medium capitalize">{String(item.action).replaceAll('_', ' ')}</div><div className="text-xs text-muted-foreground">{new Date(item.created_at).toLocaleString()}{item.recipient ? ` · ${item.recipient}` : ''}</div>{item.failure_reason && <p className="mt-1 text-sm text-destructive">{item.failure_reason}</p>}</div>)}</div>
        </DialogContent></Dialog>
    </>;
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return <div><Label className="mb-2 block">{label}</Label>{children}</div>;
}

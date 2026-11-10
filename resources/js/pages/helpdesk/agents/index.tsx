import { Head, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, ShieldCheck } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

type Agent = { id: number; name: string; email: string; is_enable_login: boolean; roles: Array<{ name: string; label: string }> };

export default function SupportAgents() {
    const { t } = useTranslation();
    const { agents } = usePage<{ agents: Agent[] }>().props;
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'support-agent' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('support-agents.store'), { onSuccess: () => { form.reset(); setOpen(false); } });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Helpdesk') }, { label: t('Support Agents') }]} pageTitle={t('Support Agents')}>
            <Head title={t('Support Agents')} />
            <div className="flex items-center justify-between mb-6">
                <div><h1 className="text-2xl font-semibold">{t('Support Agents')}</h1><p className="text-sm text-muted-foreground">{t('Platform staff who can work on assigned support tickets.')}</p></div>
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogTrigger asChild><Button><Plus className="mr-2 h-4 w-4" />{t('New Agent')}</Button></DialogTrigger>
                    <DialogContent>
                        <DialogHeader><DialogTitle>{t('Create Support Agent')}</DialogTitle></DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            <div><Label required>{t('Name')}</Label><Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} /><InputError message={form.errors.name} /></div>
                            <div><Label required>{t('Email')}</Label><Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /><InputError message={form.errors.email} /></div>
                            <div><Label required>{t('Role')}</Label><Select value={form.data.role} onValueChange={(value) => form.setData('role', value)}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="support-agent">{t('Support Agent')}</SelectItem><SelectItem value="support-manager">{t('Support Manager')}</SelectItem></SelectContent></Select></div>
                            <div className="grid grid-cols-2 gap-4"><div><Label required>{t('Password')}</Label><Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} /><InputError message={form.errors.password} /></div><div><Label required>{t('Confirm Password')}</Label><Input type="password" value={form.data.password_confirmation} onChange={(e) => form.setData('password_confirmation', e.target.value)} /></div></div>
                            <div className="flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setOpen(false)}>{t('Cancel')}</Button><Button type="submit" disabled={form.processing}>{t('Create Agent')}</Button></div>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
            <Card><CardContent className="p-0"><div className="divide-y">{agents.map((agent) => <div key={agent.id} className="flex items-center gap-4 p-5"><div className="flex h-10 w-10 items-center justify-center rounded bg-primary/10 text-primary"><ShieldCheck className="h-5 w-5" /></div><div className="flex-1"><p className="font-medium">{agent.name}</p><p className="text-sm text-muted-foreground">{agent.email}</p></div><div className="text-sm font-medium">{agent.roles[0]?.label || t('Support Agent')}</div><div className={agent.is_enable_login ? 'text-sm text-green-600' : 'text-sm text-red-600'}>{agent.is_enable_login ? t('Active') : t('Disabled')}</div></div>)}{agents.length === 0 && <p className="p-8 text-center text-muted-foreground">{t('No support agents yet.')}</p>}</div></CardContent></Card>
        </AuthenticatedLayout>
    );
}

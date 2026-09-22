import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { KeyRound, LogIn, UserCog, UserPlus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

type ClientAccount = {
    id: number;
    name: string;
    email: string;
    is_enable_login?: boolean;
    is_disable?: boolean;
};

export default function ProductionClientAccess({ project, accounts }: { project: any; accounts: ClientAccount[] }) {
    const { t } = useTranslation();
    const clients: ClientAccount[] = project.production_clients || [];
    const [createOpen, setCreateOpen] = useState(false);
    const [profileClient, setProfileClient] = useState<ClientAccount | null>(null);
    const [passwordClient, setPasswordClient] = useState<ClientAccount | null>(null);
    const [existingId, setExistingId] = useState('');

    const createForm = useForm({
        name: project.name || '', email: '', password: '', password_confirmation: '', send_welcome_email: true,
    });
    const profileForm = useForm({ name: '', email: '', is_enable_login: true });
    const passwordForm = useForm({ password: '', password_confirmation: '' });
    const availableAccounts = accounts.filter(account => !clients.some(client => client.id === account.id));

    useEffect(() => {
        if (!profileClient) return;
        profileForm.setData({
            name: profileClient.name,
            email: profileClient.email,
            is_enable_login: Boolean(profileClient.is_enable_login),
        });
        profileForm.clearErrors();
    }, [profileClient]);

    const createLogin = () => createForm.post(route('video-production.client-access.store', project.id), {
        preserveScroll: true,
        onSuccess: () => { setCreateOpen(false); createForm.reset(); },
    });

    const attachExisting = () => {
        if (!existingId) return;
        router.post(route('video-production.client-access.attach', [project.id, existingId]), {}, {
            preserveScroll: true,
            onSuccess: () => { setExistingId(''); setCreateOpen(false); },
        });
    };

    const updateProfile = () => profileClient && profileForm.put(
        route('video-production.client-access.update', [project.id, profileClient.id]),
        { preserveScroll: true, onSuccess: () => setProfileClient(null) },
    );

    const updatePassword = () => passwordClient && passwordForm.put(
        route('video-production.client-access.password', [project.id, passwordClient.id]),
        { preserveScroll: true, onSuccess: () => { setPasswordClient(null); passwordForm.reset(); } },
    );

    const openCreateLogin = () => {
        createForm.setData({
            name: project.name || '',
            email: '',
            password: '',
            password_confirmation: '',
            send_welcome_email: true,
        });
        createForm.clearErrors();
        setExistingId('');
        setCreateOpen(true);
    };

    return <>
        {clients.length === 0 ? (
            <Button variant="ghost" size="sm" className="h-8 w-32 justify-start gap-1 px-2 text-blue-600 hover:text-blue-700" onClick={openCreateLogin}>
                <UserPlus className="h-4 w-4" />{t('Create Login')}
            </Button>
        ) : clients.map(client => <div className="flex w-[116px] items-center justify-between" key={client.id}>
            <Tooltip delayDuration={0}><TooltipTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8 text-blue-600" onClick={() => setProfileClient(client)}><UserCog className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t('Client Profile')} - {client.name}</TooltipContent></Tooltip>
            <Tooltip delayDuration={0}><TooltipTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8 text-amber-600" onClick={() => setPasswordClient(client)}><KeyRound className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t('Change Password')}</TooltipContent></Tooltip>
            <Tooltip delayDuration={0}><TooltipTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8 text-emerald-700" disabled={!client.is_enable_login || client.is_disable} onClick={() => router.post(route('video-production.client-access.impersonate', [project.id, client.id]))}><LogIn className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t('Login as Client')}</TooltipContent></Tooltip>
            {availableAccounts.length > 0 && <Tooltip delayDuration={0}><TooltipTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8 text-blue-600" onClick={openCreateLogin}><UserPlus className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent>{t('Add Client Access')}</TooltipContent></Tooltip>}
        </div>)}

        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
            <DialogContent className="sm:max-w-xl" onOpenAutoFocus={event => event.preventDefault()}>
                <DialogHeader><DialogTitle>{t('Create Production Client Login')}</DialogTitle></DialogHeader>
                {availableAccounts.length > 0 && <div className="space-y-2 rounded-md border p-4">
                    <Label>{t('Attach Existing Production Client')}</Label>
                    <div className="flex gap-2"><Select value={existingId} onValueChange={setExistingId}><SelectTrigger><SelectValue placeholder={t('Select client')} /></SelectTrigger><SelectContent>{availableAccounts.map(account => <SelectItem value={String(account.id)} key={account.id}>{account.name} ({account.email})</SelectItem>)}</SelectContent></Select><Button type="button" variant="outline" disabled={!existingId} onClick={attachExisting}>{t('Attach')}</Button></div>
                </div>}
                <form className="space-y-4" autoComplete="off" onSubmit={event => { event.preventDefault(); createLogin(); }}>
                    <div className="grid gap-4 sm:grid-cols-2"><div><Label required>{t('Client Name')}</Label><Input value={createForm.data.name} autoComplete="organization" onChange={e => createForm.setData('name', e.target.value)} /><InputError message={createForm.errors.name} /></div><div><Label required>{t('Login Email')}</Label><Input type="email" name="production_client_email" autoComplete="off" data-1p-ignore="true" data-lpignore="true" value={createForm.data.email} onChange={e => createForm.setData('email', e.target.value)} /><InputError message={createForm.errors.email} /></div></div>
                    <div className="grid gap-4 sm:grid-cols-2"><div><Label required>{t('Password')}</Label><Input type="password" name="production_client_new_password" autoComplete="new-password" data-1p-ignore="true" data-lpignore="true" value={createForm.data.password} onChange={e => createForm.setData('password', e.target.value)} /><InputError message={createForm.errors.password} /></div><div><Label required>{t('Confirm Password')}</Label><Input type="password" name="production_client_password_confirmation" autoComplete="new-password" data-1p-ignore="true" data-lpignore="true" value={createForm.data.password_confirmation} onChange={e => createForm.setData('password_confirmation', e.target.value)} /></div></div>
                    <label className="flex items-center gap-2 text-sm"><Checkbox checked={createForm.data.send_welcome_email} onCheckedChange={checked => createForm.setData('send_welcome_email', checked === true)} />{t('Send welcome email')}</label>
                    <p className="text-xs text-muted-foreground">{t('The email includes the login link and email address only. Share the password separately.')}</p>
                    <DialogFooter><Button type="button" variant="outline" onClick={() => setCreateOpen(false)}>{t('Cancel')}</Button><Button disabled={createForm.processing}>{t('Create Login')}</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog open={Boolean(profileClient)} onOpenChange={open => !open && setProfileClient(null)}><DialogContent className="sm:max-w-lg"><DialogHeader><DialogTitle>{t('Client Profile')}</DialogTitle></DialogHeader><form className="space-y-4" onSubmit={event => { event.preventDefault(); updateProfile(); }}><div><Label required>{t('Client Name')}</Label><Input value={profileForm.data.name} onChange={e => profileForm.setData('name', e.target.value)} /><InputError message={profileForm.errors.name} /></div><div><Label required>{t('Login Email')}</Label><Input type="email" value={profileForm.data.email} onChange={e => profileForm.setData('email', e.target.value)} /><InputError message={profileForm.errors.email} /></div><label className="flex items-center gap-2 text-sm"><Checkbox checked={profileForm.data.is_enable_login} onCheckedChange={checked => profileForm.setData('is_enable_login', checked === true)} />{t('Login enabled')}</label><DialogFooter><Button type="button" variant="outline" onClick={() => setProfileClient(null)}>{t('Cancel')}</Button><Button disabled={profileForm.processing}>{t('Save')}</Button></DialogFooter></form></DialogContent></Dialog>

        <Dialog open={Boolean(passwordClient)} onOpenChange={open => !open && setPasswordClient(null)}><DialogContent className="sm:max-w-lg"><DialogHeader><DialogTitle>{t('Change Password')} - {passwordClient?.name}</DialogTitle></DialogHeader><form className="space-y-4" onSubmit={event => { event.preventDefault(); updatePassword(); }}><div><Label required>{t('New Password')}</Label><Input type="password" value={passwordForm.data.password} onChange={e => passwordForm.setData('password', e.target.value)} /><InputError message={passwordForm.errors.password} /></div><div><Label required>{t('Confirm Password')}</Label><Input type="password" value={passwordForm.data.password_confirmation} onChange={e => passwordForm.setData('password_confirmation', e.target.value)} /></div><DialogFooter><Button type="button" variant="outline" onClick={() => setPasswordClient(null)}>{t('Cancel')}</Button><Button disabled={passwordForm.processing}>{t('Change Password')}</Button></DialogFooter></form></DialogContent></Dialog>
    </>;
}

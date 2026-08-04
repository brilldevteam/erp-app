import { FormEventHandler } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { History, LogOut, MonitorSmartphone, ShieldCheck } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import UpdatePasswordForm from '@/pages/profile/partials/update-password-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { formatDateTime } from '@/utils/helpers';
import { PageProps } from '@/types';

type SecuritySession = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    device: {
        browser_name?: string;
        os_name?: string;
        device_type?: string;
    };
    last_activity: string;
    is_current: boolean;
};

type LoginHistoryRecord = {
    id: number;
    ip: string | null;
    details: {
        browser_name?: string;
        os_name?: string;
        device_type?: string;
        location?: string;
    };
    type: string;
    created_at: string | null;
};

type SecurityPageProps = PageProps<{
    sessions: SecuritySession[];
    sessionListingAvailable: boolean;
    loginHistories: LoginHistoryRecord[];
}>;

export default function SecurityIndex() {
    const { t } = useTranslation();
    const pageProps = usePage<SecurityPageProps>().props;
    const { sessions, sessionListingAvailable, loginHistories } = pageProps;
    useFlashMessages();

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        current_password: '',
    });

    const logoutOtherSessions: FormEventHandler = (event) => {
        event.preventDefault();

        post(route('security.logout-other-sessions'), {
            preserveScroll: true,
            onSuccess: () => reset('current_password'),
        });
    };

    const destroySession = (session: SecuritySession) => {
        if (session.is_current) {
            return;
        }

        router.delete(route('security.sessions.destroy', session.id), {
            preserveScroll: true,
        });
    };

    const deviceLabel = (device: SecuritySession['device']) => {
        return [device.browser_name, device.os_name, device.device_type].filter(Boolean).join(' / ') || t('Unknown device');
    };

    const historyDeviceLabel = (details: LoginHistoryRecord['details']) => {
        return [details.browser_name, details.os_name, details.device_type].filter(Boolean).join(' / ') || t('Unknown device');
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Security Settings') }]}
            pageTitle={t('Security Settings')}
        >
            <Head title={t('Security Settings')} />

            <div className="space-y-6">
                <Card className="shadow-sm">
                    <CardHeader className="border-b bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <ShieldCheck className="h-5 w-5 text-primary" />
                            <div>
                                <CardTitle className="text-base">{t('Change Password')}</CardTitle>
                                <p className="mt-1 text-sm text-gray-600">{t('Protect your account with a strong password.')}</p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-6">
                        <UpdatePasswordForm />
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="border-b bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <MonitorSmartphone className="h-5 w-5 text-primary" />
                            <div>
                                <CardTitle className="text-base">{t('Active Sessions')}</CardTitle>
                                <p className="mt-1 text-sm text-gray-600">{t('Review devices currently signed in to your account.')}</p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6 p-6">
                        <div className="divide-y rounded-md border">
                            {!sessionListingAvailable ? (
                                <div className="p-4 text-sm text-gray-500">{t('Active session listing requires database session storage.')}</div>
                            ) : sessions.length === 0 ? (
                                <div className="p-4 text-sm text-gray-500">{t('No active sessions found.')}</div>
                            ) : sessions.map((session) => (
                                <div key={session.id} className="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between">
                                    <div className="space-y-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium text-gray-900">{deviceLabel(session.device)}</p>
                                            {session.is_current && <Badge variant="secondary">{t('Current')}</Badge>}
                                        </div>
                                        <p className="text-sm text-gray-600">{session.ip_address || t('Unknown IP')}</p>
                                        <p className="text-xs text-gray-500">{t('Last active')}: {formatDateTime(session.last_activity, pageProps)}</p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={session.is_current}
                                        onClick={() => destroySession(session)}
                                    >
                                        <LogOut className="mr-2 h-4 w-4" />
                                        {session.is_current ? t('Current') : t('Log out')}
                                    </Button>
                                </div>
                            ))}
                        </div>

                        <form onSubmit={logoutOtherSessions} className="rounded-md border bg-gray-50/50 p-4">
                            <div className="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                                <div>
                                    <Label htmlFor="logout_current_password">{t('Current Password')}</Label>
                                    <Input
                                        id="logout_current_password"
                                        type="password"
                                        value={data.current_password}
                                        onChange={(event) => setData('current_password', event.target.value)}
                                        placeholder={t('Enter current password')}
                                        className="mt-1"
                                        required
                                    />
                                    <InputError message={errors.current_password} className="mt-2" />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    <LogOut className="mr-2 h-4 w-4" />
                                    {t('Logout Other Devices')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card className="shadow-sm">
                    <CardHeader className="border-b bg-gray-50/50">
                        <div className="flex items-center gap-3">
                            <History className="h-5 w-5 text-primary" />
                            <div>
                                <CardTitle className="text-base">{t('Login History')}</CardTitle>
                                <p className="mt-1 text-sm text-gray-600">{t('Recent sign-in activity for this account.')}</p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-6">
                        <div className="divide-y rounded-md border">
                            {loginHistories.length === 0 ? (
                                <div className="p-4 text-sm text-gray-500">{t('No login history found.')}</div>
                            ) : loginHistories.map((history) => (
                                <div key={history.id} className="grid gap-2 p-4 md:grid-cols-[1fr_160px] md:items-center">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <p className="font-medium text-gray-900">{historyDeviceLabel(history.details)}</p>
                                            <Badge variant={history.type === 'success' ? 'default' : 'destructive'}>{t(history.type)}</Badge>
                                        </div>
                                        <p className="text-sm text-gray-600">{history.ip || t('Unknown IP')}</p>
                                        {history.details.location && (
                                            <p className="text-xs text-gray-500">{history.details.location}</p>
                                        )}
                                    </div>
                                    <p className="text-sm text-gray-600 md:text-right">
                                        {history.created_at ? formatDateTime(history.created_at, pageProps) : '-'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Headphones, Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

type EventKey =
    | 'ticket_created_support'
    | 'ticket_created_confirmation'
    | 'ticket_created_company'
    | 'ticket_assigned'
    | 'reply_to_customer'
    | 'reply_to_support'
    | 'status_changed';

interface HelpdeskNotificationValues {
    support_email: string;
    events: Record<EventKey, 'on' | 'off'>;
}

interface Props {
    auth?: any;
}

const GROUPS: Array<{ title: string; events: Array<{ key: EventKey; label: string; description: string }> }> = [
    {
        title: 'To the support team',
        events: [
            { key: 'ticket_created_support', label: 'New ticket', description: 'Sent to the support mailbox when a customer opens a ticket.' },
            { key: 'reply_to_support', label: 'Customer reply', description: 'Sent to the assigned agent, or to the support mailbox when nobody is assigned.' },
            { key: 'ticket_assigned', label: 'Ticket assigned', description: 'Sent to the agent a ticket is assigned to.' },
        ],
    },
    {
        title: 'To the customer',
        events: [
            { key: 'ticket_created_confirmation', label: 'Ticket received confirmation', description: 'Confirms to the customer that their ticket was received.' },
            { key: 'reply_to_customer', label: 'Support reply', description: 'Sent to the customer when the support team replies. Internal notes are never emailed.' },
            { key: 'status_changed', label: 'Status change', description: 'Sent to the customer when the ticket status changes.' },
        ],
    },
    {
        title: "To the customer's company",
        events: [
            { key: 'ticket_created_company', label: 'Ticket opened by a client or staff member', description: "Sent to the company's email when one of its clients or staff opens a ticket." },
        ],
    },
];

const emptyValues: HelpdeskNotificationValues = {
    support_email: '',
    events: {
        ticket_created_support: 'off',
        ticket_created_confirmation: 'off',
        ticket_created_company: 'off',
        ticket_assigned: 'off',
        reply_to_customer: 'off',
        reply_to_support: 'off',
        status_changed: 'off',
    },
};

export default function HelpdeskNotificationSettings({ auth }: Props) {
    const { t } = useTranslation();
    const { helpdeskNotificationSettings } = usePage().props as { helpdeskNotificationSettings?: HelpdeskNotificationValues | null };
    const canEdit = auth?.user?.permissions?.includes('manage-email-notification-settings');
    const [values, setValues] = useState<HelpdeskNotificationValues>(helpdeskNotificationSettings ?? emptyValues);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (helpdeskNotificationSettings) setValues(helpdeskNotificationSettings);
    }, [helpdeskNotificationSettings]);

    const toggle = (key: EventKey, checked: boolean) =>
        setValues(current => ({ ...current, events: { ...current.events, [key]: checked ? 'on' : 'off' } }));

    const save = () => {
        setSaving(true);
        setError(null);
        router.post(route('settings.helpdesk-notifications.update'), {
            settings: { support_email: values.support_email, ...values.events },
        }, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = page.props.flash as any;
                if (flash?.success) toast.success(flash.success);
                else if (flash?.error) toast.error(flash.error);
            },
            onError: (errors) => {
                const message = (errors['settings.support_email'] as string) || Object.values(errors).join(', ') || t('Failed to save Helpdesk notification settings');
                setError(message);
                toast.error(message);
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div className="order-1 rtl:order-2">
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <Headphones className="h-5 w-5" />
                        {t('Helpdesk Notifications')}
                    </CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t('Choose who receives Helpdesk emails. All Helpdesk emails are sent through the central email settings.')}
                    </p>
                </div>
                {canEdit && (
                    <Button className="order-2 rtl:order-1" size="sm" onClick={save} disabled={saving}>
                        <Save className="mr-2 h-4 w-4" />
                        {saving ? t('Saving...') : t('Save Changes')}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="space-y-2">
                    <Label htmlFor="helpdesk-support-email">{t('Support mailbox')}</Label>
                    <Input
                        id="helpdesk-support-email"
                        value={values.support_email}
                        onChange={(event) => setValues(current => ({ ...current, support_email: event.target.value }))}
                        placeholder="support@example.com"
                        disabled={!canEdit}
                    />
                    <p className="text-xs text-muted-foreground">
                        {t('Up to 5 addresses, separated by commas. Customer emails reply to the first address. When empty, support emails go to the super admin login email.')}
                    </p>
                    {error && <p className="text-sm text-destructive">{error}</p>}
                </div>

                {GROUPS.map(group => (
                    <div key={group.title} className="space-y-2">
                        <h3 className="text-sm font-semibold text-foreground">{t(group.title)}</h3>
                        <div className="divide-y rounded-lg border">
                            {group.events.map(event => (
                                <div key={event.key} className="flex items-center justify-between gap-4 p-4">
                                    <div>
                                        <Label htmlFor={`helpdesk-${event.key}`} className="text-sm font-medium">{t(event.label)}</Label>
                                        <p className="mt-0.5 text-xs text-muted-foreground">{t(event.description)}</p>
                                    </div>
                                    <Switch
                                        id={`helpdesk-${event.key}`}
                                        checked={values.events[event.key] === 'on'}
                                        onCheckedChange={(checked) => toggle(event.key, checked)}
                                        disabled={!canEdit}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

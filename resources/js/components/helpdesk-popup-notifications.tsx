import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Headphones, X } from 'lucide-react';
import { Button } from '@/components/ui/button';

type PopupNotification = {
    id: number;
    ticket_id: number;
    message: string;
    created_at: string;
};

export default function HelpdeskPopupNotifications() {
    const [notifications, setNotifications] = useState<PopupNotification[]>([]);

    const load = useCallback(async () => {
        try {
            const response = await fetch(route('helpdesk-popup-notifications.index'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (response.ok) {
                const data = await response.json();
                setNotifications(data.notifications ?? []);
            }
        } catch {
            // A later poll recovers after a temporary network interruption.
        }
    }, []);

    useEffect(() => {
        load();
        const timer = window.setInterval(load, 15_000);
        return () => window.clearInterval(timer);
    }, [load]);

    const acknowledge = async (notification: PopupNotification, action: 'viewed' | 'dismiss') => {
        const response = await fetch(route(`helpdesk-popup-notifications.${action}`, notification.id), {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!response.ok) return;

        setNotifications((current) => current.filter((item) => item.id !== notification.id));
        if (action === 'viewed') {
            router.visit(route('helpdesk-tickets.show', notification.ticket_id));
        }
    };

    if (!notifications.length) return null;

    return (
        <div className="fixed right-4 top-16 z-[80] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3" aria-live="polite">
            {notifications.map((notification) => (
                <div key={notification.id} className="border-border bg-background rounded-md border p-4 shadow-lg">
                    <div className="flex items-start gap-3">
                        <div className="bg-primary/10 text-primary mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md"><Headphones className="h-4 w-4" /></div>
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium leading-5">{notification.message}</p>
                            <Button className="mt-3" size="sm" onClick={() => acknowledge(notification, 'viewed')}>View Support Ticket</Button>
                        </div>
                        <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0" aria-label="Close notification" onClick={() => acknowledge(notification, 'dismiss')}><X className="h-4 w-4" /></Button>
                    </div>
                </div>
            ))}
        </div>
    );
}

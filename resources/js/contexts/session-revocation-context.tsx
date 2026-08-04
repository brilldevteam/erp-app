import { createContext, PropsWithChildren, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { SessionEndedModal } from '@/components/session-ended-modal';
import { PageProps } from '@/types';

type SessionRevokedDetail = {
    reason?: string;
};

type SessionRevocationContextValue = {
    revoked: boolean;
    revoke: (detail?: SessionRevokedDetail, broadcast?: boolean) => void;
};

const SessionRevocationContext = createContext<SessionRevocationContextValue | null>(null);
const channelName = 'erp-auth-session';
const storageKey = 'erp-auth-session-revoked';

export function SessionRevocationProvider({ children }: PropsWithChildren) {
    const { auth } = usePage<PageProps>().props;
    const [revoked, setRevoked] = useState(false);
    const revokedRef = useRef(false);
    const channelRef = useRef<BroadcastChannel | null>(null);

    const redirectToLogin = useCallback(() => {
        window.location.assign(route('login'));
    }, []);

    const revoke = useCallback((detail?: SessionRevokedDetail, broadcast = true) => {
        if (revokedRef.current) {
            return;
        }

        revokedRef.current = true;
        setRevoked(true);

        if (broadcast) {
            const payload = {
                type: 'SESSION_REVOKED',
                reason: detail?.reason || 'LOGOUT_OTHER_DEVICES',
                time: Date.now(),
            };

            try {
                channelRef.current?.postMessage(payload);
            } catch {
            }

            try {
                localStorage.setItem(storageKey, JSON.stringify(payload));
            } catch {
            }
        }
    }, []);

    useEffect(() => {
        const handleEvent = (event: Event) => {
            const customEvent = event as CustomEvent<SessionRevokedDetail>;
            revoke(customEvent.detail, true);
        };

        window.addEventListener('erp:session-revoked', handleEvent);

        if ('BroadcastChannel' in window) {
            channelRef.current = new BroadcastChannel(channelName);
            channelRef.current.onmessage = (event) => {
                if (event.data?.type === 'SESSION_REVOKED') {
                    revoke({ reason: event.data.reason }, false);
                }
            };
        }

        const handleStorage = (event: StorageEvent) => {
            if (event.key !== storageKey || !event.newValue) {
                return;
            }

            try {
                const payload = JSON.parse(event.newValue);
                if (payload?.type === 'SESSION_REVOKED') {
                    revoke({ reason: payload.reason }, false);
                }
            } catch {
            }
        };

        window.addEventListener('storage', handleStorage);

        return () => {
            window.removeEventListener('erp:session-revoked', handleEvent);
            window.removeEventListener('storage', handleStorage);
            channelRef.current?.close();
        };
    }, [revoke]);

    useEffect(() => {
        if (!auth?.user?.id || revoked) {
            return;
        }

        let stopped = false;
        let timer: number | undefined;

        const checkSession = async () => {
            if (stopped || revokedRef.current || document.hidden) {
                return;
            }

            try {
                const response = await fetch(route('security.session-status'), {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (response.status === 401) {
                    const payload = await response.clone().json().catch(() => null);
                    if (payload?.code === 'SESSION_REVOKED') {
                        revoke({ reason: payload.reason }, true);
                    }
                }
            } catch {
            }
        };

        timer = window.setInterval(checkSession, 5000);
        checkSession();

        return () => {
            stopped = true;
            if (timer) {
                window.clearInterval(timer);
            }
        };
    }, [auth?.user?.id, revoke, revoked]);

    return (
        <SessionRevocationContext.Provider value={{ revoked, revoke }}>
            <div className={revoked ? 'pointer-events-none select-none' : undefined} aria-hidden={revoked ? true : undefined}>
                {children}
            </div>
            <SessionEndedModal open={revoked} onRedirect={redirectToLogin} />
        </SessionRevocationContext.Provider>
    );
}

export function useSessionRevocation() {
    const context = useContext(SessionRevocationContext);

    if (!context) {
        throw new Error('useSessionRevocation must be used inside SessionRevocationProvider');
    }

    return context;
}

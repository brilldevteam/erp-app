import { useEffect, useState } from 'react';

export type TimeClockDeviceAccess = {
    allowed: boolean;
    device?: string;
    reason?: string | null;
    message?: string | null;
};

export const DESKTOP_ONLY_TIME_CLOCK_MESSAGE = 'This feature is only accessible from a desktop or laptop computer.';

const isClientMobileOrTablet = () => {
    if (typeof window === 'undefined' || typeof navigator === 'undefined') return false;

    const navigatorWithHints = navigator as Navigator & {
        userAgentData?: { mobile?: boolean };
        maxTouchPoints?: number;
    };
    const userAgent = navigator.userAgent.toLowerCase();
    const platform = (navigator.platform || '').toLowerCase();
    const isIpadosDesktopMode = platform === 'macintel' && (navigatorWithHints.maxTouchPoints || 0) > 1;

    if (navigatorWithHints.userAgentData?.mobile || isIpadosDesktopMode) return true;
    if (/ipad|tablet|kindle|silk\/|playbook|nexus\s*(7|9|10)|sm-t\d+|gt-p\d+|lenovo\s+tab|tab\s/.test(userAgent)) return true;
    if (userAgent.includes('android') && !userAgent.includes('mobile')) return true;

    return /mobile|iphone|ipod|android.*mobile|windows phone|iemobile|opera mini|opera mobi|blackberry|webos|okhttp|dalvik|cfnetwork|dart|flutter/.test(userAgent);
};

export function useTimeClockDeviceAccess(serverAccess?: TimeClockDeviceAccess | null): TimeClockDeviceAccess {
    const [clientBlocked, setClientBlocked] = useState(isClientMobileOrTablet);

    useEffect(() => {
        const blocked = isClientMobileOrTablet();
        setClientBlocked(blocked);
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = `time_clock_device=${blocked ? 'mobile_or_tablet' : 'desktop'}; Path=/; SameSite=Lax${secure}`;
    }, []);

    if (serverAccess?.allowed === false || clientBlocked) {
        return {
            allowed: false,
            device: serverAccess?.device || 'mobile_or_tablet',
            reason: 'desktop_only',
            message: serverAccess?.message || DESKTOP_ONLY_TIME_CLOCK_MESSAGE,
        };
    }

    return { allowed: true, device: serverAccess?.device || 'desktop', reason: null, message: null };
}

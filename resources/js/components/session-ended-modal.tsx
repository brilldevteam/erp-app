import { useEffect, useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { useTranslation } from 'react-i18next';

type SessionEndedModalProps = {
    open: boolean;
    onRedirect: () => void;
};

export function SessionEndedModal({ open, onRedirect }: SessionEndedModalProps) {
    const { t } = useTranslation();
    const [seconds, setSeconds] = useState(5);

    useEffect(() => {
        if (!open) {
            setSeconds(5);
            return;
        }

        const countdown = window.setInterval(() => {
            setSeconds((current) => Math.max(0, current - 1));
        }, 1000);

        const redirect = window.setTimeout(onRedirect, 5000);

        return () => {
            window.clearInterval(countdown);
            window.clearTimeout(redirect);
        };
    }, [onRedirect, open]);

    return (
        <AlertDialog open={open}>
            <AlertDialogContent
                className="z-[9999] mx-4 max-w-md"
                onEscapeKeyDown={(event) => event.preventDefault()}
            >
                <AlertDialogHeader>
                    <AlertDialogTitle>{t('Session Ended')}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {t('Your session has ended because your password was changed or your account was logged out from another device.')}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <p className="text-sm font-medium text-gray-700">
                    {t('Redirecting to login in {{seconds}} seconds...', { seconds })}
                </p>
                <AlertDialogFooter>
                    <AlertDialogAction onClick={onRedirect}>
                        {t('OK')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

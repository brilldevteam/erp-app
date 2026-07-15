import { useTranslation } from 'react-i18next';
import { AlertTriangle, Monitor } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DESKTOP_ONLY_TIME_CLOCK_MESSAGE } from '../Hooks/useTimeClockDeviceAccess';

export default function DesktopOnlyTimeClockAlert({ message }: { message?: string | null }) {
    const { t } = useTranslation();

    return (
        <Card className="border-amber-200 bg-amber-50 shadow-sm">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-amber-900">
                    <Monitor className="h-5 w-5" />
                    {t('My Time Clock')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="flex items-start gap-3 rounded-lg border border-amber-200 bg-white p-4 text-amber-900">
                    <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p className="font-medium">{t('Desktop or laptop required')}</p>
                        <p className="mt-1 text-sm">{t(message || DESKTOP_ONLY_TIME_CLOCK_MESSAGE)}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

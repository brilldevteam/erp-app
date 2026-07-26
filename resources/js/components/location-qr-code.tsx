import { useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

interface LocationQrCodeProps {
    url?: string;
    className?: string;
}

const isHttpUrl = (value: string) => {
    try {
        const parsed = new URL(value);
        return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch {
        return false;
    }
};

export function LocationQrCode({ url = '', className }: LocationQrCodeProps) {
    const { t } = useTranslation();
    const [dataUrl, setDataUrl] = useState('');

    useEffect(() => {
        let active = true;
        setDataUrl('');

        if (!isHttpUrl(url)) return () => { active = false; };

        QRCode.toDataURL(url, { width: 220, margin: 2 })
            .then((value) => { if (active) setDataUrl(value); })
            .catch(() => { if (active) setDataUrl(''); });

        return () => { active = false; };
    }, [url]);

    return (
        <div className={cn('flex min-h-32 items-center justify-center rounded-md border bg-white p-2', className)}>
            {dataUrl
                ? <img src={dataUrl} alt={t('Location QR Code')} className="h-28 w-28 object-contain" />
                : <span className="px-3 text-center text-xs text-muted-foreground">{t('Enter a valid map link to generate the QR code')}</span>}
        </div>
    );
}

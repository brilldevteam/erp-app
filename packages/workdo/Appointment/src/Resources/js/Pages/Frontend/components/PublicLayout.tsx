import { ReactNode, useEffect } from 'react';
import { Head, usePage } from '@inertiajs/react';
import Header from './Header';
import Footer from './Footer';
import { getImagePath } from '@/utils/helpers';
import { useTranslation } from 'react-i18next';

interface PublicLayoutProps {
    children: ReactNode;
    title: string;
    showSearchButton?: boolean;
}

export default function PublicLayout({ 
    children,
    title,
    showSearchButton = true
}: PublicLayoutProps) {
    const { i18n } = useTranslation();
    const { props } = usePage();
    const appointmentSettings = props.appointmentSettings as any;
    const locale = (props as any).locale as string | undefined;
    const pageTitle = `${appointmentSettings?.title_text || 'MeetSpace'} - ${title}`;

    useEffect(() => {
        const lang = locale || i18n.language || 'en';
        if (lang && i18n.language !== lang) {
            i18n.changeLanguage(lang);
        }

        const rtlLanguages = ['ar', 'he', 'fa', 'ur'];
        const isRTL = rtlLanguages.includes(lang);
        document.documentElement.dir = isRTL ? 'rtl' : 'ltr';
        document.documentElement.lang = lang;
    }, [locale, i18n]);

    return (
        <div className="min-h-screen bg-white flex flex-col">
            <Head title={pageTitle}>
                {appointmentSettings?.favicon && <link rel="icon" href={getImagePath(appointmentSettings.favicon)} />}
            </Head>

            <Header showSearchButton={showSearchButton} />

            <div className="flex-1">
                {children}
            </div>

            <Footer />
        </div>
    );
}

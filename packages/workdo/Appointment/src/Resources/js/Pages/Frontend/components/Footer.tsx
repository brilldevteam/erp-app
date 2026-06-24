import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function Footer() {
    const { t } = useTranslation();
    const { props } = usePage();
    const appointmentSettings = props.appointmentSettings as any;
    const userSlug = props.userSlug as string;

    return (
        <div className="bg-gray-800 text-white py-6 mt-auto">
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex flex-col sm:flex-row justify-between items-center text-sm">
                    <p>{appointmentSettings?.footer_text || t('© 2025 wazely.io. All rights reserved.')}</p>
                    <div className="flex space-x-6 mt-2 sm:mt-0">
                        <a href={route('appointment.public.privacy-policy', userSlug)} className="hover:text-blue-400 transition-colors">
                            {t('Privacy Policy')}
                        </a>
                        <a href={route('appointment.public.terms-conditions', userSlug)} className="hover:text-blue-400 transition-colors">
                            {t('Terms & Conditions')}
                        </a>
                        <a href={route('appointment.public.faq', userSlug)} className="hover:text-blue-400 transition-colors">
                            {t('FAQ')}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
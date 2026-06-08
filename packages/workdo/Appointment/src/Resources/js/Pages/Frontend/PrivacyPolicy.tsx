import { useTranslation } from 'react-i18next';
import PublicLayout from './components/PublicLayout';

interface PrivacyPolicyProps {
    privacySettings?: {
        content: string;
        enabled: boolean;
        userSlug: string;
    };
}

export default function PrivacyPolicy({ privacySettings }: PrivacyPolicyProps) {
    const { t } = useTranslation();

    return (
        <PublicLayout
            title={t('Privacy Policy')}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <span className="text-3xl">🔒</span>
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {t('Privacy Policy')}
                    </h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        {t('Learn how we protect and handle your personal information when you use our appointment booking system.')}
                    </p>
                </div>
            </section>

            {/* Content Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div className="bg-gradient-to-r from-gray-900 to-gray-800 px-8 py-6">
                            <h2 className="text-2xl font-bold text-white">{t('Privacy Policy')}</h2>
                        </div>

                        <div className="p-8">
                            <div
                                className="prose max-w-none [&_h1]:text-3xl [&_h1]:font-bold [&_h1]:text-gray-900 [&_h1]:mb-6 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-gray-900 [&_h2]:mb-4 [&_h2]:mt-8 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-gray-800 [&_h3]:mb-3 [&_h3]:mt-6 [&_p]:text-gray-700 [&_p]:mb-4 [&_p]:leading-relaxed [&_ul]:text-gray-700 [&_ul]:mb-4 [&_li]:mb-2 [&_strong]:text-gray-900 [&_strong]:font-semibold"
                                dangerouslySetInnerHTML={{
                                    __html: privacySettings?.content || '<p class="text-gray-500 italic">No privacy policy content available.</p>'
                                }}
                            />
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
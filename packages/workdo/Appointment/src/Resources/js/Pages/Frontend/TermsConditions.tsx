import { useTranslation } from 'react-i18next';
import { FileText } from 'lucide-react';
import PublicLayout from './components/PublicLayout';

interface TermsConditionsProps {
    termsSettings?: {
        content: string;
        enabled: boolean;
        userSlug: string;
    };
}

export default function TermsConditions({ termsSettings }: TermsConditionsProps) {
    const { t } = useTranslation();

    return (
        <PublicLayout
            title={t('Terms & Conditions')}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <FileText className="h-10 w-10 text-white" />
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {t('Terms & Conditions')}
                    </h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        {t('Please read these terms and conditions carefully before using our appointment booking service.')}
                    </p>
                </div>
            </section>

            {/* Content Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white rounded-2xl shadow-xl overflow-hidden">
                        <div className="bg-gradient-to-r from-slate-800 to-slate-900 px-8 py-6">
                            <h2 className="text-2xl font-bold text-white">{t('Legal Terms')}</h2>
                        </div>
                        <div className="p-8">
                            {termsSettings?.content ? (
                                <div
                                    className="prose max-w-none [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-gray-900 [&_h2]:mb-4 [&_p]:text-gray-700 [&_p]:mb-6 [&_ul]:text-gray-700 [&_li]:mb-2"
                                    dangerouslySetInnerHTML={{
                                        __html: termsSettings.content
                                    }}
                                />
                            ) : (
                                <div className="text-center py-12">
                                    <FileText className="h-16 w-16 text-gray-300 mx-auto mb-4" />
                                    <p className="text-gray-500 text-lg">{t('Terms and conditions content is not available at the moment.')}</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
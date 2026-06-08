import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Calendar, Search } from 'lucide-react';
import PublicLayout from './components/PublicLayout';

interface NotFoundProps {
    title?: string;
    message?: string;
    showSearchButton?: boolean;
}

export default function NotFound({
    title = 'Appointment Not Found',
    message = 'The appointment you are looking for could not be found or may have been removed.',
    showSearchButton = true
}: NotFoundProps) {
    const { t } = useTranslation();

    return (
        <PublicLayout
            title={t('Not Found')}
            showSearchButton={false}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-red-50 to-orange-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-24 h-24 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <Calendar className="w-12 h-12 text-white" />
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        <span className="text-red-600">404</span> - {t(title)}
                    </h1>
                    <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                        {t(message)}
                    </p>
                </div>
            </section>

            {/* Content Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div className="p-8">
                            <div className="mb-8">
                                <h2 className="text-2xl font-bold text-gray-900 mb-4">{t('What can you do?')}</h2>
                                <div className="bg-blue-50 rounded-xl p-6 max-w-md mx-auto">
                                    <Search className="h-8 w-8 text-blue-600 mx-auto mb-4" />
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">{t('Search Again')}</h3>
                                    <p className="text-gray-600 text-sm mb-4">{t('Try searching with different appointment details')}</p>
                                </div>
                            </div>

                            {showSearchButton && (
                                <Button
                                    onClick={() => window.history.back()}
                                    variant="outline"
                                    className="border-2 border-blue-600 text-blue-600 hover:bg-blue-50 px-8 py-3 text-lg font-semibold rounded-xl"
                                >
                                    <Search className="w-5 h-5 mr-2" />
                                    {t('Search Appointments')}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Calendar, Search } from 'lucide-react';
import { getImagePath } from '@/utils/helpers';

interface HeaderProps {
    showSearchButton?: boolean;
}

export default function Header({ showSearchButton = true }: HeaderProps) {
    const { t } = useTranslation();
    const { props } = usePage();
    const appointmentSettings = props.appointmentSettings as any;
    const userSlug = props.userSlug as string;

    return (
        <nav className="bg-white shadow-lg sticky top-0 z-50">
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex justify-between items-center h-16">
                    <div className="flex items-center">
                        {appointmentSettings?.logo_dark ? (
                            <img src={getImagePath(appointmentSettings.logo_dark)} alt={appointmentSettings?.title_text} className="h-10 w-auto" />
                        ) : (
                            <div className="flex items-center space-x-2">
                                <div className="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                                    <Calendar className="h-6 w-6 text-white" />
                                </div>
                                <span className="text-2xl font-bold text-gray-900">{appointmentSettings?.title_text || t('MeetSpace')}</span>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center">
                        {showSearchButton && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white transition-all"
                                onClick={() => router.get(route('appointment.public.search', userSlug))}
                            >
                                <Search className="h-4 w-4 mr-2" />
                                {t('Search Appointment')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </nav>
    );
}
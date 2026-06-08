import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Search, Calendar, Mail, Hash, AlertCircle } from 'lucide-react';
import PublicLayout from './components/PublicLayout';
import { useFormFields } from '@/hooks/useFormFields';

interface SearchProps {
    userSlug: string;
}

export default function SearchAppointment({ userSlug }: SearchProps) {
    const { t } = useTranslation();
    const tawktoFields = useFormFields('getIntegrationFields', {}, () => { }, {}, 'create', t, 'Appointment');
    const { data, setData, post, processing, errors } = useForm({
        appointment_number: '',
        email: ''
    });
    const [validationErrors, setValidationErrors] = useState<{[key: string]: string}>({});

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();

        // Reset validation errors
        setValidationErrors({});

        // Frontend validation
        const newErrors: {[key: string]: string} = {};

        if (!data.appointment_number.trim()) {
            newErrors.appointment_number = t('Appointment number is required');
        }

        if (!data.email.trim()) {
            newErrors.email = t('Email is required');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
            newErrors.email = t('Please enter a valid email address');
        }

        if (Object.keys(newErrors).length > 0) {
            setValidationErrors(newErrors);
            return;
        }

        post(route('appointment.public.search.post', userSlug), {
            onError: (errors) => {
                setValidationErrors(errors);
            }
        });
    };

    return (
        <PublicLayout
            title={t('Search Appointment')}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <Search className="w-10 h-10 text-white" />
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {t('Find Your')} <span className="text-blue-600">{t('Appointment')}</span>
                    </h1>
                    <p className="text-xl text-gray-600 mb-8 max-w-3xl mx-auto">
                        {t('Enter your appointment details below to view your booking information and manage your appointment.')}
                    </p>
                </div>
            </section>

            {/* Search Form Section */}
            <section className="py-16 bg-white">
                <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div className="bg-gradient-to-r from-gray-900 to-gray-800 px-8 py-8 text-center">
                            <h2 className="text-3xl font-bold text-white mb-2">{t('Search Your Booking')}</h2>
                            <p className="text-gray-300">{t('Enter your appointment number and email address')}</p>
                        </div>

                        <div className="p-8">
                        <form onSubmit={handleSearch} className="space-y-6">
                            {/* Appointment Number */}
                            <div className="space-y-3">
                                <Label htmlFor="appointment_number" className="text-base font-semibold text-gray-700">
                                    {t('Appointment Number')}
                                </Label>
                                <div className="relative">
                                    <Hash className="absolute left-4 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                                    <Input
                                        id="appointment_number"
                                        type="text"
                                        value={data.appointment_number}
                                        onChange={(e) => setData('appointment_number', e.target.value)}
                                        placeholder={t('Enter your appointment number')}
                                        className={`pl-12 h-14 border-2 border-gray-200 focus:border-blue-500 focus:ring-blue-500/20 rounded-xl text-base ${
                                            (validationErrors.appointment_number || errors.appointment_number) ? 'border-red-500' : ''
                                        }`}
                                        required
                                    />
                                </div>
                                {(validationErrors.appointment_number || errors.appointment_number) && (
                                    <div className="flex items-center mt-2 text-red-600 text-sm">
                                        <AlertCircle className="h-4 w-4 mr-2" />
                                        {validationErrors.appointment_number || errors.appointment_number}
                                    </div>
                                )}
                            </div>

                            {/* Email Address */}
                            <div className="space-y-3">
                                <Label htmlFor="email" className="text-base font-semibold text-gray-700">
                                    {t('Email Address')}
                                </Label>
                                <div className="relative">
                                    <Mail className="absolute left-4 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder={t('Enter your email address')}
                                        className={`pl-12 h-14 border-2 border-gray-200 focus:border-blue-500 focus:ring-blue-500/20 rounded-xl text-base ${
                                            (validationErrors.email || errors.email) ? 'border-red-500' : ''
                                        }`}
                                        required
                                    />
                                </div>
                                {(validationErrors.email || errors.email) && (
                                    <div className="flex items-center mt-2 text-red-600 text-sm">
                                        <AlertCircle className="h-4 w-4 mr-2" />
                                        {validationErrors.email || errors.email}
                                    </div>
                                )}
                            </div>

                            {/* Search Button */}
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-4 h-14 rounded-xl font-semibold text-lg shadow-lg transition-all duration-200"
                            >
                                <Search className="h-5 w-5 mr-3" />
                                {processing ? t('Searching...') : t('Search Appointment')}
                            </Button>
                        </form>


                        </div>
                    </div>
                </div>
            </section>

            {/* TawktoMessenger Integration */}
            {tawktoFields.map((field) => (
                <div key={field.id}>
                    {field.component}
                </div>
            ))}
        </PublicLayout>
    );
}
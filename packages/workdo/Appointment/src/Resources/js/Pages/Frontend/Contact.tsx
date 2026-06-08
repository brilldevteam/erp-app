import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Mail, Phone, MapPin, Clock, Send } from 'lucide-react';
import PublicLayout from './components/PublicLayout';

interface ContactProps {
    brandSettings: {
        logo_dark: string;
        favicon: string;
        titleText: string;
        footerText: string;
    };
    userSlug?: string;
}

export default function Contact({ brandSettings, userSlug }: ContactProps) {
    const { t } = useTranslation();
    const [isSubmitting, setIsSubmitting] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        phone: '',
        subject: '',
        message: ''
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        post('/contact', {
            onSuccess: () => {
                reset();
                setIsSubmitting(false);
            },
            onError: () => {
                setIsSubmitting(false);
            }
        });
    };

    return (
        <PublicLayout userSlug={userSlug || window.location.pathname.split("/")[4]}
            title={t('Contact Us')}
            brandSettings={brandSettings}
            showBookButton={true}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <Mail className="h-10 w-10 text-white" />
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {t('Contact Us')}
                    </h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        {t('Get in touch with us for any questions about our appointment booking service. We\'re here to help!')}
                    </p>
                </div>
            </section>

            {/* Contact Section */}
            <section className="py-16 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        {/* Contact Information */}
                        <div className="space-y-8">
                            <div>
                                <h2 className="text-3xl font-bold text-gray-900 mb-6">{t('Get in Touch')}</h2>
                                <p className="text-lg text-gray-600 mb-8">
                                    {t('We\'d love to hear from you. Send us a message and we\'ll respond as soon as possible.')}
                                </p>
                            </div>

                            <div className="space-y-6">
                                <div className="flex items-start space-x-4">
                                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <Mail className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Email')}</h3>
                                        <p className="text-gray-600">support@appointmentbooking.com</p>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-4">
                                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <Phone className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Phone')}</h3>
                                        <p className="text-gray-600">+1 (555) 123-4567</p>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-4">
                                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <MapPin className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Address')}</h3>
                                        <p className="text-gray-600">123 Business Street<br />Suite 100<br />City, State 12345</p>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-4">
                                    <div className="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <Clock className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Business Hours')}</h3>
                                        <p className="text-gray-600">
                                            {t('Monday - Friday: 9:00 AM - 6:00 PM')}<br />
                                            {t('Saturday: 10:00 AM - 4:00 PM')}<br />
                                            {t('Sunday: Closed')}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Contact Form */}
                        <div className="bg-white rounded-2xl shadow-xl overflow-hidden">
                            <div className="bg-gradient-to-r from-slate-800 to-slate-900 px-8 py-6">
                                <h2 className="text-2xl font-bold text-white">{t('Send Message')}</h2>
                            </div>
                            <div className="p-8">
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div>
                                            <Label htmlFor="name" className="text-sm font-medium text-gray-700">
                                                {t('Full Name')} *
                                            </Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                className="mt-2 h-12"
                                                placeholder={t('Enter your full name')}
                                                required
                                            />
                                            {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                                        </div>

                                        <div>
                                            <Label htmlFor="email" className="text-sm font-medium text-gray-700">
                                                {t('Email Address')} *
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                className="mt-2 h-12"
                                                placeholder={t('Enter your email')}
                                                required
                                            />
                                            {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div>
                                            <Label htmlFor="phone" className="text-sm font-medium text-gray-700">
                                                {t('Phone Number')}
                                            </Label>
                                            <Input
                                                id="phone"
                                                type="tel"
                                                value={data.phone}
                                                onChange={(e) => setData('phone', e.target.value)}
                                                className="mt-2 h-12"
                                                placeholder={t('Enter your phone number')}
                                            />
                                            {errors.phone && <p className="text-red-500 text-sm mt-1">{errors.phone}</p>}
                                        </div>

                                        <div>
                                            <Label htmlFor="subject" className="text-sm font-medium text-gray-700">
                                                {t('Subject')} *
                                            </Label>
                                            <Input
                                                id="subject"
                                                type="text"
                                                value={data.subject}
                                                onChange={(e) => setData('subject', e.target.value)}
                                                className="mt-2 h-12"
                                                placeholder={t('Enter subject')}
                                                required
                                            />
                                            {errors.subject && <p className="text-red-500 text-sm mt-1">{errors.subject}</p>}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="message" className="text-sm font-medium text-gray-700">
                                            {t('Message')} *
                                        </Label>
                                        <Textarea
                                            id="message"
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            className="mt-2 min-h-[120px]"
                                            placeholder={t('Enter your message...')}
                                            required
                                        />
                                        {errors.message && <p className="text-red-500 text-sm mt-1">{errors.message}</p>}
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing || isSubmitting}
                                        className="w-full h-12 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold rounded-xl transition-all duration-200"
                                    >
                                        {processing || isSubmitting ? (
                                            <div className="flex items-center space-x-2">
                                                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                                                <span>{t('Sending...')}</span>
                                            </div>
                                        ) : (
                                            <div className="flex items-center space-x-2">
                                                <Send className="h-5 w-5" />
                                                <span>{t('Send Message')}</span>
                                            </div>
                                        )}
                                    </Button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
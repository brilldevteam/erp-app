import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, Calendar, Clock, User, Mail, Phone, Search, Printer, Download } from 'lucide-react';
import PublicLayout from './components/PublicLayout';
import { useRef, useEffect, useState } from 'react';
import html2pdf from 'html2pdf.js';
import { toast } from 'sonner';
import { usePage } from '@inertiajs/react';



interface SuccessProps {
    schedule: {
        id: number;
        unique_id: string;
        name: string;
        email: string;
        phone: string;
        date: string;
        start_time: string;
        end_time: string;
        formatted_date: string;
        formatted_start_time: string;
        formatted_end_time: string;
        status: string;
        appointment: {
            appointment_name: string;
            encrypted_id: string;
        };
    };
    userSlug?: string;
}

export default function Success({ schedule, userSlug }: SuccessProps) {
    const { t } = useTranslation();
    const printRef = useRef<HTMLDivElement>(null);
    const { props } = usePage();
    const [isDownloading, setIsDownloading] = useState(false);

    useEffect(() => {
        if (props.flash?.success) {
            toast.success(props.flash.success, {
                position: 'top-center',
                duration: 5000,
            });
        }
    }, [props.flash]);



    const downloadPDF = () => {
        if (!printRef.current) return;

        setIsDownloading(true);
        const element = printRef.current;
        const opt = {
            margin: 0.5,
            filename: `appointment-${schedule.unique_id}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save().then(() => {
            setIsDownloading(false);
        });
    };

    return (
        <PublicLayout
            title={t('Appointment Success')}
        >

            {/* Hero Success Section */}
            <section className="bg-gradient-to-br from-green-50 to-emerald-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-24 h-24 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <CheckCircle className="w-12 h-12 text-white" />
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-4">
                        {t('Booking')} <span className="text-green-600">{t('Confirmed!')}</span>
                    </h1>
                    <p className="text-xl text-gray-600 mb-8">
                        {t('Your appointment has been successfully scheduled. We look forward to meeting with you.')}
                    </p>
                    <div className="inline-flex items-center bg-white rounded-full px-6 py-3 shadow-md">
                        <span className="text-sm font-medium text-gray-600 mr-2">{t('Confirmation ID:')}</span>
                        <span className="text-lg font-bold text-green-600">{schedule.unique_id}</span>
                    </div>
                </div>
            </section>

            {/* Appointment Details Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div ref={printRef} className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div className="bg-gradient-to-r from-gray-900 to-gray-800 px-8 py-6 text-center">
                            <h2 className="text-2xl font-bold text-white">{t('Appointment Details')}</h2>
                        </div>


                        <div className="p-8">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div className="bg-blue-50 rounded-xl p-6">
                                    <div className="flex items-center mb-4">
                                        <Calendar className="h-6 w-6 text-blue-600 mr-3" />
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Date & Time')}</h3>
                                    </div>
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm text-gray-600">{t('Date')}</p>
                                            <p className="text-lg font-semibold text-gray-900">{schedule.formatted_date}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-600">{t('Time')}</p>
                                            <p className="text-lg font-semibold text-gray-900">{schedule.formatted_start_time} - {schedule.formatted_end_time}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="bg-green-50 rounded-xl p-6">
                                    <div className="flex items-center mb-4">
                                        <User className="h-6 w-6 text-green-600 mr-3" />
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Contact Information')}</h3>
                                    </div>
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm text-gray-600">{t('Name')}</p>
                                            <p className="text-lg font-semibold text-gray-900">{schedule.name}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-gray-600">{t('Email')}</p>
                                            <p className="text-lg font-semibold text-gray-900">{schedule.email}</p>
                                        </div>
                                        {schedule.phone && (
                                            <div>
                                                <p className="text-sm text-gray-600">{t('Phone')}</p>
                                                <p className="text-lg font-semibold text-gray-900">{schedule.phone}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="mt-8 bg-gray-50 rounded-xl p-6">
                                <div className="flex items-center mb-4">
                                    <CheckCircle className="h-6 w-6 text-gray-600 mr-3" />
                                    <h3 className="text-lg font-semibold text-gray-900">{t('Appointment Type')}</h3>
                                </div>
                                <p className="text-xl font-bold text-blue-600">{schedule.appointment?.appointment_name || '-'}</p>
                            </div>

                            {/* Action Buttons */}
                            {!isDownloading && (
                                <div className="flex flex-col sm:flex-row gap-4 justify-center mt-8 pt-6 border-t border-gray-200">
                                <Button
                                    onClick={() => {
                                        const slug = userSlug || window.location.pathname.split('/')[4];
                                        window.location.href = route('appointment.public.book', { userSlug: slug, encryptedId: schedule.appointment.encrypted_id });
                                    }}
                                    className="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-3 text-lg font-semibold rounded-xl shadow-lg"
                                >
                                    {t('Book Another Appointment')}
                                </Button>
                                <Button
                                    variant="outline"
                                    className="border-2 border-blue-600 text-blue-600 hover:bg-blue-50 px-8 py-3 text-lg font-semibold rounded-xl"
                                    onClick={downloadPDF}
                                >
                                    <Download className="w-5 h-5 mr-2" />
                                    {t('Download PDF')}
                                </Button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}
import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DatePicker } from '@/components/ui/date-picker';
import { TimePicker } from '@/components/ui/time-picker';
import { Calendar, Clock, Mail, Phone, Search, User, Users } from 'lucide-react';
import { formatDate, formatTime } from '@/utils/helpers';
import PublicLayout from './components/PublicLayout';
import { toast } from 'sonner';

interface Schedule {
    id: number;
    unique_id: string;
    name: string;
    email: string;
    phone: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    appointment: {
        appointment_name: string;
        appointment_type: string;
    };
}

interface SearchResultsProps {
    schedule: Schedule & {
        formatted_date: string;
        formatted_start_time: string;
        formatted_end_time: string;
    };
    userSlug: string;
}

export default function SearchResults({ schedule, userSlug }: SearchResultsProps) {
    const { t } = useTranslation();
    const [showCallback, setShowCallback] = useState(false);
    const [showCancel, setShowCancel] = useState(false);

    const { data: callbackData, setData: setCallbackData, post: postCallback, processing: callbackProcessing } = useForm({
        date: '',
        start_time: '',
        end_time: '',
        reason: ''
    });

    const { data: cancelData, setData: setCancelData, post: postCancel, processing: cancelProcessing } = useForm({
        reason: ''
    });

    const handleCallback = (e: React.FormEvent) => {
        e.preventDefault();
        postCallback(route('appointment.public.callback', { userSlug, uniqueId: schedule.unique_id }), {
            onSuccess: () => {
                setCallbackData({
                    date: '',
                    start_time: '',
                    end_time: '',
                    reason: ''
                });
                setShowCallback(false);
                toast.success('Callback appointment requested successfully!');
            }
        });
    };

    const handleCancel = (e: React.FormEvent) => {
        e.preventDefault();
        postCancel(route('appointment.public.cancel', { userSlug, uniqueId: schedule.unique_id }), {
            onSuccess: () => {
                setCancelData({ reason: '' });
                toast.success('Appointment cancelled successfully!');
            }
        });
    };

    return (
        <PublicLayout
            title={t('Search Results')}
            showSearchButton={true}
        >
            {/* Hero Section */}
            <section className="bg-gradient-to-br from-blue-50 to-indigo-100 py-20">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <div className="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg">
                        <Calendar className="w-10 h-10 text-white" />
                    </div>
                    <h1 className="text-5xl font-bold text-gray-900 mb-6">
                        {t('Your')} <span className="text-blue-600">{t('Appointment')}</span>
                    </h1>
                    <p className="text-xl text-gray-600 mb-8">
                        {t('Here are the details of your scheduled appointment. You can manage your booking below.')}
                    </p>
                    <div className="inline-flex items-center bg-white rounded-full px-6 py-3 shadow-md">
                        <span className="text-sm font-medium text-gray-600 mr-2">{t('ID:')}</span>
                        <span className="text-lg font-bold text-blue-600">{schedule.unique_id}</span>
                    </div>
                </div>
            </section>

            {/* Appointment Details Section */}
            <section className="py-16 bg-white">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div className="bg-gradient-to-r from-gray-900 to-gray-800 px-8 py-6 text-center">
                            <h2 className="text-2xl font-bold text-white">{t('Appointment Information')}</h2>
                        </div>

                        <div className="p-8">
                    <div className="space-y-8">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                                <div className="bg-blue-50 rounded-xl p-6">
                                    <div className="flex items-center mb-4">
                                        <User className="h-6 w-6 text-blue-600 mr-3" />
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Contact Details')}</h3>
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

                                <div className="bg-green-50 rounded-xl p-6">
                                    <div className="flex items-center mb-4">
                                        <Calendar className="h-6 w-6 text-green-600 mr-3" />
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Appointment Details')}</h3>
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
                                        <div>
                                            <p className="text-sm text-gray-600">{t('Type')}</p>
                                            <p className="text-lg font-semibold text-gray-900">{schedule.appointment.appointment_name}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="bg-gray-50 rounded-xl p-6 mb-8">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center">
                                        <div className="w-10 h-10 bg-gray-200 rounded-lg flex items-center justify-center mr-3">
                                            <Clock className="h-5 w-5 text-gray-600" />
                                        </div>
                                        <h3 className="text-lg font-semibold text-gray-900">{t('Status')}</h3>
                                    </div>
                                    <div className={`px-4 py-2 text-sm font-semibold rounded-full ${
                                        schedule.status === 'approved' ? 'bg-green-100 text-green-800 border border-green-200' :
                                        schedule.status === 'complete' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                                        schedule.status === 'reject' ? 'bg-red-100 text-red-800 border border-red-200' :
                                        schedule.status === 'cancel' ? 'bg-gray-100 text-gray-800 border border-gray-200' : 
                                        'bg-yellow-100 text-yellow-800 border border-yellow-200'
                                    }`}>
                                        {schedule.status === 'approved' ? t('Approved') : 
                                         schedule.status === 'complete' ? t('Complete') :
                                         schedule.status === 'reject' ? t('Rejected') :
                                         schedule.status === 'cancel' ? t('Cancelled') :
                                         t('Pending')}
                                    </div>
                                </div>
                            </div>

                            {/* Callback Appointment - Show only if status is complete */}
                            {schedule.status === 'complete' && (
                                <>
                                    <div className="mb-6">
                                        <Button
                                            onClick={() => setShowCallback(!showCallback)}
                                            className="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl font-semibold"
                                        >
                                            {t('Request Callback Appointment')}
                                        </Button>
                                    </div>

                                    {showCallback && (
                                        <div className="bg-blue-50 rounded-xl p-6 mb-6">
                                            <h3 className="text-xl font-bold text-blue-600 mb-6">{t('Schedule Callback Appointment')}</h3>
                                        <form onSubmit={handleCallback} className="space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-700 mb-2 block">{t('Date')}</Label>
                                                    <DatePicker
                                                        value={callbackData.date}
                                                        onChange={(value) => setCallbackData('date', value)}
                                                        placeholder={t('Select date')}
                                                        minDate={new Date()}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-700 mb-2 block">{t('Start Time')}</Label>
                                                    <TimePicker
                                                        value={callbackData.start_time}
                                                        onChange={(value) => setCallbackData('start_time', value)}
                                                        placeholder={t('Select start time')}
                                                        required
                                                    />
                                                </div>
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-700 mb-2 block">{t('End Time')}</Label>
                                                    <TimePicker
                                                        value={callbackData.end_time}
                                                        onChange={(value) => setCallbackData('end_time', value)}
                                                        placeholder={t('Select end time')}
                                                        required
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <Label htmlFor="callback_reason" className="text-sm font-medium text-gray-700">{t('Reason for Callback')}</Label>
                                                <Textarea
                                                    id="callback_reason"
                                                    value={callbackData.reason}
                                                    onChange={(e) => setCallbackData('reason', e.target.value)}
                                                    placeholder={t('Enter reason for callback...')}
                                                    className="mt-1"
                                                    rows={3}
                                                    required
                                                />
                                            </div>
                                            <Button type="submit" disabled={callbackProcessing} className="bg-blue-600 hover:bg-blue-700">
                                                {callbackProcessing ? t('Creating...') : t('Create')}
                                            </Button>
                                        </form>
                                    </div>
                                )}
                            </>
                        )}

                            {/* Cancel Form - Show only if status is pending or approved */}
                            {(schedule.status === 'pending' || schedule.status === 'approved') && (
                                <div className="bg-red-50 rounded-xl p-6">
                                    <h3 className="text-xl font-bold text-red-600 mb-6">{t('Cancel Appointment')}</h3>
                                <form onSubmit={handleCancel} className="space-y-4">
                                    <div>
                                        <Label htmlFor="cancel_reason" className="text-sm font-medium text-gray-700">{t('Reason for Cancellation')}</Label>
                                        <Textarea
                                            id="cancel_reason"
                                            value={cancelData.reason}
                                            onChange={(e) => setCancelData('reason', e.target.value)}
                                            placeholder={t('Enter reason for cancellation...')}
                                            className="mt-1"
                                            rows={4}
                                            required
                                        />
                                    </div>
                                    <Button type="submit" disabled={cancelProcessing} className="bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white px-6 py-3 rounded-xl font-semibold">
                                        {cancelProcessing ? t('Submitting...') : t('Cancel Appointment')}
                                    </Button>
                                </form>
                            </div>
                        )}
                    </div>
                        </div>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Calendar, Clock, Phone, Users, CheckCircle, XCircle } from "lucide-react";
import { ViewAppointmentProps } from './types';

export default function ViewAppointment({ appointment, onClose }: ViewAppointmentProps) {
    const { t } = useTranslation();

    const formatWeekDays = (weekDays: string[] | string) => {
        let days = [];
        if (typeof weekDays === 'string') {
            try {
                days = JSON.parse(weekDays);
            } catch {
                days = [weekDays];
            }
        } else if (Array.isArray(weekDays)) {
            days = weekDays;
        }

        const dayMap: Record<string, string> = {
            'monday': t('Monday'),
            'tuesday': t('Tuesday'),
            'wednesday': t('Wednesday'),
            'thursday': t('Thursday'),
            'friday': t('Friday'),
            'saturday': t('Saturday'),
            'sunday': t('Sunday')
        };

        return days.map(day => dayMap[day] || day).join(', ');
    };

    const formatQuestions = (questionIds: string[] | string) => {
        let ids = [];
        if (typeof questionIds === 'string') {
            try {
                ids = JSON.parse(questionIds);
            } catch {
                ids = [questionIds];
            }
        } else if (Array.isArray(questionIds)) {
            ids = questionIds;
        }
        return ids.length;
    };

    return (
        <DialogContent className="max-w-2xl">
            <DialogHeader>
                <DialogTitle className="flex items-center gap-2 mb-2">
                    <Calendar className="h-5 w-5 text-primary" />
                    {t('Appointment Details')}
                </DialogTitle>
            </DialogHeader>

            <div className="space-y-6">
                {/* Basic Information */}
                <Card>
                    <CardContent className="p-4">
                        <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            {t('Basic Information')}
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Appointment Name')}</label>
                                <p className="text-sm font-semibold text-gray-900 mt-1">{appointment.appointment_name}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Type')}</label>
                                <div className="mt-1">
                                    <span className={`px-2 py-1 rounded-full text-sm ${appointment.appointment_type === '0' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                        {appointment.appointment_type === '0' ? t('Paid') : t('Free')}
                                    </span>
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600 flex items-center gap-1">
                                    {t('Duration')}
                                </label>
                                <p className="text-sm text-gray-900 mt-1">{appointment.duration} {t('minutes')}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Enabled')}</label>
                                <div className="mt-1 flex items-center gap-2">
                                    <span className={`px-2 py-1 rounded-full text-sm ${appointment.enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                        {appointment.enabled ? t('On') : t('Off')}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Schedule Information */}
                <Card>
                    <CardContent className="p-4">
                        <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
                            <Calendar className="h-4 w-4" />
                            {t('Schedule Information')}
                        </h3>
                        <div className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Available Days')}</label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {formatWeekDays(appointment.week_day).split(', ').map((day, index) => (
                                        <Badge key={index} variant="outline">{day}</Badge>
                                    ))}
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-gray-600">{t('Phone Enabled')}</label>
                                <span className={`px-2 py-1 rounded-full text-sm ${appointment.phone_enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                    {appointment.phone_enabled ? t('Yes') : t('No')}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Questions Information */}
                <Card>
                    <CardContent className="p-4">
                        <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
                            <Users className="h-4 w-4" />
                            {t('Questions & Custom Fields')}
                        </h3>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Total Questions')}</label>
                            <p className="text-sm text-gray-900 mt-1">
                                {formatQuestions(appointment.question_ids)} {t('questions configured')}
                            </p>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </DialogContent>
    );
}

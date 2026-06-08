import { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { TimePicker } from '@/components/ui/time-picker';
import { Save } from 'lucide-react';
import SystemSetupSidebar from '../SystemSetupSidebar';

interface AppointmentHoursProps {
    hours: Record<string, any>;
    weekdays: string[];
}

interface DayHours {
    start_time: string;
    end_time: string;
    add_day_off: boolean;
}

export default function AppointmentHours({ hours, weekdays }: AppointmentHoursProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const canEdit = auth?.user?.permissions?.includes('manage-appointment-settings');

    useFlashMessages();

    const weekDays = [
        { key: 'monday', label: t('Monday'), number: '1.' },
        { key: 'tuesday', label: t('Tuesday'), number: '2.' },
        { key: 'wednesday', label: t('Wednesday'), number: '3.' },
        { key: 'thursday', label: t('Thursday'), number: '4.' },
        { key: 'friday', label: t('Friday'), number: '5.' },
        { key: 'saturday', label: t('Saturday'), number: '6.' },
        { key: 'sunday', label: t('Sunday'), number: '7.' }
    ];

    const [dayHours, setDayHours] = useState<Record<string, DayHours>>({});

    useEffect(() => {
        const updatedHours: Record<string, DayHours> = {};

        weekDays.forEach(day => {
            const startTime = hours?.[day.key]?.start_time || '';
            const endTime = hours?.[day.key]?.end_time || '';

            updatedHours[day.key] = {
                start_time: startTime ? startTime.substring(0, 5) : '',
                end_time: endTime ? endTime.substring(0, 5) : '',
                add_day_off: Boolean(hours?.[day.key]?.day_off)
            };
        });

        setDayHours(updatedHours);
    }, [hours]);

    const [processing, setProcessing] = useState(false);

    const updateDayHour = (day: string, field: keyof DayHours, value: string | boolean) => {
        setDayHours(prev => ({
            ...prev,
            [day]: { ...prev[day], [field]: value }
        }));
    };

    const handleSubmit = () => {
        setProcessing(true);

        router.post(route('appointment.settings.hours.store'), { data: dayHours }, {
            onError: () => {
                setProcessing(false);
            },
            onFinish: () => {
                setProcessing(false);
            }
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Appointment'), url: route('appointment.index') },
                { label: t('System Setup') },
                { label: t('Appointment Hours') }
            ]}
            pageTitle={t('System Setup')}
        >
            <Head title={t('Appointment Hours')} />

            <div className="flex flex-col md:flex-row gap-8">
                <div className="md:w-64 flex-shrink-0">
                    <SystemSetupSidebar activeItem="appointment-hours" />
                </div>

                <div className="flex-1">
                    <Card className="shadow-sm">
                        <CardContent className="p-6">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium">{t('Appointment Hours')}</h3>
                            </div>

                            <div className="space-y-4">
                                    {weekDays.map((day) => {
                                        const hours = dayHours[day.key];
                                        const isDisabled = hours?.add_day_off;

                                        return (
                                            <div key={day.key} className="flex items-center gap-4 p-4 border rounded-lg">
                                                <div className="w-24 flex items-center gap-2">
                                                    <span className="text-green-600 font-medium">{day.number}</span>
                                                    <span className="text-green-600 font-medium">{day.label}</span>
                                                </div>

                                                <div className="flex-1 grid grid-cols-2 gap-4">
                                                    <TimePicker
                                                    id={`start_time_${day.key}`}
                                                        value={hours?.start_time || ''}
                                                        onChange={(value) => updateDayHour(day.key, 'start_time', value)}
                                                        disabled={isDisabled}
                                                    placeholder={t('Start time')}
                                                    className={isDisabled ? 'opacity-50' : ''}
                                                    />
                                                <TimePicker
                                                    id={`end_time_${day.key}`}
                                                        value={hours?.end_time || ''}
                                                        onChange={(value) => updateDayHour(day.key, 'end_time', value)}
                                                        disabled={isDisabled}
                                                        placeholder={t('End time')}
                                                        className={isDisabled ? 'opacity-50' : ''}
                                                    />
                                                </div>

                                                <div className="flex items-center space-x-2">
                                                    <Checkbox
                                                        id={`add_day_off_${day.key}`}
                                                        checked={hours?.add_day_off || false}
                                                        onCheckedChange={(checked) => updateDayHour(day.key, 'add_day_off', !!checked)}
                                                    />
                                                    <Label htmlFor={`add_day_off_${day.key}`} className="text-sm whitespace-nowrap">
                                                        {t('Add day off')}
                                                    </Label>
                                                </div>
                                            </div>
                                        );
                                    })}
                            </div>

                            {canEdit && (
                                <div className="flex justify-end pt-6 border-t">
                                    <Button onClick={handleSubmit} disabled={processing}>
                                        <Save className="h-4 w-4 mr-2" />
                                        {processing ? t('Saving...') : t('Save Changes')}
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

import { DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Check, X } from 'lucide-react';
import { formatDate, formatTime } from '@/utils/helpers';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useFormFields } from '@/hooks/useFormFields';
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
    questions: string;
    questions_with_names?: Record<string, string>;
    cancel_description: string;
    appointment: {
        appointment_name: string;
        appointment_type: string;
    };
}
interface User {
    id: number;
    name: string;
}
interface ViewProps {
    schedule: Schedule;
    users: User[];
    onClose: () => void;
    auth?: {
        user: {
            permissions: string[];
        };
    };
}
export default function View({ schedule, users, onClose, auth }: ViewProps) {
    const { t } = useTranslation();
    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [userSelectionError, setUserSelectionError] = useState<string>('');

    const { data, setData, errors } = useForm({
        sync_to_google_calendar: false,
        sync_to_outlook_calendar: false,
    });

    // Calendar sync fields
    const calendarFields = useFormFields('getCalendarSyncFields', data, setData, errors, 'create', t, 'Appointment');

    const getStatusBadge = (status: string) => {
        const colors = {
            'pending': 'bg-yellow-100 text-yellow-800',
            'approved': 'bg-green-100 text-green-800',
            'reject': 'bg-red-100 text-red-800',
            'complete': 'bg-blue-100 text-blue-800'
        };
        return (
            <span className={`px-2 py-1 rounded-full text-sm ${colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800'}`}>
                {t(status)}
            </span>
        );
    };
    const parseQuestions = (questionsStr: string) => {
        try {
            return JSON.parse(questionsStr || '{}');
        } catch {
            return {};
        }
    };
    return (
        <DialogContent className="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{t('Schedule Details')}</DialogTitle>
                <DialogDescription>
                    {t('Schedule')}: {schedule.unique_id}
                </DialogDescription>
            </DialogHeader>
            <div className="space-y-6">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Name')}</Label>
                        <div className="mt-1 font-medium">{schedule.name}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Email')}</Label>
                        <div className="mt-1 font-medium">{schedule.email}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Phone')}</Label>
                        <div className="mt-1 font-medium">{schedule.phone}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Date')}</Label>
                        <div className="mt-1 font-medium">{formatDate(schedule.date)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Start Time')}</Label>
                        <div className="mt-1 font-medium">{formatTime(schedule.start_time)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('End Time')}</Label>
                        <div className="mt-1 font-medium">{formatTime(schedule.end_time)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Appointment')}</Label>
                        <div className="mt-1 font-medium">{schedule.appointment?.appointment_name || '-'}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Status')}</Label>
                        <div className="mt-1">{getStatusBadge(schedule.status)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Appointment Type')}</Label>
                        <div className="mt-1">
                            <span className={`px-2 py-1 rounded-full text-sm ${schedule.appointment?.appointment_type === '0' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                {schedule.appointment?.appointment_type === '0' ? t('Paid') : t('Free')}
                            </span>
                        </div>
                    </div>
                </div>
                {schedule.questions && Object.keys(parseQuestions(schedule.questions)).length > 0 && (
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Questions & Answers')}</Label>
                        <div className="mt-2 space-y-2">
                            {Object.entries(parseQuestions(schedule.questions)).map(([question, answer], index) => (
                                <div key={index} className="space-y-2 border border-gray-200 rounded p-3">
                                    <div className="font-medium text-gray-900">
                                        {t('Question')}: {question}
                                    </div>
                                    <div className="text-gray-700 bg-gray-50 p-1 rounded">
                                        {t('Answer')}: {answer || t('No answer')}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
                {schedule.status === 'cancel' && schedule.cancel_description && (
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Cancel Description')}</Label>
                        <div className="mt-2 bg-red-50 p-3 rounded-lg">
                            <p className="text-sm text-red-800">{schedule.cancel_description}</p>
                        </div>
                    </div>
                )}
                {schedule.status === 'pending' && (
                    <>
                        <div>
                            <Label className="text-sm font-medium text-gray-500">
                                {t('Assign User')}
                            </Label>
                            <Select value={selectedUserId} onValueChange={(value) => {
                                setSelectedUserId(value);
                                setUserSelectionError('');
                            }}>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('Select User')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {users?.map((user) => (
                                        <SelectItem key={user.id} value={user.id.toString()}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {userSelectionError && (
                                <p className="text-red-500 text-sm mt-1">{userSelectionError}</p>
                            )}
                        </div>

                        {/* Calendar Sync Fields */}
                        {calendarFields.map((field) => (
                            <div key={field.id}>
                                {field.component}
                            </div>
                        ))}
                    </>
                )}
            </div>
            {schedule.status === 'pending' && auth?.user?.permissions?.includes('schedule-actions') && (
                <DialogFooter className="flex justify-end gap-2 pt-4">
                    <Button
                        onClick={() => {
                            if (!selectedUserId) {
                                setUserSelectionError(t('Please select a user before approving'));
                                return;
                            }
                            setIsProcessing(true);
                            router.post(route('appointment.schedules.approve', schedule.id), {
                                user_id: selectedUserId,
                                sync_to_google_calendar: data.sync_to_google_calendar,
                                sync_to_outlook_calendar: data.sync_to_outlook_calendar
                            }, {
                                onFinish: () => {
                                    setIsProcessing(false);
                                    onClose();
                                }
                            });
                        }}
                        disabled={isProcessing}
                        className="bg-green-600 hover:bg-green-700"
                    >
                        <Check className="w-4 h-4" />
                        {t('Approve')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={() => {
                            setIsProcessing(true);
                            router.post(route('appointment.schedules.reject', schedule.id), {
                                sync_to_google_calendar: data.sync_to_google_calendar,
                                sync_to_outlook_calendar: data.sync_to_outlook_calendar
                            }, {
                                onFinish: () => {
                                    setIsProcessing(false);
                                    onClose();
                                }
                            });
                        }}
                        disabled={isProcessing}
                    >
                        <X className="w-4 h-4" />
                        {t('Reject')}
                    </Button>
                </DialogFooter>
            )}
            {schedule.status === 'approved' && auth?.user?.permissions?.includes('schedule-actions') && (
                <DialogFooter className="flex justify-end gap-2 pt-4">
                    <Button
                        onClick={() => {
                            setIsProcessing(true);
                            router.post(route('appointment.schedules.complete', schedule.id), {
                                sync_to_google_calendar: data.sync_to_google_calendar,
                                sync_to_outlook_calendar: data.sync_to_outlook_calendar
                            }, {
                                onFinish: () => {
                                    setIsProcessing(false);
                                    onClose();
                                }
                            });
                        }}
                        disabled={isProcessing}
                        className="bg-blue-600 hover:bg-blue-700"
                    >
                        <Check className="w-4 h-4" />
                        {t('Complete')}
                    </Button>
                </DialogFooter>
            )}
        </DialogContent>
    );
}

import { DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Check, X } from 'lucide-react';
import { formatDate, formatTime } from '@/utils/helpers';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface AppointmentCallback {
    id: number;
    unique_code: string;
    reason: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    schedule: {
        name: string;
        email: string;
        phone: string;
    };
    appointment: {
        appointment_name: string;
        appointment_type: string;
    };
}

interface ViewProps {
    callback: AppointmentCallback;
    onClose: () => void;
}

export default function View({ callback, onClose }: ViewProps) {
    const { t } = useTranslation();
    const [isProcessing, setIsProcessing] = useState(false);

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

    return (
        <DialogContent className="max-w-2xl">
            <DialogHeader>
                <DialogTitle>{t('Callback Details')}</DialogTitle>
                <DialogDescription>
                    {t('Callback')}: {callback.unique_code}
                </DialogDescription>
            </DialogHeader>

            <div className="space-y-6">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Name')}</Label>
                        <div className="mt-1 font-medium">{callback.schedule?.name}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Email')}</Label>
                        <div className="mt-1 font-medium">{callback.schedule?.email}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Phone')}</Label>
                        <div className="mt-1 font-medium">{callback.schedule?.phone}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Date')}</Label>
                        <div className="mt-1 font-medium">{formatDate(callback.date)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Start Time')}</Label>
                        <div className="mt-1 font-medium">{formatTime(callback.start_time)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('End Time')}</Label>
                        <div className="mt-1 font-medium">{formatTime(callback.end_time)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Appointment')}</Label>
                        <div className="mt-1 font-medium">{callback.appointment?.appointment_name || '-'}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Status')}</Label>
                        <div className="mt-1">{getStatusBadge(callback.status)}</div>
                    </div>
                    <div>
                        <Label className="text-sm font-medium text-gray-500">{t('Appointment Type')}</Label>
                        <div className="mt-1">
                            <span className={`px-2 py-1 rounded-full text-sm ${callback.appointment?.appointment_type === '0' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                {callback.appointment?.appointment_type === '0' ? t('Paid') : t('Free')}
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <Label className="text-sm font-medium text-gray-500">{t('Reason')}</Label>
                    <div className="mt-2 bg-gray-50 p-4 rounded-lg">
                        <p className="text-sm text-gray-900 whitespace-pre-wrap">{callback.reason}</p>
                    </div>
                </div>
            </div>

            {callback.status === 'pending' && (
                <DialogFooter className="flex justify-end gap-2 pt-4">
                    <Button
                        onClick={() => {
                            setIsProcessing(true);
                            router.post(route('appointment.callbacks.approve', callback.id), {}, {
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
                            router.post(route('appointment.callbacks.reject', callback.id), {}, {
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

            {callback.status === 'approved' && (
                <DialogFooter className="flex justify-end gap-2 pt-4">
                    <Button
                        onClick={() => {
                            setIsProcessing(true);
                            router.post(route('appointment.callbacks.complete', callback.id), {}, {
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
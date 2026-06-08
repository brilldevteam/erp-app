import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MultiSelectEnhanced } from '@/components/ui/multi-select-enhanced';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { EditAppointmentProps, EditAppointmentFormData } from './types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

export default function EditAppointment({ appointment, onSuccess }: EditAppointmentProps) {
    const {  } = usePage<any>().props;

    const { t } = useTranslation();
    const parseJsonField = (field: any) => {
        if (Array.isArray(field)) return field;
        try {
            return JSON.parse(field || '[]');
        } catch {
            return [];
        }
    };

    const { data, setData, put, processing, errors } = useForm<EditAppointmentFormData>({
        appointment_name: appointment.appointment_name ?? '',
        appointment_type: appointment.appointment_type?.toString() ?? '0',
        week_day: parseJsonField(appointment.week_day),
        duration: appointment.duration?.toString() ?? '',
        phone_enabled: appointment.phone_enabled ?? false,
        question_ids: parseJsonField(appointment.question_ids),
        enabled: appointment.enabled ?? true,
    });

    const [availableQuestions, setAvailableQuestions] = useState([]);

    useEffect(() => {
        axios.get(route('appointment.questions.api'))
            .then(response => {
                setAvailableQuestions(response.data || []);
            })
            .catch(() => setAvailableQuestions([]));
    }, []);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('appointment.appointments.update', appointment.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Edit Appointment')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="appointment_name">{t('Appointment Name')}</Label>
                    <Input
                        id="appointment_name"
                        value={data.appointment_name}
                        onChange={(e) => setData('appointment_name', e.target.value)}
                        placeholder={t('Enter Appointment Name')}
                        required
                    />
                    <InputError message={errors.appointment_name} />
                </div>

                <div>
                    <Label htmlFor="appointment_type">{t('Appointment Type')}</Label>
                    <Select value={data.appointment_type?.toString() || '0'} onValueChange={(value) => setData('appointment_type', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0">{t('paid')}</SelectItem>
                            <SelectItem value="1">{t('free')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.appointment_type} />
                </div>

                <div>
                    <Label required>{t('Week Day')} </Label>
                    <MultiSelectEnhanced
                        options={[{ value: 'monday', label: t('monday') }, { value: 'tuesday', label: t('tuesday') }, { value: 'wednesday', label: t('wednesday') }, { value: 'thursday', label: t('thursday') }, { value: 'friday', label: t('friday') }, { value: 'saturday', label: t('saturday') }, { value: 'sunday', label: t('sunday') }]}
                        value={data.week_day}
                        onValueChange={(value) => setData('week_day', value)}
                        placeholder={t('Select Week Day...')}
                        searchable={false}
                    />
                    <InputError message={errors.week_day} />
                </div>

                <div>
                    <Label htmlFor="duration" required>{t('Duration (Minutes)')} </Label>
                    <Input
                        id="duration"
                        type="number"
                        step="1"
                        min="0"
                        value={data.duration}
                        onChange={(e) => setData('duration', e.target.value)}
                        placeholder={t('Enter duration')}
                    />
                    <InputError message={errors.duration} />
                </div>

                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="phone_enabled"
                        checked={data.phone_enabled || false}
                        onCheckedChange={(checked) => setData('phone_enabled', !!checked)}
                    />
                    <Label htmlFor="phone_enabled" className="cursor-pointer">{t('Phone Enabled')}</Label>
                    <InputError message={errors.phone_enabled} />
                </div>

                <div>
                    <Label>{t('Questions & Custom Field')}</Label>
                    <div className="flex flex-col gap-3 mt-2">
                        {availableQuestions.map((question: any) => (
                            <div key={question.id} className="flex items-center space-x-2">
                                <Checkbox
                                    id={`question_${question.id}`}
                                    checked={data.question_ids.includes(question.id.toString()) || data.question_ids.includes(question.id)}
                                    onCheckedChange={(checked) => {
                                        const questionIdStr = question.id.toString();
                                        if (checked) {
                                            if (!data.question_ids.includes(questionIdStr) && !data.question_ids.includes(question.id)) {
                                                setData('question_ids', [...data.question_ids, questionIdStr]);
                                            }
                                        } else {
                                            setData('question_ids', data.question_ids.filter(id => id !== questionIdStr && id !== question.id));
                                        }
                                    }}

                                />
                                <Label htmlFor={`question_${question.id}`} className="cursor-pointer">
                                    {question.question_name}
                                    {question.required_answer && <span className="text-red-500 ml-1">*</span>}
                                </Label>
                            </div>
                        ))}
                    </div>
                    <InputError message={errors.question_ids} />
                </div>

                <div className="flex items-center space-x-2">
                    <Switch
                        id="enabled"
                        checked={data.enabled || false}
                        onCheckedChange={(checked) => setData('enabled', !!checked)}
                    />
                    <Label htmlFor="enabled" className="cursor-pointer">{t('Enabled')}</Label>
                    <InputError message={errors.enabled} />
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Updating...') : t('Update')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}
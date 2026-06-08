import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CurrencyInput } from '@/components/ui/currency-input';
import { DatePicker } from '@/components/ui/date-picker';
import { usePage } from '@inertiajs/react';

interface EditContractTemplateProps {
    template: any;
    onSuccess: () => void;
}

interface EditContractTemplateFormData {
    subject: string;
    user_id: string;
    value: string;
    type_id: string;
    start_date: string;
    end_date: string;
    description: string;
    status: string;
}

export default function Edit({ template, onSuccess }: EditContractTemplateProps) {
    const { users, contractTypes } = usePage<any>().props;
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<EditContractTemplateFormData>({
        subject: template.subject || '',
        user_id: template.user_id?.toString() || '',
        value: template.value?.toString() || '',
        type_id: template.type_id?.toString() || '',
        start_date: template.start_date || '',
        end_date: template.end_date || '',
        description: template.description || '',
        status: template.status || 'draft',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('contract-templates.update', template.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Edit Contract Template')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4 mt-3">
                <div>
                    <Label htmlFor="subject">{t('Subject')}</Label>
                    <Input
                        id="subject"
                        type="text"
                        value={data.subject}
                        onChange={(e) => setData('subject', e.target.value)}
                        placeholder={t('Enter Subject')}
                        required
                    />
                    <InputError message={errors.subject} />
                </div>

                <div>
                    <CurrencyInput
                        label={t('Value')}
                        value={data.value}
                        onChange={(value) => setData('value', value)}
                        error={errors.value}
                        required
                    />
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label required>{t('Start Date')}</Label>
                        <DatePicker
                            value={data.start_date}
                            onChange={(date) => setData('start_date', date)}
                            placeholder={t('Select Start Date')}
                        />
                        <InputError message={errors.start_date} />
                    </div>

                    <div>
                        <Label required>{t('End Date')}</Label>
                        <DatePicker
                            value={data.end_date}
                            onChange={(date) => setData('end_date', date)}
                            placeholder={t('Select End Date')}
                        />
                        <InputError message={errors.end_date} />
                    </div>
                </div>

                <div>
                    <Label htmlFor="status" required>{t('Status')}</Label>
                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="draft">{t('Draft')}</SelectItem>
                            <SelectItem value="active">{t('Active')}</SelectItem>
                            <SelectItem value="archived">{t('Archived')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>

                <div>
                    <Label htmlFor="type_id" required>{t('Contract Type')}</Label>
                    <Select value={data.type_id?.toString() || ''} onValueChange={(value) => setData('type_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Contract Type')} />
                        </SelectTrigger>
                        <SelectContent searchable={true}>
                            {contractTypes && typeof contractTypes === 'object' && Object.entries(contractTypes).map(([id, name]) => (
                                <SelectItem key={id} value={id.toString()}>
                                    {String(name)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type_id} />
                </div>

                <div>
                    <Label htmlFor="user_id">{t('Users')}</Label>
                    <Select value={data.user_id?.toString() || ''} onValueChange={(value) => setData('user_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Users (Optional)')} />
                        </SelectTrigger>
                        <SelectContent searchable={true}>
                            {users && typeof users === 'object' && Object.entries(users).map(([id, name]) => (
                                <SelectItem key={id} value={id.toString()}>
                                    {String(name)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.user_id} />
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
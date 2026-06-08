import React from 'react';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { CurrencyInput } from '@/components/ui/currency-input';
import InputError from '@/components/ui/input-error';

interface Props {
    template: any;
    contractTypes: Record<number, string>;
    users?: Record<number, string>;
    actionType?: 'duplicate' | 'convert' | 'convertToTemplate';
    open: boolean;
    onClose: () => void;
}

export default function DuplicateModal({ template, contractTypes, users, actionType = 'duplicate', open, onClose }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({
        subject: actionType === 'duplicate' ? `${template.subject} (Copy)` : template.subject,
        user_id: template.user_id?.toString() || '',
        value: template.value?.toString() || '',
        type_id: template.type_id?.toString() || '',
        start_date: template.start_date || '',
        end_date: template.end_date || '',
        description: template.description || '',
        status: 'draft',
        comments_duplicate: false,
        notes_duplicate: false,
        attachments_duplicate: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        let routeName = 'contract-templates.duplicate';
        if (actionType === 'convert') {
            routeName = 'contract-templates.convert-to-contract';
        } else if (actionType === 'convertToTemplate') {
            routeName = 'contract-templates.convert-to-template';
        }
        post(route(routeName, template.id), {
            onSuccess: () => {
                reset();
                onClose();
            }
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {actionType === 'convert' ? t('Convert to Contract') :
                            actionType === 'convertToTemplate' ? t('Convert to Template') :
                                t('Duplicate Template')}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4 mt-3">
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
                                required
                                value={data.start_date}
                                onChange={(date) => setData('start_date', date)}
                                placeholder={t('Select Start Date')}

                            />
                            <InputError message={errors.start_date} />
                        </div>

                        <div>
                            <Label required>{t('End Date')}</Label>
                            <DatePicker
                                required
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
                                {contractTypes && typeof contractTypes === 'object' && (
                                    Array.isArray(contractTypes)
                                        ? contractTypes.map((type: any) => (
                                            <SelectItem key={type.id} value={type.id.toString()}>
                                                {type.name}
                                            </SelectItem>
                                        ))
                                        : Object.entries(contractTypes).map(([id, name]) => (
                                            <SelectItem key={id} value={id.toString()}>
                                                {String(name)}
                                            </SelectItem>
                                        ))
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.type_id} />
                    </div>

                    {users && (
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
                    )}


                    <div className="border-t pt-4">
                        <Label className="text-base font-medium">{t('Copy Options')}</Label>
                        <div className="space-y-3 mt-3">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="comments_duplicate"
                                    checked={data.comments_duplicate}
                                    onCheckedChange={(checked) => setData('comments_duplicate', !!checked)}
                                />
                                <Label htmlFor="comments_duplicate" className="text-sm">
                                    {t('Copy comments')}
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="notes_duplicate"
                                    checked={data.notes_duplicate}
                                    onCheckedChange={(checked) => setData('notes_duplicate', !!checked)}
                                />
                                <Label htmlFor="notes_duplicate" className="text-sm">
                                    {t('Copy notes')}
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="attachments_duplicate"
                                    checked={data.attachments_duplicate}
                                    onCheckedChange={(checked) => setData('attachments_duplicate', !!checked)}
                                />
                                <Label htmlFor="attachments_duplicate" className="text-sm">
                                    {t('Copy attachments')}
                                </Label>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? (actionType === 'duplicate' ? t('Duplicating...') : t('Converting...'))
                                : (actionType === 'duplicate' ? t('Duplicate') : t('Convert'))
                            }
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { DatePicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import { CurrencyInput } from '@/components/ui/currency-input';
import { CreatePettyCashProps, CreatePettyCashFormData } from './types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { useFormFields } from '@/hooks/useFormFields';


export default function Create({ onSuccess }: CreatePettyCashProps) {
    const {  } = usePage<any>().props;

    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm<CreatePettyCashFormData>({
        date: new Date().toISOString().split('T')[0],
        added_amount: '0',
        remarks: '',
    });



    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('petty-cash-management.petty-cashes.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    // Bank Account Field
    const bankAccountField = useFormFields('bankAccountField', data, setData, errors);

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Create Petty Cash')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label required>{t('Date')}</Label>
                    <DatePicker
                        value={data.date}
                        onChange={(date) => setData('date', date)}
                        placeholder={t('Select Date')}
                        required
                    />
                    <InputError message={errors.date} />
                </div>

                <CurrencyInput
                    label={t('Amount')}
                    value={data.added_amount}
                    onChange={(value) => setData('added_amount', value)}
                    error={errors.added_amount}
                    required
                />
                
                {bankAccountField.map((field) => (
                    <div key={field.id}>{field.component}</div>
                ))}
                <div>
                    <Label htmlFor="remarks">{t('Remarks')}</Label>
                    <Textarea
                        id="remarks"
                        value={data.remarks}
                        onChange={(e) => setData('remarks', e.target.value)}
                        placeholder={t('Enter Remarks')}
                        rows={3}
                    />
                    <InputError message={errors.remarks} />
                </div>

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}

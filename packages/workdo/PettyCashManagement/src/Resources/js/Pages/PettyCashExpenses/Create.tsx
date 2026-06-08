import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { DateTimeRangePicker } from '@/components/ui/datetime-range-picker';
import { CreatePettyCashExpenseProps, CreatePettyCashExpenseFormData } from './types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

export default function Create({ onSuccess }: CreatePettyCashExpenseProps) {
    const { pettycashrequests, users } = usePage<any>().props;

    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm<CreatePettyCashExpenseFormData>({
        request_id: '',
        type: '0',
        amount: '',
        remarks: '',
        status: '0',
        approved_at: '',
        approved_by: '',
        created_by: '',
    });



    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('petty-cash-management.petty-cash-expenses.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Create Expense')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="request_id">{t('Request ID')}</Label>
                    <Select value={data.request_id?.toString() || ''} onValueChange={(value) => setData('request_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Request ID')} />
                        </SelectTrigger>
                        <SelectContent>
                            {pettycashrequests?.map((item: any) => (
                                <SelectItem key={item.id} value={item.id.toString()}>
                                    {item.id}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.request_id} />
                </div>

                <div>
                    <Label htmlFor="type">{t('Type')}</Label>
                    <Select value={data.type?.toString() || '0'} onValueChange={(value) => setData('type', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0">{t('Reimbursement')}</SelectItem>
                            <SelectItem value="1">{t('Petty Cash')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type} />
                </div>

                <div>
                    <Label htmlFor="amount">{t('Amount')}</Label>
                    <Input
                        id="amount"
                        type="text"
                        value={data.amount}
                        onChange={(e) => setData('amount', e.target.value)}
                        placeholder={t('Enter Amount')}
                        required
                    />
                    <InputError message={errors.amount} />
                </div>

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

                <div>
                    <Label htmlFor="status">{t('Status')}</Label>
                    <Select value={data.status?.toString() || '0'} onValueChange={(value) => setData('status', value)}>
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="0">{t('Pending')}</SelectItem>
                            <SelectItem value="1">{t('Approved')}</SelectItem>
                            <SelectItem value="2">{t('Rejected')}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>

                <div>
                    <Label>{t('Approved At')}</Label>
                    <DateTimeRangePicker
                        value={data.approved_at}
                        onChange={(value) => setData('approved_at', value)}
                        placeholder={t('Select Approved At')}
                        mode="single"
                    />
                    <InputError message={errors.approved_at} />
                </div>

                <div>
                    <Label htmlFor="approved_by">{t('Approved By')}</Label>
                    <Select value={data.approved_by?.toString() || ''} onValueChange={(value) => setData('approved_by', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Approved By')} />
                        </SelectTrigger>
                        <SelectContent>
                            {users?.map((item: any) => (
                                <SelectItem key={item.id} value={item.id.toString()}>
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.approved_by} />
                </div>

                <div>
                    <Label htmlFor="created_by">{t('Created By')}</Label>
                    <Select value={data.created_by?.toString() || ''} onValueChange={(value) => setData('created_by', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Created By')} />
                        </SelectTrigger>
                        <SelectContent>
                            {users?.map((item: any) => (
                                <SelectItem key={item.id} value={item.id.toString()}>
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.created_by} />
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

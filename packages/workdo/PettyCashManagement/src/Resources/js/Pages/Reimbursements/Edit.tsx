import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CurrencyInput } from '@/components/ui/currency-input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { DateTimeRangePicker } from '@/components/ui/datetime-range-picker';
import { Input } from '@/components/ui/input';
import { EditReimbursementProps, EditReimbursementFormData } from './types';
import { formatDate, formatTime, formatDateTime, formatCurrency, getImagePath } from '@/utils/helpers';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

export default function EditReimbursement({ reimbursement, onSuccess }: EditReimbursementProps) {
    const { users, categories } = usePage<any>().props;
    const [filteredCategories, setFilteredCategories] = useState(categories || []);
    const [receiptFile, setReceiptFile] = useState<File | null>(null);
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, transform } = useForm<EditReimbursementFormData>({
        user_id: reimbursement.user_id?.toString() || '',
        category_id: reimbursement.category_id?.toString() || '',
        amount: reimbursement.amount ?? '',
        description: reimbursement.description ?? '',
        receipt_path: reimbursement.receipt_path || '',
    });

    transform((data) => {
        const formData = new FormData();
        formData.append('user_id', data.user_id);
        formData.append('category_id', data.category_id);
        formData.append('amount', data.amount);
        formData.append('description', data.description);
        formData.append('_method', 'PUT');
        if (receiptFile) {
            formData.append('receipt_path', receiptFile);
        }
        return formData;
    });

    useEffect(() => {
        if (data.user_id) {
            axios.get(route('pettycashmanagement.users.categories', data.user_id))
                .then(response => {
                    setFilteredCategories(response.data);
                })
                .catch(() => {
                    setFilteredCategories([]);
                });
        } else {
            setFilteredCategories(categories || []);
            setData('category_id', '');
        }
    }, [data.user_id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('petty-cash-management.reimbursements.update', reimbursement.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Edit Reimbursement')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="user_id" required>{t('User')}</Label>
                    <Select value={data.user_id?.toString() || ''} onValueChange={(value) => setData('user_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select User')} />
                        </SelectTrigger>
                        <SelectContent>
                            {users?.map((item: any) => (
                                <SelectItem key={item.id} value={item.id.toString()}>
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.user_id} />
                </div>

                <div>
                    <Label htmlFor="category_id" required>{t('Category')}</Label>
                    <Select value={data.category_id?.toString() || ''} onValueChange={(value) => setData('category_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Category')} />
                        </SelectTrigger>
                        <SelectContent>
                            {categories?.map((item: any) => (
                                <SelectItem key={item.id} value={item.id.toString()}>
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.category_id} />
                </div>

                <div>
                    <CurrencyInput
                        label={t('Amount')}
                        value={data.amount}
                        onChange={(value) => setData('amount', value)}
                        error={errors.amount}
                        required
                    />
                </div>

                <div>
                    <Label htmlFor="receipt">{t('Upload Payment Receipt')}</Label>
                    <Input
                        id="receipt"
                        type="file"
                        accept=".png,.jpg,.jpeg,.pdf"
                        onChange={(e) => setReceiptFile(e.target.files?.[0] || null)}
                    />
                    {data.receipt_path && !receiptFile && (
                        <div className="mt-2">
                            <p className="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                {t('Current Receipt')}:
                            </p>
                            <img
                                src={getImagePath(data.receipt_path)}
                                alt="Current receipt"
                                className="max-w-xs max-h-32 object-contain border rounded"
                            />
                        </div>
                    )}
                    {receiptFile && (
                        <div className="mt-2">
                            <p className="text-sm text-green-600 dark:text-green-400 mb-2">
                                {t('New Receipt')}: {receiptFile.name}
                            </p>
                            {receiptFile.type.startsWith('image/') && (
                                <img
                                    src={URL.createObjectURL(receiptFile)}
                                    alt="New receipt preview"
                                    className="max-w-xs max-h-32 object-contain border rounded"
                                />
                            )}
                        </div>
                    )}
                    <InputError message={errors.receipt_path} />
                </div>

                <div>
                    <Label htmlFor="description">{t('Description')}</Label>
                    <Textarea
                        id="description"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        placeholder={t('Enter Description')}
                        rows={3}
                    />
                    <InputError message={errors.description} />
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

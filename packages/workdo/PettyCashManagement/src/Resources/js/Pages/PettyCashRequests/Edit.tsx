import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Textarea } from '@/components/ui/textarea';
import { EditPettyCashRequestProps, EditPettyCashRequestFormData } from './types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

export default function EditPettyCashRequest({ pettycashrequest, onSuccess }: EditPettyCashRequestProps) {
    const { users, pettycashcategories } = usePage<any>().props;
    const [filteredCategories, setFilteredCategories] = useState(pettycashcategories || []);
    const [filteredApprovedBies, setFilteredApprovedBies] = useState(users || []);
    const [filteredCreatedBies, setFilteredCreatedBies] = useState(users || []);
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<EditPettyCashRequestFormData>({
        user_id: pettycashrequest.user_id?.toString() || '',
        categorie_id: pettycashrequest.categorie_id?.toString() || '',
        requested_amount: pettycashrequest.requested_amount ?? '',
        remarks: pettycashrequest.remarks ?? '',
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
            setFilteredCategories(pettycashcategories || []);
            setData('categorie_id', '');
        }
    }, [data.user_id]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('petty-cash-management.petty-cash-requests.update', pettycashrequest.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Edit Petty Cash Request')}</DialogTitle>
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
                    <Label htmlFor="categorie_id" required>{t('Category')}</Label>
                    <Select value={data.categorie_id?.toString() || ''} onValueChange={(value) => setData('categorie_id', value)}>
                        <SelectTrigger>
                            <SelectValue placeholder={t('Select Category')} />
                        </SelectTrigger>
                        <SelectContent>
                            {pettycashcategories?.map((item: any) => (
                                <SelectItem key={item.id} value={item.id.toString()}>
                                    {item.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.categorie_id} />
                </div>

                <div>
                    <CurrencyInput
                        label={t('Amount')}
                        value={data.requested_amount}
                        onChange={(value) => setData('requested_amount', value)}
                        error={errors.requested_amount}
                        required
                    />
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

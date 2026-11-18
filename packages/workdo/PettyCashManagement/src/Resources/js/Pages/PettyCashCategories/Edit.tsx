import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { EditPettyCashCategorieProps, EditPettyCashCategorieFormData, ExpenseAccountOption } from './types';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import axios from 'axios';

export default function EditPettyCashCategorie({ pettycashcategorie, onSuccess }: EditPettyCashCategorieProps) {
    const { expenseAccounts = [] } = usePage<any>().props as { expenseAccounts?: ExpenseAccountOption[] };

    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<EditPettyCashCategorieFormData>({
        name: pettycashcategorie.name ?? '',
        account_id: pettycashcategorie.account_id ? pettycashcategorie.account_id.toString() : '',
    });



    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('petty-cash-management.petty-cash-categories.update', pettycashcategorie.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Edit Category')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t('Enter Name')}
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div>
                    <Label htmlFor="account_id" required={expenseAccounts.length > 0}>{t('Expense Account')}</Label>
                    <Select value={data.account_id} onValueChange={(value) => setData('account_id', value)}>
                        <SelectTrigger id="account_id">
                            <SelectValue placeholder={t('Select Expense Account')} />
                        </SelectTrigger>
                        <SelectContent>
                            {expenseAccounts.map((account) => (
                                <SelectItem key={account.id} value={account.id.toString()}>
                                    {account.account_code} - {account.account_name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <p className="mt-1 text-xs text-muted-foreground">{t('Approved spending in this category is posted to this account.')}</p>
                    <InputError message={errors.account_id} />
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

import { FormEvent } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/utils/helpers';
import { Plus, Trash2 } from 'lucide-react';
import { AccountOption, JournalEntryItem } from './types';

interface CreateProps {
    accounts: AccountOption[];
}

const emptyLine = (): JournalEntryItem => ({
    account_id: '',
    description: '',
    debit_amount: '',
    credit_amount: '',
});

const amount = (value: number | string) => Number(value || 0);

export default function Create() {
    const { t } = useTranslation();
    const { accounts } = usePage<CreateProps>().props;

    const { data, setData, post, processing, errors } = useForm({
        journal_date: new Date().toISOString().split('T')[0],
        reference_type: '',
        description: '',
        items: [emptyLine(), emptyLine()] as JournalEntryItem[],
    });

    const updateLine = (index: number, field: keyof JournalEntryItem, value: string) => {
        const nextItems = [...data.items];
        nextItems[index] = { ...nextItems[index], [field]: value };

        if (field === 'debit_amount' && amount(value) > 0) {
            nextItems[index].credit_amount = '';
        }

        if (field === 'credit_amount' && amount(value) > 0) {
            nextItems[index].debit_amount = '';
        }

        setData('items', nextItems);
    };

    const removeLine = (index: number) => {
        if (data.items.length <= 2) {
            return;
        }

        setData('items', data.items.filter((_, itemIndex) => itemIndex !== index));
    };

    const totalDebit = data.items.reduce((total, item) => total + amount(item.debit_amount), 0);
    const totalCredit = data.items.reduce((total, item) => total + amount(item.credit_amount), 0);
    const difference = Math.abs(totalDebit - totalCredit);
    const isBalanced = totalDebit > 0 && totalCredit > 0 && difference < 0.01;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(route('account.journal-entries.store'));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Accounting'), url: route('account.index') },
                { label: t('Journal Entries'), url: route('account.journal-entries.index') },
                { label: t('Create Journal Entry') },
            ]}
            pageTitle={t('Create Journal Entry')}
        >
            <Head title={t('Create Journal Entry')} />

            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Journal Details')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <Label htmlFor="journal_date" required>{t('Journal Date')}</Label>
                                <Input
                                    id="journal_date"
                                    type="date"
                                    value={data.journal_date}
                                    onChange={(event) => setData('journal_date', event.target.value)}
                                />
                                <InputError message={errors.journal_date} />
                            </div>
                            <div>
                                <Label htmlFor="reference_type">{t('Reference')}</Label>
                                <Input
                                    id="reference_type"
                                    value={data.reference_type}
                                    onChange={(event) => setData('reference_type', event.target.value)}
                                    placeholder={t('e.g., ADJ-001')}
                                />
                                <InputError message={errors.reference_type} />
                            </div>
                            <div className="md:col-span-3">
                                <Label htmlFor="description" required>{t('Description')}</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(event) => setData('description', event.target.value)}
                                    placeholder={t('Reason for this journal entry')}
                                />
                                <InputError message={errors.description} />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>{t('Debit and Credit Lines')}</CardTitle>
                            <Button type="button" size="sm" onClick={() => setData('items', [...data.items, emptyLine()])}>
                                <Plus className="mr-2 h-4 w-4" />
                                {t('Add Row')}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <InputError message={(errors as any).items} />
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px]">
                                <thead>
                                    <tr className="border-b">
                                        <th className="px-3 py-2 text-left">{t('Account')}</th>
                                        <th className="px-3 py-2 text-left">{t('Line Description')}</th>
                                        <th className="px-3 py-2 text-right">{t('Debit')}</th>
                                        <th className="px-3 py-2 text-right">{t('Credit')}</th>
                                        <th className="px-3 py-2 text-right">{t('Action')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.items.map((item, index) => (
                                        <tr key={index} className="border-b">
                                            <td className="px-3 py-3 align-top">
                                                <Select value={String(item.account_id)} onValueChange={(value) => updateLine(index, 'account_id', value)}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder={t('Select Account')} />
                                                    </SelectTrigger>
                                                    <SelectContent searchable>
                                                        {accounts.map((account) => (
                                                            <SelectItem key={account.id} value={String(account.id)}>
                                                                {account.account_code} - {account.account_name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={(errors as any)[`items.${index}.account_id`]} />
                                            </td>
                                            <td className="px-3 py-3 align-top">
                                                <Input
                                                    value={item.description || ''}
                                                    onChange={(event) => updateLine(index, 'description', event.target.value)}
                                                    placeholder={t('Optional line note')}
                                                />
                                            </td>
                                            <td className="px-3 py-3 align-top">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={item.debit_amount}
                                                    onChange={(event) => updateLine(index, 'debit_amount', event.target.value)}
                                                    className="text-right"
                                                />
                                                <InputError message={(errors as any)[`items.${index}.debit_amount`]} />
                                            </td>
                                            <td className="px-3 py-3 align-top">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={item.credit_amount}
                                                    onChange={(event) => updateLine(index, 'credit_amount', event.target.value)}
                                                    className="text-right"
                                                />
                                                <InputError message={(errors as any)[`items.${index}.credit_amount`]} />
                                            </td>
                                            <td className="px-3 py-3 text-right align-top">
                                                <Button type="button" variant="ghost" size="sm" className="text-red-600" onClick={() => removeLine(index)} disabled={data.items.length <= 2}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <div className="w-80 rounded-lg bg-muted/30 p-4">
                                <div className="flex justify-between text-sm">
                                    <span>{t('Total Debit')}</span>
                                    <span className="font-medium">{formatCurrency(totalDebit)}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span>{t('Total Credit')}</span>
                                    <span className="font-medium">{formatCurrency(totalCredit)}</span>
                                </div>
                                <div className={`mt-2 flex justify-between border-t pt-2 font-semibold ${isBalanced ? 'text-green-600' : 'text-red-600'}`}>
                                    <span>{t('Difference')}</span>
                                    <span>{formatCurrency(difference)}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-3">
                    <Button type="button" variant="outline" onClick={() => router.visit(route('account.journal-entries.index'))}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !isBalanced}>
                        {processing ? t('Saving...') : t('Save Journal Entry')}
                    </Button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

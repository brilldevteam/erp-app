import { FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Trash2 } from 'lucide-react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/ui/input-error';
import SystemSetupSidebar from '../SystemSetupSidebar';

type Currency = { code: string; name: string; symbol: string };
type Rate = { id: number; currency_code: string; exchange_rate: string; effective_date: string; is_active: boolean };

export default function Index({ rates, currencies, baseCurrency }: { rates: Rate[]; currencies: Currency[]; baseCurrency: string }) {
    const { t } = useTranslation();
    const form = useForm({
        currency_code: '',
        exchange_rate: '',
        effective_date: new Date().toISOString().slice(0, 10),
        is_active: true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('account.currency-rates.store'), { preserveScroll: true, onSuccess: () => form.reset('currency_code', 'exchange_rate') });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Accounting'), url: route('account.index') }, { label: t('System Setup') }, { label: t('Currencies & Exchange Rates') }]}
            pageTitle={t('System Setup')}
        >
            <Head title={t('Currencies & Exchange Rates')} />
            <div className="flex flex-col gap-8 md:flex-row">
                <div className="flex-shrink-0 md:w-64"><SystemSetupSidebar activeItem="currency-rates" /></div>
                <div className="min-w-0 flex-1 space-y-6">
                    <Card className="shadow-sm">
                        <CardContent className="p-6">
                            <h3 className="text-lg font-medium">{t('Currencies & Exchange Rates')}</h3>
                            <p className="mt-1 text-sm text-muted-foreground">{t('Base currency')}: <strong>{baseCurrency}</strong></p>
                            <form onSubmit={submit} className="mt-6 grid gap-4 md:grid-cols-4 md:items-end">
                                <div>
                                    <Label required>{t('Currency')}</Label>
                                    <Select value={form.data.currency_code} onValueChange={(value) => form.setData('currency_code', value)}>
                                        <SelectTrigger><SelectValue placeholder={t('Select Currency')} /></SelectTrigger>
                                        <SelectContent>{currencies.filter((currency) => currency.code !== baseCurrency).map((currency) => <SelectItem key={currency.code} value={currency.code}>{currency.name} ({currency.code})</SelectItem>)}</SelectContent>
                                    </Select>
                                    <InputError message={form.errors.currency_code} />
                                </div>
                                <div>
                                    <Label required>{t('Exchange Rate')}</Label>
                                    <Input type="number" min="0.00000001" step="0.00000001" value={form.data.exchange_rate} onChange={(event) => form.setData('exchange_rate', event.target.value)} />
                                    <InputError message={form.errors.exchange_rate} />
                                </div>
                                <div>
                                    <Label required>{t('Effective Date')}</Label>
                                    <Input type="date" value={form.data.effective_date} onChange={(event) => form.setData('effective_date', event.target.value)} />
                                    <InputError message={form.errors.effective_date} />
                                </div>
                                <Button disabled={form.processing}>{t('Save Rate')}</Button>
                            </form>
                        </CardContent>
                    </Card>
                    <Card className="shadow-sm">
                        <CardContent className="overflow-x-auto p-0">
                            <table className="w-full text-sm">
                                <thead><tr className="border-b bg-muted/40"><th className="p-4 text-left">{t('Currency')}</th><th className="p-4 text-right">{t('Exchange Rate')}</th><th className="p-4 text-left">{t('Effective Date')}</th><th className="w-16 p-4" /></tr></thead>
                                <tbody>{rates.map((rate) => <tr key={rate.id} className="border-b"><td className="p-4 font-medium">{rate.currency_code}</td><td className="p-4 text-right tabular-nums">{Number(rate.exchange_rate).toFixed(8)}</td><td className="p-4">{String(rate.effective_date).slice(0, 10)}</td><td className="p-4"><Button variant="ghost" size="icon" title={t('Delete')} onClick={() => router.delete(route('account.currency-rates.destroy', rate.id), { preserveScroll: true })}><Trash2 className="h-4 w-4" /></Button></td></tr>)}</tbody>
                            </table>
                            {!rates.length && <p className="p-8 text-center text-muted-foreground">{t('No exchange rates configured')}</p>}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

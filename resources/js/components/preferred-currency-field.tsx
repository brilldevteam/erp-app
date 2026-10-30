import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/ui/input-error';

export default function PreferredCurrencyField({ value, onChange, error }: { value?: string; onChange: (value: string) => void; error?: string }) {
    const { t } = useTranslation();
    const page = usePage().props as any;
    const currencies = page.currencies || [];
    const baseCurrency = page.companyAllSetting?.defaultCurrency || 'USD';

    return <div>
        <Label>{t('Preferred Currency')}</Label>
        <Select value={value || baseCurrency} onValueChange={onChange}>
            <SelectTrigger><SelectValue /></SelectTrigger>
            <SelectContent searchable>{currencies.map((currency: any) => <SelectItem key={currency.code} value={currency.code}>{currency.name} ({currency.code})</SelectItem>)}</SelectContent>
        </Select>
        <InputError message={error} />
    </div>;
}

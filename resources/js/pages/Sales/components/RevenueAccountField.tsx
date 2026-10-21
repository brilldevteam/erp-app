import { useTranslation } from 'react-i18next';
import { Label } from '@/components/ui/label';
import { InputError } from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { RevenueAccountOption } from '../types';

// Accounts the journal posts to when no category is chosen (see JournalService).
const DEFAULT_REVENUE_CODES = { product: '4100', service: '4200' } as const;

export function defaultRevenueAccountId(accounts: RevenueAccountOption[], type: 'product' | 'service'): string {
    return accounts.find((account) => account.account_code === DEFAULT_REVENUE_CODES[type])?.id.toString() ?? '';
}

interface RevenueAccountFieldProps {
    accounts: RevenueAccountOption[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
}

export default function RevenueAccountField({ accounts, value, onChange, error }: RevenueAccountFieldProps) {
    const { t } = useTranslation();

    if (!accounts.length) return null;

    return (
        <div>
            <Label htmlFor="revenue_account_id">{t('Revenue Category')}</Label>
            <Select value={value || undefined} onValueChange={onChange}>
                <SelectTrigger id="revenue_account_id">
                    <SelectValue placeholder={t('Select Revenue Category')} />
                </SelectTrigger>
                <SelectContent>
                    {accounts.map((account) => (
                        <SelectItem key={account.id} value={account.id.toString()}>
                            {account.account_name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}

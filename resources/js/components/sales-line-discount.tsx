import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getCompanySetting } from '@/utils/helpers';

export default function SalesLineDiscount({ item, onChange }: {
    item: { discount_type?: 'percentage' | 'fixed'; discount_value?: number; discount_percentage: number; quantity: number; unit_price: number };
    onChange: (field: 'discount_type' | 'discount_value' | 'discount_percentage', value: string | number) => void;
}) {
    const { t } = useTranslation();
    const fixed = item.discount_type === 'fixed';
    const currency = getCompanySetting('defaultCurrency') || 'QAR';
    return (
        <div className="flex gap-2 min-w-[180px]">
            <Input type="number" aria-label={t('Discount value')} min="0" step="0.01"
                max={fixed ? Math.round(item.quantity * item.unit_price * 100) / 100 : 100}
                className="w-24" value={fixed ? item.discount_value ?? 0 : item.discount_percentage}
                onChange={e => onChange(fixed ? 'discount_value' : 'discount_percentage', Number(e.target.value) || 0)} />
            <Select value={item.discount_type || 'percentage'} onValueChange={value => onChange('discount_type', value)}>
                <SelectTrigger className="w-24" aria-label={t('Discount type')}><SelectValue /></SelectTrigger>
                <SelectContent><SelectItem value="percentage">%</SelectItem><SelectItem value="fixed">{currency}</SelectItem></SelectContent>
            </Select>
        </div>
    );
}

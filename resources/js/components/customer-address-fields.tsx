import { useTranslation } from 'react-i18next';
import { CountryAddressFields } from '@/components/country-address-fields';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Address, addressCountryCode } from '@/types/address';

interface CustomerAddressFieldsProps {
    kind: 'billing' | 'shipping';
    address: Address;
    onChange: (address: Address) => void;
    errors: Record<string, string | undefined>;
}

export function CustomerAddressFields({ kind, address, onChange, errors }: CustomerAddressFieldsProps) {
    const { t } = useTranslation();
    const prefix = `${kind}_address`;
    const code = addressCountryCode(address);
    const update = (field: keyof Address, value: string) => onChange({ ...address, [field]: value });
    const customerField = (
        key: keyof Address,
        label: string,
        placeholder: string,
        options: { required?: boolean; inputMode?: 'numeric' | 'text'; pattern?: string; maxLength?: number } = {},
    ) => (
        <div>
            <Label htmlFor={`${prefix}_${key}`} required={options.required}>{label}</Label>
            <Input
                id={`${prefix}_${key}`}
                value={address[key] || ''}
                onChange={(event) => update(key, event.target.value)}
                placeholder={placeholder}
                required={options.required}
                inputMode={options.inputMode}
                pattern={options.pattern}
                maxLength={options.maxLength}
            />
            <InputError message={errors[`${prefix}.${key}`]} />
        </div>
    );

    return (
        <div className="space-y-4">
            {customerField('name', kind === 'billing' ? t('Billing Name') : t('Shipping Name'), kind === 'billing' ? t('Enter billing name') : t('Enter shipping name'), { required: true })}
            <CountryAddressFields
                prefix={prefix}
                address={address}
                onChange={(countryAddress) => onChange({ ...address, ...countryAddress })}
                errors={errors}
                addressLineOneLabel={kind === 'billing' ? t('Billing Address') : t('Shipping Address')}
                addressLineOnePlaceholder={kind === 'billing' ? t('Enter address') : t('Enter shipping address')}
                afterCountry={code === 'QA'
                    ? customerField('qid_number', t('QID No.'), t('Enter 11-digit QID number'), { required: true, inputMode: 'numeric', pattern: '[0-9]{11}', maxLength: 11 })
                    : code === 'SA'
                        ? customerField('saudi_identity_number', t('National ID / Iqama No.'), t('Enter 10-digit National ID or Iqama number'), { required: true, inputMode: 'numeric', pattern: '[12][0-9]{9}', maxLength: 10 })
                        : undefined}
            />
        </div>
    );
}

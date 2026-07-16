import { useTranslation } from 'react-i18next';
import { CountrySelect } from '@/components/country-select';
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
    const field = (
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
            {field('name', kind === 'billing' ? t('Billing Name') : t('Shipping Name'), kind === 'billing' ? t('Enter billing name') : t('Enter shipping name'), { required: true })}
            <div>
                <Label htmlFor={`${prefix}_country`} required>{t('Country')}</Label>
                <CountrySelect
                    id={`${prefix}_country`}
                    value={code}
                    countryName={address.country}
                    invalid={Boolean(errors[`${prefix}.country`] || errors[`${prefix}.country_code`])}
                    onChange={({ code: countryCode, name }) => onChange({ ...address, country_code: countryCode, country: name })}
                />
                <InputError message={errors[`${prefix}.country`] || errors[`${prefix}.country_code`]} />
            </div>

            {code === 'QA' ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    {field('zone_number', t('Zone Number'), t('Enter zone number'), { required: true, inputMode: 'numeric', pattern: '[0-9]+' })}
                    {field('street_number', t('Street Number'), t('Enter street number'), { required: true, inputMode: 'numeric', pattern: '[0-9]+' })}
                    {field('building_number', t('Building Number'), t('Enter building number'), { required: true, inputMode: 'numeric', pattern: '[0-9]+' })}
                </div>
            ) : code === 'SA' ? (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {field('building_number', t('Building Number'), t('Enter 4-digit building number'), { required: true, inputMode: 'numeric', pattern: '[0-9]{4}', maxLength: 4 })}
                    {field('street_name', t('Street Name'), t('Enter street name'), { required: true })}
                    {field('district', t('District'), t('Enter district'), { required: true })}
                    {field('city', t('City'), t('Enter city'), { required: true })}
                    {field('zip_code', t('Postal Code'), t('Enter 5-digit postal code'), { required: true, inputMode: 'numeric', pattern: '[0-9]{5}', maxLength: 5 })}
                    {field('secondary_number', t('Secondary Number'), t('Enter 4-digit secondary number'), { required: true, inputMode: 'numeric', pattern: '[0-9]{4}', maxLength: 4 })}
                </div>
            ) : (
                <>
                    {field('address_line_1', kind === 'billing' ? t('Billing Address') : t('Shipping Address'), kind === 'billing' ? t('Enter address') : t('Enter shipping address'), { required: true })}
                    {field('address_line_2', t('Address Line 2'), t('Apartment, suite, etc. (optional)'))}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {field('city', t('City'), t('Enter city'), { required: true })}
                        {field('state', t('State / Province'), t('Enter state or province'), { required: true })}
                        {field('zip_code', t('ZIP / Postal Code'), t('Enter ZIP or postal code'), { required: true })}
                    </div>
                </>
            )}
        </div>
    );
}

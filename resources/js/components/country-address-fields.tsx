import { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { CountrySelect } from '@/components/country-select';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { CountryAddress, addressCountryCode } from '@/types/address';

interface CountryAddressFieldsProps {
    prefix: string;
    address: CountryAddress;
    onChange: (address: CountryAddress) => void;
    errors: Record<string, string | undefined>;
    addressLineOneLabel?: string;
    addressLineOnePlaceholder?: string;
    afterCountry?: ReactNode;
}

export function CountryAddressFields({
    prefix,
    address,
    onChange,
    errors,
    addressLineOneLabel,
    addressLineOnePlaceholder,
    afterCountry,
}: CountryAddressFieldsProps) {
    const { t } = useTranslation();
    const code = addressCountryCode(address);
    const idPrefix = prefix.replace(/[^a-zA-Z0-9_-]/g, '_');
    const update = (field: keyof CountryAddress, value: string) => onChange({ ...address, [field]: value });
    const field = (
        key: keyof CountryAddress,
        label: string,
        placeholder: string,
        options: { required?: boolean; inputMode?: 'numeric' | 'text'; pattern?: string; maxLength?: number } = {},
    ) => (
        <div>
            <Label htmlFor={`${idPrefix}_${key}`} required={options.required}>{label}</Label>
            <Input
                id={`${idPrefix}_${key}`}
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
            <div>
                <Label htmlFor={`${idPrefix}_country`} required>{t('Country')}</Label>
                <CountrySelect
                    id={`${idPrefix}_country`}
                    value={code}
                    countryName={address.country}
                    invalid={Boolean(errors[`${prefix}.country`] || errors[`${prefix}.country_code`])}
                    onChange={({ code: countryCode, name }) => onChange({ ...address, country_code: countryCode, country: name })}
                />
                <InputError message={errors[`${prefix}.country`] || errors[`${prefix}.country_code`]} />
            </div>

            {afterCountry}

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
            ) : (code || address.country) ? (
                <>
                    {field('address_line_1', addressLineOneLabel || t('Address Line 1'), addressLineOnePlaceholder || t('Enter address'), { required: true })}
                    {field('address_line_2', t('Address Line 2'), t('Apartment, suite, etc. (optional)'))}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {field('city', t('City'), t('Enter city'), { required: true })}
                        {field('state', t('State / Province'), t('Enter state or province'), { required: true })}
                        {field('zip_code', t('ZIP / Postal Code'), t('Enter ZIP or postal code'), { required: true })}
                    </div>
                </>
            ) : null}
        </div>
    );
}

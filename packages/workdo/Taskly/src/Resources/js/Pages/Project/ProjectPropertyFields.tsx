import { useTranslation } from 'react-i18next';
import { CountryAddressFields } from '@/components/country-address-fields';
import { LocationQrCode } from '@/components/location-qr-code';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { ProjectPropertyInformation } from './types';

interface ProjectPropertyFieldsProps {
    value: ProjectPropertyInformation;
    onChange: (value: ProjectPropertyInformation) => void;
    errors: Record<string, string | undefined>;
}

export function ProjectPropertyFields({ value, onChange, errors }: ProjectPropertyFieldsProps) {
    const { t } = useTranslation();
    const update = (field: 'plot_number' | 'property_number' | 'location_url', nextValue: string) => {
        onChange({ ...value, [field]: nextValue });
    };

    return (
        <section className="space-y-4 rounded-lg border p-4">
            <h3 className="font-semibold">{t('Project Property Information')}</h3>

            <CountryAddressFields
                prefix="property_information"
                address={value}
                onChange={(address) => onChange({ ...value, ...address })}
                errors={errors}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <Label htmlFor="property_information_plot_number" required>{t('Plot Number')}</Label>
                    <Input
                        id="property_information_plot_number"
                        value={value.plot_number}
                        onChange={(event) => update('plot_number', event.target.value)}
                        placeholder={t('Enter plot number')}
                        maxLength={50}
                        required
                    />
                    <InputError message={errors['property_information.plot_number']} />
                </div>
                <div>
                    <Label htmlFor="property_information_property_number" required>{t('Property Number')}</Label>
                    <Input
                        id="property_information_property_number"
                        value={value.property_number}
                        onChange={(event) => update('property_number', event.target.value)}
                        placeholder={t('Enter property number')}
                        maxLength={50}
                        required
                    />
                    <InputError message={errors['property_information.property_number']} />
                </div>
            </div>

            <div className="grid grid-cols-1 items-end gap-4 sm:grid-cols-[minmax(0,1fr)_9rem]">
                <div>
                    <Label htmlFor="property_information_location_url" required>{t('Location Map Link')}</Label>
                    <Input
                        id="property_information_location_url"
                        type="url"
                        value={value.location_url}
                        onChange={(event) => update('location_url', event.target.value)}
                        placeholder={t('Paste an HTTP or HTTPS map link')}
                        maxLength={2048}
                        required
                    />
                    <InputError message={errors['property_information.location_url']} />
                </div>
                <LocationQrCode url={value.location_url} />
            </div>
        </section>
    );
}

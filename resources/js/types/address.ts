export interface CountryAddress {
    country: string;
    country_code?: string;
    address_line_1?: string;
    address_line_2?: string;
    city?: string;
    state?: string;
    zip_code?: string;
    zone_number?: string;
    street_number?: string;
    building_number?: string;
    street_name?: string;
    district?: string;
    secondary_number?: string;
}

export interface Address extends CountryAddress {
    name: string;
    qid_number?: string;
    saudi_identity_number?: string;
}

export type AddressTranslation = (key: string) => string;

export const addressCountryCode = (address?: Partial<CountryAddress> | null): string => {
    const explicitCode = address?.country_code?.trim().toUpperCase();
    if (explicitCode) return explicitCode;

    const country = address?.country?.trim().toLowerCase();
    if (country === 'qatar') return 'QA';
    if (country === 'saudi arabia' || country === 'kingdom of saudi arabia') return 'SA';

    return '';
};

export const formatAddressLines = (
    address?: Partial<Address> | null,
    translate: AddressTranslation = (key) => key,
): string[] => {
    if (!address) return [];

    const lines = [address.name];
    const code = addressCountryCode(address);

    if (code === 'QA' && address.qid_number) lines.push(`${translate('QID No.')}: ${address.qid_number}`);
    if (code === 'SA' && address.saudi_identity_number) lines.push(`${translate('National ID / Iqama No.')}: ${address.saudi_identity_number}`);

    lines.push(...formatCountryAddressLines(address, translate));
    return lines.filter((line): line is string => Boolean(line?.trim()));
};

export const formatCountryAddressLines = (
    address?: Partial<CountryAddress> | null,
    translate: AddressTranslation = (key) => key,
): string[] => {
    if (!address) return [];

    const code = addressCountryCode(address);
    const lines: Array<string | undefined> = [];

    if (code === 'QA' && (address.zone_number || address.street_number || address.building_number)) {
        lines.push(
            address.zone_number ? `${translate('Zone Number')}: ${address.zone_number}` : undefined,
            address.street_number ? `${translate('Street Number')}: ${address.street_number}` : undefined,
            address.building_number ? `${translate('Building Number')}: ${address.building_number}` : undefined,
        );
    } else if (code === 'SA' && (address.building_number || address.street_name || address.district || address.secondary_number)) {
        lines.push(
            [address.building_number, address.street_name].filter(Boolean).join(' '),
            address.district,
            address.city,
            address.zip_code ? `${translate('Postal Code')}: ${address.zip_code}` : undefined,
            address.secondary_number ? `${translate('Secondary Number')}: ${address.secondary_number}` : undefined,
        );
    } else {
        lines.push(
            address.address_line_1,
            address.address_line_2,
            [address.city, address.state].filter(Boolean).join(', '),
            address.zip_code,
        );
    }

    lines.push(address.country);
    return lines.filter((line): line is string => Boolean(line?.trim()));
};

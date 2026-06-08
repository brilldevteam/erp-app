import React from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { useTranslation } from 'react-i18next';

export const customerCreateFields = (data: any, setData: any, errors: any, mode: string = 'create') => {
    const { t } = useTranslation();
    return [
        {
            id: 'electronic-address',
            order: 100,
            component: (
                <div key="electronic-address">
                    <Label htmlFor="electronic_address">
                        {t('Electronic Address')}
                    </Label>
                    <Input
                        id="electronic_address"
                        value={data.electronic_address || ''}
                        onChange={(e) => setData('electronic_address', e.target.value)}
                        placeholder={t('Enter Electronic Address')}
                    />
                    <InputError message={errors.electronic_address} />
                </div>
            )
        },
        {
            id: 'electronic-address-scheme',
            order: 101,
            component: (
                <div key="electronic-address-scheme">
                    <Label htmlFor="electronic_address_scheme">
                        {t('Electronic Address Scheme')}
                    </Label>
                    <Input
                        id="electronic_address_scheme"
                        value={data.electronic_address_scheme || ''}
                        onChange={(e) => setData('electronic_address_scheme', e.target.value)}
                        placeholder={t('Enter Electronic Address Scheme')}
                    />
                    <InputError message={errors.electronic_address_scheme} />
                </div>
            )
        }
    ];
};

export const customerEditFields = (data: any, setData: any, errors: any, mode: string) => {
    return customerCreateFields(data, setData, errors, mode);
};
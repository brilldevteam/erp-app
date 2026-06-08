import { Save } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const retainerCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Retainer'),
        icon: Save,
        permission: 'manage-retainer',
        href:'',
        order: 170,

        children: [
            {
                title: t('Retainers'),
                href: route('retainers.index'),
                permission: 'manage-retainer',
            },
            {
                title: t('Retainer Payments'),
                href: route('retainer-payments.index'),
                permission: 'manage-retainer-payments',
            },
        ]}

];
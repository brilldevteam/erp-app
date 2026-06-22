import { FileCheck } from 'lucide-react';

declare global {
    function route(name: string, params?: Record<string, unknown>): string;
}

export const quotationCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Quotation'),
        icon: FileCheck,
        permission: 'manage-quotations',
        order: 260,
        children: [
            {
                title: t('Quotation Template'),
                href: route('documents.settings.type', { type: 'quotation' }),
                permission: 'manage-document-templates',
            },
            {
                title: t('Manage Quotation'),
                href: route('quotations.index'),
                permission: 'manage-quotations',
            },
        ],
    },
];

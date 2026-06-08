import {  Package , Tag , FileText , DollarSign , Receipt } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const pettycashmanagementCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Petty Cash'),
        icon: DollarSign,
        permission: 'manage-petty-cash-management',
        order: 1365,
        children: [
            {
                title: t('Petty Cash'),
                href: route('petty-cash-management.petty-cashes.index'),
                permission: 'manage-petty-cashes',
            },
            {
                title: t('Petty Cash Requests'),
                href: route('petty-cash-management.petty-cash-requests.index'),
                permission: 'manage-petty-cash-requests',
            },
            {
                title: t('Reimbursements'),
                href: route('petty-cash-management.reimbursements.index'),
                permission: 'manage-reimbursements',
            },
            {
                title: t('Expenses'),
                href: route('petty-cash-management.petty-cash-expenses.index'),
                permission: 'manage-petty-cash-expenses',
            },
            {
                title: t('Categories'),
                href: route('petty-cash-management.petty-cash-categories.index'),
                permission: 'manage-petty-cash-categories',
            },
        ],
    },
];

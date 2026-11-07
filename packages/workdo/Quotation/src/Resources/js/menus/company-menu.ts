declare global {
    function route(name: string): string;
}

export const quotationCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Quotations'),
        permission: 'manage-quotations',
        href: route('quotations.index'),
        parent: 'sales',
        order: 20,
    },
    {
        title: t('Salesperson'),
        permission: 'manage-quotations',
        href: route('quotation-salespersons.index'),
        parent: 'sales',
        order: 25,
    },
];

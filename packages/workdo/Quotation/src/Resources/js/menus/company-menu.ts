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
];
declare global {
    function route(name: string): string;
}

declare global {
    function route(name: string): string;
}

export const contracttemplateCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Contract Templates'),
        href: route('contract-templates.index'),
        permission: 'manage-contract-templates',
        parent: 'contract',
        order: 20,
    },
];
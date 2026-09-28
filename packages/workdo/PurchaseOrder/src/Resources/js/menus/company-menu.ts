declare global {
    function route(name: string): string;
}

export const purchaseOrderCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Purchase Orders'),
        href: route('purchase-orders.index'),
        permission: 'manage-purchase-orders',
        parent: 'purchase',
        order: 10,
    },
];

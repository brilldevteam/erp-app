declare global {
    function route(name: string): string;
}

export const purchaseOrderCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Purchase Orders / LPO'),
        href: route('purchase-orders.index'),
        permission: 'manage-purchase-orders',
        parent: 'purchase',
        order: 10,
    },
];

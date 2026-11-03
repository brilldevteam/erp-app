import { BriefcaseBusiness, Layers, Package } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const productserviceCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Product & Service'),
        icon: Layers,
        anyPermission: ['manage-products', 'manage-services', 'manage-product-service-item'],
        parent: 'settings',
        order: 37,
        children: [
            {
                title: t('Products'),
                icon: Package,
                href: route('product-service.products.index'),
                permission: 'manage-products',
            },
            {
                title: t('Services'),
                icon: BriefcaseBusiness,
                href: route('product-service.services.index'),
                permission: 'manage-services',
            },
            {
                title: t('System Setup'),
                href: route('product-service.item-categories.index'),
                permission: 'manage-product-service-item',
            },
        ],
    },
];

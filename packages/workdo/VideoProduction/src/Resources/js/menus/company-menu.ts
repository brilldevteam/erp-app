import { FileCheck2, Gauge, Video } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const videoproductionCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Overview'),
        href: route('video-production.client.overview'),
        icon: Gauge,
        permission: 'view-video-production-dashboard',
        roles: ['production-client'],
        order: 100,
    },
    {
        title: t('Shooting Log'),
        href: route('video-production.client.shooting-log'),
        icon: Video,
        permission: 'view-video-production',
        roles: ['production-client'],
        order: 110,
    },
    {
        title: t('Deliverables'),
        href: route('video-production.client.deliverables'),
        icon: FileCheck2,
        permission: 'view-video-production',
        roles: ['production-client'],
        order: 120,
    },
];

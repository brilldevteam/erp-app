import { Video } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const videoProductionCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Video Production'),
        icon: Video,
        href: route('video-production.index'),
        permission: 'view-video-production',
        order: 315,
    },
];

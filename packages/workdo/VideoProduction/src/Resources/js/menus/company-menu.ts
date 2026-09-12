declare global {
    function route(name: string): string;
}

export const videoproductionCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Production'),
        href: route('video-production.dashboard'),
        permission: 'manage-video-production',
        parent: 'dashboard',
        order: 25,
    },
    { title: t('Production'), href: route('video-production.dashboard'), permission: 'manage-video-production', parent: 'project', order: 12 },
];

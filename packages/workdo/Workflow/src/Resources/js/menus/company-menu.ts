import { Workflow } from 'lucide-react';

declare global {
    function route(name: string): string;
}

export const workflowCompanyMenu = (t: (key: string) => string) => [
    {
        title: t('Workflow'),
        icon: Workflow,
        href: route('workflow.index'),
        permission: 'manage-workflow',
        order: 1050,
    },
];

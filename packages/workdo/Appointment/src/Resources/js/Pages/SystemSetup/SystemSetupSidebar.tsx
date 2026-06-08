import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { ScrollArea } from "@/components/ui/scroll-area";
import { cn } from '@/lib/utils';
import { Palette, HelpCircle, Shield, FileText, Clock } from "lucide-react";

interface SidebarItem {
    key: string;
    label: string;
    icon: React.ComponentType<{ className?: string }>;
    route: string;
    permission: string;
}

interface SystemSetupSidebarProps {
    activeItem?: string;
    onSectionChange?: (section: string) => void;
}

export default function SystemSetupSidebar({ activeItem, onSectionChange }: SystemSetupSidebarProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const currentRoute = route().current();

    const sidebarItems: SidebarItem[] = [
        {
            key: 'appointment-hours',
            label: t('Appointment Hours'),
            icon: Clock,
            route: 'appointment.settings.hours',
            permission: 'manage-appointment-settings'
        },
        {
            key: 'brand-settings',
            label: t('Brand Settings'),
            icon: Palette,
            route: 'appointment.settings.index',
            permission: 'manage-appointment-settings'
        },
        {
            key: 'faq-settings',
            label: t('FAQ Settings'),
            icon: HelpCircle,
            route: 'appointment.settings.faq',
            permission: 'manage-appointment-settings'
        },
        {
            key: 'privacy-policy',
            label: t('Privacy Policy'),
            icon: Shield,
            route: 'appointment.settings.privacy',
            permission: 'manage-appointment-settings'
        },
        {
            key: 'terms-conditions',
            label: t('Terms & Conditions'),
            icon: FileText,
            route: 'appointment.settings.terms',
            permission: 'manage-appointment-settings'
        }
    ];

    const filteredItems = sidebarItems.filter(item =>
        auth.user?.permissions?.includes(item.permission)
    );

    return (
        <div className="sticky top-4">
            <ScrollArea className="h-[calc(100vh-8rem)]">
                <div className="pr-4 space-y-1">
                    {filteredItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = activeItem === item.key || currentRoute === item.route;

                        return (
                            <Button
                                key={item.key}
                                variant="ghost"
                                className={cn('w-full justify-start', {
                                    'bg-muted font-medium': isActive,
                                })}
                                onClick={() => {
                                    router.get(route(item.route));
                                    onSectionChange?.(item.key);
                                }}
                            >
                                <Icon className="h-4 w-4 mr-2" />
                                {item.label}
                            </Button>
                        );
                    })}
                </div>
            </ScrollArea>
        </div>
    );
}
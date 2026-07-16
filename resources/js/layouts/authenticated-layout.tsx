import { PropsWithChildren, ReactNode, Fragment } from "react";
import {AppSidebar} from "@/components/app-sidebar";
import {SidebarInset, SidebarProvider, SidebarTrigger} from "@/components/ui/sidebar";
import {Separator} from "@/components/ui/separator";
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbLink,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import { NavUser } from "@/components/nav-user";
import { usePage, Head, Link, router } from "@inertiajs/react";
import { PageProps } from "@/types";
import { BrandProvider, useBrand } from "@/contexts/brand-context";
import CookieConsent from "@/components/cookie-consent";
import { useFavicon } from "@/hooks/use-favicon";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { UserX, Bot } from "lucide-react";
import { useFormFields } from '@/hooks/useFormFields';
import { getImagePath } from '@/utils/helpers';

function AuthenticatedLayoutContent({
    header,
    children,
    breadcrumbs,
    pageTitle,
    pageActions
}: PropsWithChildren<{
    header?: ReactNode;
    breadcrumbs?: Array<{label: string, url?: string}>;
    pageTitle?: string;
    pageActions?: ReactNode;
    className?: string;
}>) {
    const { t } = useTranslation();
    const { auth, companyAllSetting, adminAllSetting } = usePage<PageProps>().props as any;
    const { settings } = useBrand();
    useFavicon();

    const generalAlerts = useFormFields('generalAlert', {}, () => {}, {});

    // Check if current page is AI Agent chat page
    const isAIAgentPage = window.location.pathname.includes('/ai-agent/chat');


    return (
        <>
        <Head title={adminAllSetting?.metaTitle}>
            {adminAllSetting?.metaKeywords && (
                <meta name="keywords" content={adminAllSetting.metaKeywords} />
            )}
            {adminAllSetting?.metaDescription && (
                <meta name="description" content={adminAllSetting.metaDescription} />
            )}
            {adminAllSetting?.metaImage && (
                <meta property="og:image" content={getImagePath(adminAllSetting.metaImage)} />
            )}
        </Head>
        <div
            className={`${settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr'} max-w-full overflow-x-hidden`}
            data-theme={settings.themeMode}
            dir={settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr'}
            style={{ direction: settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr' }}
        >
        <SidebarProvider defaultOpen={true}>
            <AppSidebar />

            <SidebarInset className="max-w-full overflow-x-hidden overflow-y-visible"
                style={{ direction: settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr' }}
                dir={settings.layoutDirection === 'rtl' ? 'rtl' : 'ltr'}
            >
                <header
                    className="mb-2 flex h-12 w-full min-w-0 shrink-0 items-center justify-between gap-1 border-b bg-background/95 px-2 py-2 shadow-sm backdrop-blur-md sm:h-14 sm:gap-2 sm:px-4 md:px-6"
                    >
                    {/* Sidebar + Breadcrumb */}
                    <div className={`flex min-w-0 flex-1 items-center gap-1 sm:gap-2 ${ settings.layoutDirection === "rtl" ? "order-2 flex-row-reverse" : "order-1" }`} >
                        {/* SidebarTrigger */}
                        <SidebarTrigger className={`-ml-1 ${ settings.layoutDirection === "rtl" ? "order-3" : "order-1" }`} />

                        {/* Separator */}
                        <Separator orientation="vertical" className="order-2 hidden h-4 sm:block" />

                        {/* Breadcrumb */}
                        <Breadcrumb className={`min-w-0 flex-1 overflow-hidden ${ settings.layoutDirection === "rtl" ? "order-1" : "order-3" }`} >
                            <BreadcrumbList className={`flex-nowrap gap-1 overflow-hidden whitespace-nowrap text-xs sm:gap-2 sm:text-sm ${ settings.layoutDirection === "rtl" ? "justify-end" : "justify-start" }`} >
                            <BreadcrumbItem className="shrink-0">
                                <BreadcrumbLink asChild>
                                    <Link href={route("dashboard")}>{t('Dashboard')}</Link>
                                </BreadcrumbLink>
                            </BreadcrumbItem>
                            {breadcrumbs?.map((crumb, index) => (
                                <Fragment key={index}>
                                <BreadcrumbSeparator className={`${index < (breadcrumbs?.length || 0) - 1 ? 'hidden sm:block' : 'shrink-0'} ${settings.layoutDirection === 'rtl' ? 'rotate-180' : ''}`} />
                                <BreadcrumbItem className={index < (breadcrumbs?.length || 0) - 1 ? 'hidden shrink-0 sm:inline-flex' : 'min-w-0'}>
                                    {crumb.url ? (
                                    <BreadcrumbLink asChild className="truncate">
                                        <Link href={crumb.url}>{crumb.label}</Link>
                                    </BreadcrumbLink>
                                    ) : (
                                    <BreadcrumbPage className="truncate">{crumb.label}</BreadcrumbPage>
                                    )}
                                </BreadcrumbItem>
                                </Fragment>
                            ))}
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>

                    {/* NavUser */}
                    <div
                        className={`flex shrink-0 items-center gap-1 sm:gap-2 md:gap-3 ${
                        settings.layoutDirection === "rtl" ? "order-1 flex-row-reverse" : "order-2"
                        }`}
                    >
                        {/* Leave Impersonation Button */}
                        {auth.impersonating && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.post(route('users.leave-impersonation'))}
                                className="text-orange-600 border-orange-600 hover:bg-transparent hover:text-orange-600"
                            >
                                <UserX className="h-4 w-4 mr-2" />
                                {t('Leave Login As User')}
                            </Button>
                        )}
                        <NavUser user={auth.user} inHeader={true} />
                    </div>
                </header>

                <main className="h-full min-w-0 max-w-full overflow-x-hidden p-3 sm:p-4 md:pt-0">
                    {pageTitle && (
                        <div className="flex items-center mb-6" dir={settings.layoutDirection}>
                            <h1 className="text-xl font-semibold text-gray-900 dark:text-white flex-1">{pageTitle}</h1>
                            <div className="flex-shrink-0">{pageActions}</div>
                        </div>
                    )}
                    {children}
                </main>
            </SidebarInset>
        </SidebarProvider>
        <CookieConsent settings={adminAllSetting || {}} />
        {generalAlerts.map((alert) => (
            <div key={alert.id}>{alert.component}</div>
        ))}
        
        {/* Floating AI Agent Button */}
        {auth.user?.permissions?.includes('manage-ai-agent') && !isAIAgentPage && (
            <div className="fixed bottom-8 right-8 z-50 animate-bounce" style={{ animationDuration: '2s' }}>
                <Button
                    onClick={() => router.visit(route('ai-agent.chat.page'))}
                    className="h-14 w-14 rounded-full shadow-lg hover:shadow-xl transition-shadow duration-200 bg-primary hover:bg-primary/90 p-0 [&_svg]:!size-7"
                >
                    <Bot className="text-primary-foreground" strokeWidth={2} />
                </Button>
            </div>
        )}
        </div>
        </>
    );
}

export default function AuthenticatedLayout(props: PropsWithChildren<{
    header?: ReactNode;
    breadcrumbs?: Array<{label: string, url?: string}>;
    pageTitle?: string;
    pageActions?: ReactNode;
    className?: string;
}>) {
    return (
        <BrandProvider>
            <AuthenticatedLayoutContent {...props} />
        </BrandProvider>
    );
}

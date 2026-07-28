import { Link, Head, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useState } from 'react';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useBrand } from '@/contexts/brand-context';
import { useFavicon } from '@/hooks/use-favicon';
import { getImagePath } from '@/utils/helpers';
import ApplicationLogo from '@/components/application-logo';
import CookieConsent from '@/components/cookie-consent';
import { ShieldCheck } from 'lucide-react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { settings, getPrimaryColor, getLogoSrc } = useBrand();
    const { adminAllSetting } = usePage().props as any;
    useFavicon();

    const logoSrc = getLogoSrc();
    const primaryColor = getPrimaryColor();
    const appName = settings.titleText || 'Wazely ERP';
    const [logoFailed, setLogoFailed] = useState(false);

    useEffect(() => {
        setLogoFailed(false);
    }, [logoSrc]);

    return (
        <>
        <Head title={adminAllSetting?.metaTitle}>
            <meta name="keywords" content={adminAllSetting?.metaKeywords || ''} />
            <meta name="description" content={adminAllSetting?.metaDescription || ''} />
            <meta property="og:image" content={adminAllSetting?.metaImage ? getImagePath(adminAllSetting.metaImage) : ''} />
        </Head>
        <div className="auth-shell relative flex min-h-svh items-center justify-center overflow-hidden bg-slate-50 px-4 py-8 dark:bg-slate-950 sm:px-6 lg:px-10">
            <style>{`
                .auth-shell .dark .bg-primary,
                .dark .auth-shell .bg-primary {
                    background-color: ${primaryColor} !important;
                    color: white !important;
                }
                .auth-shell .dark .bg-primary:hover,
                .dark .auth-shell .bg-primary:hover {
                    background-color: ${primaryColor}dd !important;
                }
            `}</style>
            <div className="absolute inset-y-0 left-0 hidden w-1/2 bg-primary lg:block" />
            <div className="absolute inset-y-0 right-0 hidden w-1/2 bg-slate-100 dark:bg-slate-900 lg:block" />
            <div
                className="absolute inset-0 opacity-40 dark:opacity-20"
                style={{
                    backgroundImage: 'radial-gradient(circle, rgba(100,116,139,0.22) 1.5px, transparent 1.5px)',
                    backgroundSize: '30px 30px',
                }}
            />
            <div className="absolute -right-28 top-0 hidden h-72 w-72 rounded-full border-[56px] border-primary/25 lg:block" />
            <div className="absolute -bottom-28 left-[28%] hidden h-72 w-72 rounded-full border-[28px] border-white/35 lg:block" />

            <div className="absolute right-4 top-4 z-20 sm:right-6">
                <LanguageSwitcher />
            </div>

            <main className="relative z-10 grid w-full max-w-5xl overflow-hidden rounded-2xl border border-white/50 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900 lg:min-h-[620px] lg:grid-cols-[1.05fr_0.95fr]">
                <section className="relative overflow-hidden bg-primary px-6 py-8 text-primary-foreground lg:hidden">
                    <div
                        className="absolute right-4 top-4 h-20 w-20 opacity-60"
                        style={{
                            backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.85) 1.5px, transparent 1.5px)',
                            backgroundSize: '12px 12px',
                        }}
                    />
                    <div className="absolute -bottom-12 -right-10 h-32 w-32 rounded-full border-[14px] border-white/50" />

                    <div className="relative flex items-center gap-3">
                        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
                            <ShieldCheck className="h-6 w-6" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm font-medium text-primary-foreground/80">Secure ERP access</p>
                            <h2 className="truncate text-2xl font-bold tracking-normal">{appName}</h2>
                        </div>
                    </div>
                </section>

                <section className="relative hidden overflow-hidden bg-primary px-12 py-14 text-primary-foreground lg:flex lg:flex-col lg:justify-between">
                    <div
                        className="absolute left-10 top-10 h-24 w-24 opacity-70"
                        style={{
                            backgroundImage: 'radial-gradient(circle, rgba(255,255,255,0.85) 1.5px, transparent 1.5px)',
                            backgroundSize: '14px 14px',
                        }}
                    />
                    <div className="absolute right-12 top-10 h-28 w-10 rounded-b-full bg-white/30" />
                    <div className="absolute bottom-10 right-10 h-48 w-48 rounded-full border-[16px] border-white/65" />

                    <div className="relative">
                        <div className="mb-8 inline-flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 text-sm font-medium backdrop-blur">
                            <ShieldCheck className="h-4 w-4" />
                            Secure ERP access
                        </div>
                        <h2 className="max-w-sm text-5xl font-bold leading-tight tracking-normal">
                            Welcome back to {appName}
                        </h2>
                        <p className="mt-5 max-w-sm text-base leading-7 text-primary-foreground/85">
                            Manage your company operations from one protected workspace.
                        </p>
                    </div>

                    <div className="relative text-sm text-primary-foreground/80">
                        {settings.footerText}
                    </div>
                </section>

                <section className="flex min-h-[calc(100svh-14rem)] flex-col justify-center px-6 py-10 sm:px-10 lg:min-h-0 lg:px-16">
                    <div className="mx-auto flex w-full max-w-md flex-col gap-8">
                        <div className="flex flex-col items-center gap-5 text-center">
                            <Link href={route('dashboard')} className="flex items-center justify-center">
                                <span className="flex h-20 min-w-20 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                                    {logoSrc && !logoFailed ? (
                                        <img
                                            src={getImagePath(logoSrc)}
                                            alt={appName}
                                            className="max-h-14 w-auto max-w-40 object-contain"
                                            onError={() => setLogoFailed(true)}
                                        />
                                    ) : (
                                        <ApplicationLogo className="h-12 w-12 text-primary" />
                                    )}
                                </span>
                                <span className="sr-only">{appName}</span>
                            </Link>

                            <div className="space-y-2">
                                <p className="text-sm font-semibold text-primary lg:hidden">{appName}</p>
                                <h1 className="text-2xl font-semibold text-slate-950 dark:text-white">{title}</h1>
                                <p className="text-sm leading-6 text-muted-foreground">{description}</p>
                            </div>
                        </div>

                        {children}
                    </div>
                </section>
            </main>
            <CookieConsent settings={adminAllSetting || {}} />
        </div>
        </>
    );
}

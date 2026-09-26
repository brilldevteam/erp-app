import { Head, Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren, useEffect, useState } from 'react';
import { LanguageSwitcher } from '@/components/language-switcher';
import { useBrand } from '@/contexts/brand-context';
import { useFavicon } from '@/hooks/use-favicon';
import { getImagePath } from '@/utils/helpers';
import CookieConsent from '@/components/cookie-consent';
import { BarChart3, Check, ShieldCheck, UsersRound } from 'lucide-react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
    variant?: 'login' | 'register' | 'default';
}

export default function AuthSimpleLayout({ children, title, description, variant = 'default' }: PropsWithChildren<AuthLayoutProps>) {
    const { settings, getPrimaryColor, getLogoSrc } = useBrand();
    const { adminAllSetting } = usePage().props as any;
    useFavicon();

    const logoSrc = getLogoSrc();
    const primaryColor = getPrimaryColor();
    const appName = settings.titleText || 'Wazely ERP';
    const isRegister = variant === 'register';
    const [logoFailed, setLogoFailed] = useState(false);

    useEffect(() => setLogoFailed(false), [logoSrc]);

    return (
        <>
            <Head title={adminAllSetting?.metaTitle}>
                <meta name="keywords" content={adminAllSetting?.metaKeywords || ''} />
                <meta name="description" content={adminAllSetting?.metaDescription || ''} />
                <meta property="og:image" content={adminAllSetting?.metaImage ? getImagePath(adminAllSetting.metaImage) : ''} />
            </Head>

            <div className="auth-shell relative min-h-[100dvh] overflow-hidden bg-[#f3f6f5] px-4 py-5 text-slate-950 sm:px-6 sm:py-8 lg:px-10">
                <style>{`
                    .auth-shell { --auth-accent: ${primaryColor}; }
                    .auth-shell .auth-primary { background-color: var(--auth-accent) !important; color: white !important; }
                    .auth-shell .auth-primary:hover { filter: brightness(.94); }
                    .auth-shell .auth-form input { border-color: #dbe3e0; background: #fbfcfc; }
                    .auth-shell .auth-form input:focus-visible { border-color: var(--auth-accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--auth-accent) 16%, transparent); }
                `}</style>

                <div className="pointer-events-none absolute inset-0 opacity-[0.35]" style={{ backgroundImage: 'radial-gradient(#b9c8c3 1px, transparent 1px)', backgroundSize: '24px 24px' }} />

                <header className="relative z-20 mx-auto mb-5 flex max-w-[1320px] items-center justify-between sm:mb-7">
                    <Link href={route('dashboard')} className="flex items-center gap-3" aria-label={appName}>
                        <span className="flex h-10 min-w-10 items-center justify-center rounded-xl border border-slate-200 bg-white px-2 shadow-sm">
                            {logoSrc && !logoFailed ? (
                                <img src={getImagePath(logoSrc)} alt={appName} className="max-h-7 w-auto max-w-32 object-contain" onError={() => setLogoFailed(true)} />
                            ) : <span className="flex items-center px-1 text-[17px] font-bold tracking-[-0.045em] text-slate-950">wazely<span className="ml-1 rounded bg-primary px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-normal text-white">erp</span></span>}
                        </span>
                        <span className="hidden text-sm font-semibold tracking-tight text-slate-700 sm:block">Business management, simplified.</span>
                    </Link>
                    <div className="rounded-xl border border-slate-200 bg-white/90 px-2 py-1 shadow-sm backdrop-blur"><LanguageSwitcher /></div>
                </header>

                <main className="relative z-10 mx-auto grid w-full max-w-[1320px] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_30px_90px_-45px_rgba(15,23,42,0.45)] lg:min-h-[720px] lg:grid-cols-[minmax(0,0.92fr)_minmax(520px,1.08fr)]">
                    <section className="order-2 flex items-center px-6 py-10 sm:px-10 lg:order-1 lg:px-16 lg:py-14 xl:px-20">
                        <div className="auth-form mx-auto w-full max-w-[460px]">
                            <div className="mb-8">
                                <span className="mb-5 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">
                                    <ShieldCheck className="h-3.5 w-3.5 text-primary" /> Secure workspace
                                </span>
                                <h1 className="text-3xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-4xl">{title}</h1>
                                <p className="mt-3 max-w-md text-sm leading-6 text-slate-500 sm:text-base">{description}</p>
                            </div>
                            {children}
                        </div>
                    </section>

                    <section className="relative order-1 m-3 min-h-[260px] overflow-hidden rounded-[20px] bg-[#07111f] p-7 text-white sm:p-10 lg:order-2 lg:m-4 lg:min-h-0 lg:p-12 xl:p-14">
                        <div className="absolute right-0 top-0 h-52 w-52 translate-x-16 -translate-y-16 rounded-full border-[34px] border-white/[0.06]" />
                        <div className="relative flex h-full flex-col justify-between gap-10">
                            <div>
                                <div className="auth-primary mb-7 flex h-11 w-11 items-center justify-center rounded-xl shadow-lg"><BarChart3 className="h-5 w-5" /></div>
                                <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-white/45">Your business, connected</p>
                                <h2 className="mt-5 max-w-[500px] text-3xl font-medium leading-[1.08] tracking-[-0.045em] text-white sm:text-4xl lg:text-[44px]">
                                    {isRegister ? 'Everything your team needs, in one place.' : 'A clearer way to run your business.'}
                                </h2>
                                <p className="mt-5 max-w-[440px] text-sm leading-7 text-slate-400 sm:text-[15px]">
                                    {isRegister ? 'Bring your people, projects and finances together in a workspace built for focused work.' : 'Projects, finance and your team stay connected in one focused workspace.'}
                                </p>
                            </div>

                            <div className="hidden lg:block">
                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-2xl border border-white/[0.08] bg-white/[0.055] p-5">
                                        <div className="flex items-center justify-between text-slate-400"><span className="text-[11px] font-medium uppercase tracking-[0.08em]">Active projects</span><BarChart3 className="h-4 w-4" /></div>
                                        <p className="mt-4 text-[28px] font-medium tracking-[-0.04em]">24</p>
                                        <p className="mt-1.5 flex items-center gap-1.5 text-[11px] text-emerald-300"><Check className="h-3.5 w-3.5" /> All on track</p>
                                    </div>
                                    <div className="rounded-2xl border border-white/[0.08] bg-white/[0.055] p-5">
                                        <div className="flex items-center justify-between text-slate-400"><span className="text-[11px] font-medium uppercase tracking-[0.08em]">Team members</span><UsersRound className="h-4 w-4" /></div>
                                        <p className="mt-4 text-[28px] font-medium tracking-[-0.04em]">48</p>
                                        <p className="mt-1.5 text-[11px] text-slate-500">Across 6 departments</p>
                                    </div>
                                </div>
                                <div className="mt-3 rounded-2xl border border-white/[0.08] bg-white/[0.055] p-5">
                                    <div className="mb-5 flex items-center justify-between"><span className="text-[11px] font-medium uppercase tracking-[0.08em] text-slate-400">Operations overview</span><span className="rounded-full border border-white/[0.08] bg-white/[0.06] px-2.5 py-1 text-[10px] text-slate-400">This month</span></div>
                                    <div className="flex h-24 items-end gap-3" aria-hidden="true">
                                        {[42, 56, 48, 76, 64, 88, 72, 94].map((height, index) => <span key={index} className="flex-1 rounded-t-md bg-white/15" style={{ height: `${height}%`, backgroundColor: index === 7 ? primaryColor : undefined }} />)}
                                    </div>
                                </div>
                            </div>

                            <p className="text-xs text-slate-500">{settings.footerText || `Copyright © ${appName}`}</p>
                        </div>
                    </section>
                </main>
                <CookieConsent settings={adminAllSetting || {}} />
            </div>
        </>
    );
}

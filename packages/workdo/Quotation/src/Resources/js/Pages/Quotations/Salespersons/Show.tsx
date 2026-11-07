import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { formatCurrency, formatDate } from '@/utils/helpers';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Banknote, CheckCircle2, ChevronRight, CircleDollarSign, FileText, Mail, ReceiptText, UserRound } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { getStatusBadgeClasses } from '../utils';

type WorkspaceTab = 'overview' | 'quotations' | 'payments';

type SalespersonQuotation = {
    id: number;
    quotation_number: string;
    quotation_date: string | null;
    due_date: string | null;
    customer_name: string;
    subject?: string | null;
    status: string;
    base_amount: number;
    invoice?: {
        id: number;
        invoice_number: string;
        status: string;
        base_amount: number;
        paid_base: number;
        balance_base: number;
    } | null;
};

type SalespersonPayment = {
    id: number;
    payment_number: string;
    payment_date: string | null;
    status: string;
    quotation_id: number;
    quotation_number: string;
    invoice_id?: number | null;
    invoice_number?: string | null;
    allocated_base: number;
};

interface Props {
    salesperson: { id: number; name: string; email?: string | null; is_active: boolean };
    quotations: SalespersonQuotation[];
    payments: SalespersonPayment[];
    summary: {
        quotation_count: number;
        quoted_base: number;
        converted_base: number;
        paid_base: number;
        outstanding_base: number;
        status_counts: Record<string, number>;
    };
    [key: string]: any;
}

export default function SalespersonWorkspace() {
    const { t } = useTranslation();
    const { salesperson, quotations, payments, summary } = usePage<Props>().props;
    const [activeTab, setActiveTab] = useState<WorkspaceTab>('overview');
    const tabs: Array<[WorkspaceTab, string]> = [['overview', 'Overview'], ['quotations', 'Quotations'], ['payments', 'Payments']];
    const openQuotation = (quotationId: number) => router.get(route('quotations.show', quotationId));
    const openInvoice = (invoiceId: number) => router.get(route('sales-invoices.show', invoiceId));

    const quotationList = (rows: SalespersonQuotation[]) => (
        <div className="divide-y">
            {rows.map(quotation => <div key={quotation.id} role="button" tabIndex={0} onClick={() => openQuotation(quotation.id)} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') openQuotation(quotation.id); }} className="group grid w-full cursor-pointer gap-3 px-5 py-4 text-left transition-colors hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary md:grid-cols-[minmax(145px,.75fr)_minmax(160px,1fr)_105px_120px_120px_20px] md:items-center md:px-6">
                <div><p className="font-semibold text-slate-900 group-hover:text-primary">{quotation.quotation_number}</p><p className="mt-0.5 text-xs text-slate-500">{quotation.quotation_date ? formatDate(quotation.quotation_date) : '-'}</p></div>
                <div className="min-w-0"><p className="truncate text-sm font-medium text-slate-800">{quotation.customer_name}</p><p className="mt-0.5 truncate text-xs text-slate-500">{quotation.subject || t('No subject')}</p></div>
                <div><span className={getStatusBadgeClasses(quotation.status)}>{t(quotation.status.charAt(0).toUpperCase() + quotation.status.slice(1))}</span></div>
                <div className="text-sm font-semibold tabular-nums md:text-right">{formatCurrency(quotation.base_amount)}</div>
                <div className="min-w-0 md:text-right" onClick={event => event.stopPropagation()}>{quotation.invoice ? <button type="button" className="break-words text-xs font-semibold text-primary hover:underline" onClick={() => openInvoice(quotation.invoice!.id)}>{quotation.invoice.invoice_number}</button> : <span className="text-xs text-slate-500">{t('Not invoiced')}</span>}</div>
                <ChevronRight className="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-primary" />
            </div>)}
            {!rows.length && <div className="px-6 py-14 text-center"><FileText className="mx-auto mb-3 h-10 w-10 text-muted-foreground" /><p className="font-medium">{t('No quotations assigned')}</p><p className="mt-1 text-sm text-muted-foreground">{t('Quotations assigned to this salesperson will appear here.')}</p></div>}
        </div>
    );

    return <AuthenticatedLayout
        breadcrumbs={[{ label: t('Sales') }, { label: t('Salespersons'), url: route('quotation-salespersons.index') }, { label: salesperson.name }]}
        pageTitle={salesperson.name}
        pageActions={<Button variant="outline" size="sm" onClick={() => router.get(route('quotation-salespersons.index'))}><ArrowLeft className="mr-2 h-4 w-4" />{t('All Salespersons')}</Button>}
    >
        <Head title={salesperson.name} />
        <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
            <Card className="overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                <CardContent className="p-0">
                    <div className="flex flex-col gap-5 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 items-center gap-4">
                            <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary"><UserRound className="h-7 w-7" /></span>
                            <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h1 className="truncate text-2xl font-semibold">{salesperson.name}</h1><Badge variant="outline" className={salesperson.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'}>{salesperson.is_active ? t('Active') : t('Inactive')}</Badge></div><p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground"><Mail className="h-3.5 w-3.5" />{salesperson.email || t('No email')}</p></div>
                        </div>
                        <div className="rounded-xl border bg-slate-50/60 px-4 py-3 text-right"><p className="text-xs font-medium uppercase text-muted-foreground">{t('Salesperson quotations')}</p><p className="mt-1 text-xl font-semibold tabular-nums">{summary.quotation_count}</p></div>
                    </div>
                    <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-5 py-2" role="tablist" aria-label={t('Salesperson workspace views')}>{tabs.map(([value, label]) => <button key={value} type="button" role="tab" aria-selected={activeTab === value} onClick={() => setActiveTab(value)} className={`whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${activeTab === value ? 'bg-background text-primary shadow-sm ring-1 ring-slate-200' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}>{t(label)}</button>)}</div>
                </CardContent>
            </Card>

            {activeTab === 'overview' && <>
                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label={t('Salesperson summary')}>
                    {[
                        [t('Quoted value'), summary.quoted_base, FileText, 'bg-blue-50 text-blue-600'],
                        [t('Converted to invoices'), summary.converted_base, ReceiptText, 'bg-violet-50 text-violet-600'],
                        [t('Payments collected'), summary.paid_base, CircleDollarSign, 'bg-emerald-50 text-emerald-700'],
                        [t('Outstanding'), summary.outstanding_base, Banknote, 'bg-amber-50 text-amber-700'],
                    ].map(([label, value, Icon, tone]) => <Card key={String(label)} className="rounded-2xl shadow-sm shadow-slate-200/30"><CardContent className="flex items-center justify-between p-5"><div><p className="text-xs font-medium text-muted-foreground">{label as string}</p><p className="mt-1 text-xl font-semibold tabular-nums">{formatCurrency(value as number)}</p></div><span className={`rounded-xl p-3 ${tone}`}><Icon className="h-5 w-5" /></span></CardContent></Card>)}
                </section>
                <div className="grid gap-5 lg:grid-cols-[1.5fr_.7fr]">
                    <Card className="overflow-hidden rounded-2xl shadow-sm shadow-slate-200/40"><CardHeader className="border-b"><CardTitle>{t('Recent quotations')}</CardTitle></CardHeader>{quotationList(quotations.slice(0, 5))}</Card>
                    <Card className="rounded-2xl shadow-sm shadow-slate-200/40"><CardHeader><CardTitle>{t('Quotation status')}</CardTitle></CardHeader><CardContent className="space-y-3">{Object.entries(summary.status_counts).map(([status, count]) => <div key={status} className="flex items-center justify-between rounded-xl border bg-slate-50/50 px-4 py-3"><span className="flex items-center gap-2 text-sm font-medium"><CheckCircle2 className="h-4 w-4 text-primary" />{t(status.charAt(0).toUpperCase() + status.slice(1))}</span><strong className="tabular-nums">{count}</strong></div>)}{!Object.keys(summary.status_counts).length && <p className="py-8 text-center text-sm text-muted-foreground">{t('No quotation activity yet.')}</p>}</CardContent></Card>
                </div>
            </>}

            {activeTab === 'quotations' && <Card className="overflow-hidden rounded-2xl shadow-sm shadow-slate-200/40"><div className="hidden grid-cols-[minmax(145px,.75fr)_minmax(160px,1fr)_105px_120px_120px_20px] gap-3 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 md:grid"><span>{t('Quotation')}</span><span>{t('Customer')}</span><span>{t('Status')}</span><span className="text-right">{t('Amount')}</span><span className="text-right">{t('Invoice')}</span><span /></div>{quotationList(quotations)}</Card>}

            {activeTab === 'payments' && <Card className="overflow-hidden rounded-2xl shadow-sm shadow-slate-200/40">
                <div className="hidden grid-cols-[1fr_150px_1fr_1fr_160px] gap-4 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 md:grid"><span>{t('Payment')}</span><span>{t('Date')}</span><span>{t('Quotation')}</span><span>{t('Invoice')}</span><span className="text-right">{t('Allocated amount')}</span></div>
                <div className="divide-y">{payments.map(payment => <div key={`${payment.id}-${payment.quotation_id}`} className="grid gap-3 px-5 py-4 text-left transition-colors hover:bg-blue-50/40 md:grid-cols-[1fr_150px_1fr_1fr_160px] md:items-center md:px-6"><span className="font-semibold text-slate-900">{payment.payment_number}</span><span className="text-sm text-slate-600">{payment.payment_date ? formatDate(payment.payment_date) : '-'}</span><button type="button" onClick={() => openQuotation(payment.quotation_id)} className="text-left text-sm font-medium text-primary hover:underline">{payment.quotation_number}</button>{payment.invoice_id ? <button type="button" onClick={() => openInvoice(payment.invoice_id!)} className="text-left text-sm font-medium text-primary hover:underline">{payment.invoice_number}</button> : <span className="text-sm text-slate-600">-</span>}<strong className="tabular-nums md:text-right">{formatCurrency(payment.allocated_base)}</strong></div>)}{!payments.length && <div className="px-6 py-14 text-center"><Banknote className="mx-auto mb-3 h-10 w-10 text-muted-foreground" /><p className="font-medium">{t('No payments attributed')}</p><p className="mt-1 text-sm text-muted-foreground">{t("Payments allocated to invoices converted from this salesperson's quotations will appear here.")}</p></div>}</div>
            </Card>}
        </div>
    </AuthenticatedLayout>;
}

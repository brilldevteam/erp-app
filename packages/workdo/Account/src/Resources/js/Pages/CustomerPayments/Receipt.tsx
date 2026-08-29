import React, { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import html2pdf from 'html2pdf.js';
import { Download, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatCurrency, formatDate, getCompanySetting, getImagePath } from '@/utils/helpers';
import { DocumentTemplate } from '@/types/document-template';
import { CustomerPayment } from './types';

interface ReceiptProps {
    payment: CustomerPayment;
    documentTemplate?: DocumentTemplate | null;
    requiresPaymentTemplate?: boolean;
    [key: string]: any;
}

export default function Receipt() {
    const { t } = useTranslation();
    const pageProps = usePage<ReceiptProps>().props;
    const { payment, documentTemplate, requiresPaymentTemplate } = pageProps;
    const [isDownloading, setIsDownloading] = useState(false);
    const hasTemplate = Boolean(documentTemplate && !requiresPaymentTemplate);

    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('download') === 'pdf' && hasTemplate) {
            setTimeout(() => downloadPDF(), 500);
        }
    }, [hasTemplate]);

    const downloadPDF = async () => {
        if (!hasTemplate) return;
        setIsDownloading(true);

        const printContent = document.querySelector('.customer-payment-receipt');
        if (printContent) {
            const opt = {
                margin: 0.2,
                filename: `customer-payment-receipt-${payment.payment_number || payment.id}.pdf`,
                image: { type: 'jpeg' as const, quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' as const },
            };

            try {
                await html2pdf().set(opt).from(printContent as HTMLElement).save();
                if (new URLSearchParams(window.location.search).get('download') === 'pdf') {
                    setTimeout(() => window.close(), 1000);
                }
            } catch (error) {
                console.error('PDF generation failed:', error);
            }
        }

        setIsDownloading(false);
    };

    if (!hasTemplate || !documentTemplate) {
        return <MissingPaymentTemplateNotice />;
    }

    const config = documentTemplate.config_json;
    const color = documentTemplate.primary_color || '#10b981';
    const signature = documentTemplate.signature_url;
    const companyLogo = documentTemplate.logo_url
        || getCompanySetting('logo_dark', pageProps)
        || getCompanySetting('logo_light', pageProps)
        || getCompanySetting('company_logo', pageProps)
        || getCompanySetting('logo', pageProps);
    const companyName = getCompanySetting('company_name', pageProps) || 'Company';
    const paymentMode = payment.bank_account?.account_name || payment.bank_account?.bank_name || '-';

    return (
        <div className="min-h-screen bg-slate-100 py-6 print:bg-white print:py-0">
            <Head title={t('Customer Payment Receipt')} />

            {isDownloading && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 print:hidden">
                    <div className="rounded-lg bg-white p-6 shadow-lg">
                        <div className="flex items-center gap-3">
                            <div className="h-6 w-6 animate-spin rounded-full border-b-2 border-primary"></div>
                            <p className="text-lg font-semibold text-gray-700">{t('Generating PDF...')}</p>
                        </div>
                    </div>
                </div>
            )}

            <div className="mx-auto mb-4 flex max-w-4xl justify-end print:hidden">
                <Button onClick={downloadPDF} disabled={isDownloading}>
                    <Download className="mr-2 h-4 w-4" />
                    {isDownloading ? t('Generating PDF...') : t('Download PDF')}
                </Button>
            </div>

            <div className="customer-payment-receipt relative mx-auto min-h-[10.5in] max-w-4xl overflow-hidden bg-white p-12 text-sm text-slate-900 shadow-sm print:shadow-none">
                <div className="absolute left-0 top-0 h-10 w-10" style={{ borderTop: `40px solid ${color}`, borderRight: '40px solid transparent' }} />

                <header className="flex items-start justify-between gap-8 border-b pb-12">
                    <div className="pt-14">
                        <h1 className="font-serif text-3xl font-bold uppercase tracking-wide">{t('PAYMENT')}</h1>
                        {config.documentDetails.showDocumentNumber && (
                            <p className="mt-2 text-base text-slate-500">{payment.payment_number || `Payment#${payment.id}`}</p>
                        )}
                    </div>
                    <div className="text-right">
                        {config.header.showLogo && companyLogo ? (
                            <img src={getImagePath(String(companyLogo), pageProps)} alt={companyName} className="ml-auto max-h-24 max-w-56 object-contain" />
                        ) : (
                            config.header.showCompanyName && <div className="text-2xl font-bold">{companyName}</div>
                        )}
                    </div>
                </header>

                <section className="grid grid-cols-1 gap-12 border-b py-12 md:grid-cols-[1fr_1.1fr]">
                    <div className="space-y-10 md:border-r md:pr-12">
                        <div>
                            <div className="font-serif text-base font-semibold text-slate-600">{t('Amount Received')}</div>
                            <div className="mt-5 font-serif text-5xl font-bold leading-none text-slate-900">{formatCurrency(payment.payment_amount, pageProps)}</div>
                        </div>
                        <div>
                            <div className="font-serif text-base font-semibold text-slate-600">{t('Received From')}</div>
                            <div className="mt-4 font-semibold" style={{ color }}>{payment.customer?.name || '-'}</div>
                            {payment.customer?.email && <div className="mt-1 text-slate-500">{payment.customer.email}</div>}
                        </div>
                    </div>

                    <div className="space-y-6 md:pl-4">
                        <PaymentDetail label={t('Payment Date')} value={formatDate(payment.payment_date, pageProps)} />
                        <PaymentDetail label={t('Reference Number')} value={payment.reference_number || '-'} />
                        <PaymentDetail label={t('Payment Mode')} value={paymentMode} />
                        <PaymentDetail label={t('Bank Account')} value={payment.bank_account?.account_number || '-'} />
                    </div>
                </section>

                {config.footer.showNotes && payment.notes && <Section title={t('Payment Notes')} content={payment.notes} />}
                {config.footer.showNotes && documentTemplate.notes && <Section title={t('Notes')} content={documentTemplate.notes} />}
                {config.footer.showTerms && documentTemplate.terms && <Section title={t('Terms and Conditions')} content={documentTemplate.terms} />}
                {config.footer.showBankDetails && documentTemplate.bank_details && <Section title={t('Bank Details')} content={documentTemplate.bank_details} />}

                {config.footer.showSignature && (
                    <div className="mt-20 flex justify-end">
                        <div className="w-56 text-center">
                            {signature && <img src={getImagePath(String(signature), pageProps)} alt="Signature" className="mx-auto mb-2 max-h-20 max-w-48 object-contain" />}
                            <div className="border-t pt-3 font-serif text-slate-600">{documentTemplate.signature_text || t('Authorized Signature')}</div>
                        </div>
                    </div>
                )}

                {config.footer.footerText && <div className="mt-10 border-t pt-4 text-center text-slate-500">{config.footer.footerText}</div>}
            </div>
        </div>
    );
}

function MissingPaymentTemplateNotice() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
            <Head title={t('Payment Template Required')} />
            <div className="max-w-lg rounded-lg bg-white p-8 text-center shadow-sm">
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <FileText className="h-7 w-7" />
                </div>
                <h1 className="text-xl font-bold text-slate-900">{t('Payment template required')}</h1>
                <p className="mt-3 text-slate-600">{t('Please create an active Payment template before downloading customer payment receipts.')}</p>
                <p className="mt-2 text-sm text-slate-500">{t('Go to Templates, create a template, and select Type as Payment.')}</p>
                <a href={route('document-templates.create')} className="mt-6 inline-flex rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90">
                    {t('Create Payment Template')}
                </a>
            </div>
        </div>
    );
}

function PaymentDetail({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="grid grid-cols-2 gap-6">
            <div className="font-serif text-base text-slate-500">{label}</div>
            <div className="font-semibold text-slate-900">{value}</div>
        </div>
    );
}

function Section({ title, content }: { title: string; content: string }) {
    return (
        <section className="mt-8 max-w-2xl">
            <h2 className="font-serif text-base font-semibold text-slate-700">{title}</h2>
            <p className="mt-2 whitespace-pre-line text-slate-600">{content}</p>
        </section>
    );
}

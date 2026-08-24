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
    requiresInvoiceTemplate?: boolean;
    [key: string]: any;
}

export default function Receipt() {
    const { t } = useTranslation();
    const pageProps = usePage<ReceiptProps>().props;
    const { payment, documentTemplate, requiresInvoiceTemplate } = pageProps;
    const [isDownloading, setIsDownloading] = useState(false);

    const hasTemplate = Boolean(documentTemplate && !requiresInvoiceTemplate);

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
                margin: 0.25,
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
        return <MissingInvoiceTemplateNotice />;
    }

    const config = documentTemplate.config_json;
    const color = documentTemplate.primary_color || '#10b981';
    const signature = documentTemplate.signature_url;
    const alignment = config.header.alignment || 'left';
    const companyLogo = documentTemplate.logo_url
        || getCompanySetting('logo_dark', pageProps)
        || getCompanySetting('logo_light', pageProps)
        || getCompanySetting('company_logo', pageProps)
        || getCompanySetting('logo', pageProps);
    const companyName = getCompanySetting('company_name', pageProps) || 'Company';
    const companyAddress = [
        getCompanySetting('company_address', pageProps),
        getCompanySetting('company_city', pageProps),
        getCompanySetting('company_country', pageProps),
    ].filter(Boolean).join('\n');
    const companyContact = [
        getCompanySetting('company_telephone', pageProps) || getCompanySetting('company_phone', pageProps),
        getCompanySetting('company_email', pageProps),
    ].filter(Boolean).join(' | ');
    const totalAllocated = payment.allocations?.reduce((sum, allocation) => sum + Number(allocation.allocated_amount || 0), 0) || 0;
    const totalCreditApplied = payment.credit_note_applications?.reduce((sum, application) => sum + Number(application.applied_amount || 0), 0) || 0;
    const totalApplied = totalAllocated + totalCreditApplied;
    const headerLayoutClass = alignment === 'center' ? 'flex-col items-center text-center' : 'items-start justify-between';
    const companyInfoClass = alignment === 'right' ? 'order-2 text-right' : '';
    const badgeClass = alignment === 'right' ? 'order-1 mr-auto' : alignment === 'center' ? '' : 'ml-auto';
    const logoClass = alignment === 'center' ? 'mx-auto' : alignment === 'right' ? 'ml-auto' : '';

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

            <div className="customer-payment-receipt mx-auto max-w-4xl bg-white p-8 text-sm text-slate-900 shadow-sm print:shadow-none">
                <div className={`flex gap-6 border-b pb-6 ${headerLayoutClass}`}>
                    <div className={`space-y-2 ${companyInfoClass}`}>
                        {config.header.showLogo && companyLogo && (
                            <img src={getImagePath(String(companyLogo), pageProps)} alt={companyName} className={`${logoClass} max-h-16 max-w-40 object-contain`} />
                        )}
                        {config.header.showCompanyName && <h2 className="text-xl font-bold">{companyName}</h2>}
                        {config.header.showCompanyAddress && companyAddress && <p className="whitespace-pre-line text-slate-600">{companyAddress}</p>}
                        {config.header.showContactDetails && companyContact && <p className="text-slate-600">{companyContact}</p>}
                    </div>
                    <div className={`${badgeClass} min-w-52 rounded-lg p-4 text-white`} style={{ backgroundColor: color }}>
                        <div className="text-2xl font-bold">{t('RECEIPT')}</div>
                        {config.documentDetails.showDocumentNumber && <div>#{payment.payment_number || payment.id}</div>}
                        {config.documentDetails.showDocumentDate && <div>{t('Date')}: {formatDate(payment.payment_date, pageProps)}</div>}
                        <div className="mt-2 inline-flex rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase">{t(payment.status)}</div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 py-6 md:grid-cols-2">
                    <div>
                        <div className="mb-2 font-semibold" style={{ color }}>{t('Received From')}</div>
                        <div className="font-medium">{payment.customer?.name || '-'}</div>
                        {config.customerBlock.showContactPerson && payment.customer?.name && <div>{payment.customer.name}</div>}
                        {payment.customer?.email && <div>{payment.customer.email}</div>}
                    </div>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Detail label={t('Payment Date')} value={formatDate(payment.payment_date, pageProps)} />
                        <Detail label={t('Reference Number')} value={payment.reference_number || '-'} />
                        <div className="sm:col-span-2">
                            <Detail
                                label={t('Bank Account')}
                                value={`${payment.bank_account?.account_name || '-'}${payment.bank_account?.account_number ? ` (${payment.bank_account.account_number})` : ''}`}
                            />
                        </div>
                    </div>
                </div>

                <div className="mb-6 flex justify-end">
                    <div className="w-full rounded-lg bg-slate-50 p-5 md:w-96">
                        <div className="flex items-center justify-between gap-4">
                            <span className="font-semibold uppercase tracking-wide text-slate-500">{t('Amount Received')}</span>
                            <span className="text-3xl font-bold" style={{ color }}>{formatCurrency(payment.payment_amount, pageProps)}</span>
                        </div>
                    </div>
                </div>

                {payment.allocations && payment.allocations.length > 0 && (
                    <ReceiptTable title={t('Invoice Allocations')} color={color}>
                        <thead>
                            <tr className="text-white" style={{ backgroundColor: color }}>
                                <th className="px-3 py-2 text-left">{t('Invoice Number')}</th>
                                <th className="px-3 py-2 text-left">{t('Invoice Date')}</th>
                                <th className="px-3 py-2 text-right">{t('Invoice Total')}</th>
                                <th className="px-3 py-2 text-right">{t('Allocated Amount')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payment.allocations.map((allocation) => (
                                <tr key={allocation.id} className="border-b">
                                    <td className="px-3 py-3 font-medium">{allocation.invoice?.invoice_number || '-'}</td>
                                    <td className="px-3 py-3">{allocation.invoice?.invoice_date ? formatDate(allocation.invoice.invoice_date, pageProps) : '-'}</td>
                                    <td className="px-3 py-3 text-right">{formatCurrency(allocation.invoice?.total_amount || 0, pageProps)}</td>
                                    <td className="px-3 py-3 text-right font-semibold">{formatCurrency(allocation.allocated_amount, pageProps)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </ReceiptTable>
                )}

                {payment.credit_note_applications && payment.credit_note_applications.length > 0 && (
                    <ReceiptTable title={t('Credit Notes Applied')} color={color}>
                        <thead>
                            <tr className="text-white" style={{ backgroundColor: color }}>
                                <th className="px-3 py-2 text-left">{t('Credit Note Number')}</th>
                                <th className="px-3 py-2 text-left">{t('Application Date')}</th>
                                <th className="px-3 py-2 text-right">{t('Applied Amount')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payment.credit_note_applications.map((application) => (
                                <tr key={application.id} className="border-b">
                                    <td className="px-3 py-3 font-medium">{application.credit_note?.credit_note_number || '-'}</td>
                                    <td className="px-3 py-3">{application.application_date ? formatDate(application.application_date, pageProps) : '-'}</td>
                                    <td className="px-3 py-3 text-right font-semibold">{formatCurrency(application.applied_amount, pageProps)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </ReceiptTable>
                )}

                <div className="mt-6 flex justify-end">
                    <div className="w-72 space-y-2 rounded-lg bg-slate-50 p-4">
                        <TotalRow label={t('Amount Received')} value={formatCurrency(payment.payment_amount, pageProps)} />
                        {totalCreditApplied > 0 && <TotalRow label={t('Credit Applied')} value={formatCurrency(totalCreditApplied, pageProps)} />}
                        <div className="flex justify-between border-t pt-2 text-lg font-bold">
                            <span>{t('Total Applied')}</span>
                            <span>{formatCurrency(totalApplied || payment.payment_amount, pageProps)}</span>
                        </div>
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className="space-y-4">
                        {config.footer.showNotes && payment.notes && <Section title={t('Payment Notes')} content={payment.notes} />}
                        {config.footer.showNotes && documentTemplate.notes && <Section title={t('Notes')} content={documentTemplate.notes} />}
                        {config.footer.showTerms && documentTemplate.terms && <Section title={t('Terms and Conditions')} content={documentTemplate.terms} />}
                        {config.footer.showBankDetails && documentTemplate.bank_details && <Section title={t('Bank Details')} content={documentTemplate.bank_details} />}
                    </div>
                    {config.footer.showSignature && (
                        <div className="flex items-end justify-end">
                            <div className="w-56 text-center">
                                {signature && <img src={getImagePath(String(signature), pageProps)} alt="Signature" className="mx-auto mb-2 max-h-16 max-w-40 object-contain" />}
                                <div className="border-t pt-2">{documentTemplate.signature_text || t('Authorized Signature')}</div>
                            </div>
                        </div>
                    )}
                </div>

                {config.footer.footerText && <div className="mt-8 border-t pt-4 text-center text-slate-500">{config.footer.footerText}</div>}
            </div>
        </div>
    );
}

function MissingInvoiceTemplateNotice() {
    const { t } = useTranslation();

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-100 p-6">
            <Head title={t('Invoice Template Required')} />
            <div className="max-w-lg rounded-lg bg-white p-8 text-center shadow-sm">
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                    <FileText className="h-7 w-7" />
                </div>
                <h1 className="text-xl font-bold text-slate-900">{t('Invoice template required')}</h1>
                <p className="mt-3 text-slate-600">
                    {t('Please create an active Invoice template before downloading customer payment receipts.')}
                </p>
                <p className="mt-2 text-sm text-slate-500">{t('Go to Templates, create a template, and select Type as Invoice.')}</p>
                <a href={route('document-templates.create')} className="mt-6 inline-flex rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary/90">
                    {t('Create Invoice Template')}
                </a>
            </div>
        </div>
    );
}

function ReceiptTable({ title, color, children }: { title: string; color: string; children: React.ReactNode }) {
    return (
        <div className="mb-8">
            <h3 className="mb-3 font-semibold" style={{ color }}>{title}</h3>
            <table className="w-full border-collapse text-sm">{children}</table>
        </div>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="font-semibold text-slate-500">{label}</div>
            <div className="mt-1 text-slate-900">{value}</div>
        </div>
    );
}

function TotalRow({ label, value }: { label: string; value: string }) {
    return <div className="flex justify-between"><span>{label}</span><span>{value}</span></div>;
}

function Section({ title, content }: { title: string; content: string }) {
    return <div><div className="mb-1 font-semibold">{title}</div><div className="whitespace-pre-line text-slate-600">{content}</div></div>;
}
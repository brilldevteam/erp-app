import { Fragment, useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import html2pdf from 'html2pdf.js';
import { formatCurrency, formatDate, getCompanySetting } from '@/utils/helpers';

type ColumnKey = 'account_code' | 'customer' | 'invoice_number' | 'current' | '1_30_days' | '31_60_days' | '61_90_days' | 'over_90_days' | 'total';
const amountColumns: ColumnKey[] = ['current', '1_30_days', '31_60_days', '61_90_days', 'over_90_days', 'total'];

export default function Print() {
    const { t } = useTranslation();
    const { data, filters, columns } = usePage<any>().props as { data: any; filters: any; columns: ColumnKey[] };
    const [isDownloading, setIsDownloading] = useState(false);
    const columnDefinitions: Array<{ key: ColumnKey; label: string; align: 'left' | 'right' }> = [
        { key: 'account_code', label: t('Account Code'), align: 'left' },
        { key: 'customer', label: t('Customer'), align: 'left' },
        { key: 'invoice_number', label: t('Invoice Number'), align: 'left' },
        { key: 'current', label: t('Current'), align: 'right' },
        { key: '1_30_days', label: `1-30 ${t('Days')}`, align: 'right' },
        { key: '31_60_days', label: `31-60 ${t('Days')}`, align: 'right' },
        { key: '61_90_days', label: `61-90 ${t('Days')}`, align: 'right' },
        { key: 'over_90_days', label: `>90 ${t('Days')}`, align: 'right' },
        { key: 'total', label: t('Total'), align: 'right' },
    ];
    const activeColumns = columnDefinitions.filter(column => columns.includes(column.key));
    const selectedCustomer = filters.customer_id
        ? data.filter_options.customers.find((customer: any) => customer.id.toString() === filters.customer_id.toString())?.name
        : null;

    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('download') === 'pdf') downloadPDF();
    }, []);

    const downloadPDF = async () => {
        setIsDownloading(true);
        const printContent = document.querySelector('.report-container');
        if (printContent) {
            const opt = {
                margin: 0.25,
                filename: `invoice-aging-${filters.as_of_date}.pdf`,
                image: { type: 'jpeg' as const, quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' as const }
            };
            try { await html2pdf().set(opt).from(printContent).save(); }
            catch (error) { console.error('PDF generation failed:', error); }
        }
        setIsDownloading(false);
    };

    const invoiceValue = (key: ColumnKey, customer: any, invoice: any) => {
        if (key === 'account_code') return customer.account_code || '-';
        if (key === 'customer') return customer.customer_name;
        if (key === 'invoice_number') return invoice.invoice_number;
        return formatCurrency(invoice[key]);
    };

    return (
        <div className="min-h-screen bg-white">
            <Head title={t('Invoice Aging Report')} />
            {isDownloading && <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50"><div className="bg-white p-6 rounded-lg shadow-lg"><p className="text-lg font-semibold text-gray-700">{t('Generating PDF...')}</p></div></div>}
            <div className="report-container bg-white max-w-7xl mx-auto p-8">
                <div className="border-b-2 border-gray-800 pb-5 mb-6">
                    <div className="flex justify-between items-start">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900 mb-2">{getCompanySetting('company_name') || 'YOUR COMPANY'}</h1>
                            {getCompanySetting('company_address') && <p className="text-sm text-gray-600">{getCompanySetting('company_address')}</p>}
                        </div>
                        <div className="text-right">
                            <h2 className="text-2xl font-bold text-gray-900 mb-2">{t('INVOICE AGING REPORT')}</h2>
                            <p className="text-sm text-gray-600">{t('As of')}: {formatDate(filters.as_of_date)}</p>
                            {selectedCustomer && <p className="text-xs text-gray-600">{t('Customer')}: {selectedCustomer}</p>}
                            {filters.invoice_date_from && <p className="text-xs text-gray-600">{t('Invoice Date Range')}: {formatDate(filters.invoice_date_from)} - {formatDate(filters.invoice_date_to)}</p>}
                            {filters.aging_bucket && <p className="text-xs text-gray-600">{t('Aging Bucket')}: {columnDefinitions.find(column => column.key === filters.aging_bucket)?.label}</p>}
                        </div>
                    </div>
                </div>
                <table className="w-full border-collapse">
                    <thead><tr className="border-b-2 border-black">{activeColumns.map(column => <th key={column.key} className={`py-2 px-2 text-xs font-semibold ${column.align === 'right' ? 'text-right' : 'text-left'}`}>{column.label}</th>)}</tr></thead>
                    <tbody>
                        {data.customers.map((customer: any) => (
                            <Fragment key={`${customer.account_code}-${customer.customer_name}`}>
                                {customer.invoices.map((invoice: any) => (
                                    <tr key={invoice.id} className="border-b border-gray-200">
                                        {activeColumns.map(column => <td key={column.key} className={`whitespace-nowrap py-2 px-2 text-xs ${column.align === 'right' ? 'text-right' : 'text-left'} ${column.key === 'invoice_number' || column.key === 'total' ? 'font-semibold' : ''}`}>{invoiceValue(column.key, customer, invoice)}</td>)}
                                    </tr>
                                ))}
                                <tr className="border-b border-gray-400 bg-gray-50 font-semibold">
                                    {activeColumns.map(column => <td key={column.key} className={`py-2 px-2 text-xs ${column.align === 'right' ? 'text-right' : 'text-left'}`}>{column.key === 'customer' ? `${customer.customer_name} ${t('Subtotal')}` : amountColumns.includes(column.key) ? formatCurrency(customer[column.key]) : ''}</td>)}
                                </tr>
                                <tr aria-hidden="true"><td colSpan={activeColumns.length} className="h-1.5 border-y border-gray-300 bg-gray-200 p-0" /></tr>
                            </Fragment>
                        ))}
                        <tr className="border-t-2 border-black font-bold">
                            {activeColumns.map(column => <td key={column.key} className={`py-3 px-2 text-sm ${column.align === 'right' ? 'text-right' : 'text-left'}`}>{column.key === 'customer' ? t('GRAND TOTAL') : amountColumns.includes(column.key) ? formatCurrency(data.aging_summary[column.key]) : ''}</td>)}
                        </tr>
                    </tbody>
                </table>
                <div className="mt-8 pt-4 border-t text-center text-xs text-gray-600"><p>{t('Generated on')} {formatDate(new Date().toISOString())}</p></div>
            </div>
        </div>
    );
}

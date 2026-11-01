import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import html2pdf from 'html2pdf.js';
import { formatCurrency, formatDate, getCompanySetting } from '@/utils/helpers';
import { formatTransactionMoney } from '@/utils/transaction-money';
import { lineDiscountLabel } from '@/utils/line-discount';

// Local Purchase Order (LPO) sent to the vendor. Opened with ?download=pdf it saves itself as a PDF, like the bill print.
export default function Print() {
    const { t } = useTranslation();
    const { order } = usePage<any>().props;
    const [isDownloading, setIsDownloading] = useState(false);
    const companyCurrency = String(getCompanySetting('defaultCurrency') || order.currency_code || '').toUpperCase();
    const money = (value: number | string) => (order.currency_code || companyCurrency).toUpperCase() === companyCurrency ? formatCurrency(value) : formatTransactionMoney(value, order.currency_code);
    const vendor = order.vendor_details;
    const billing = vendor?.billing_address;
    const discount = Number(order.line_discount_amount || 0) + Number(order.document_discount_amount || 0);

    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('download') === 'pdf') {
            setTimeout(() => downloadPDF(), 800);
        }
    }, []);

    const downloadPDF = async () => {
        setIsDownloading(true);
        const content = document.querySelector('.lpo-container');
        if (content) {
            try {
                await html2pdf().set({
                    margin: 0.25,
                    filename: `${order.purchase_order_number}.pdf`,
                    image: { type: 'jpeg' as const, quality: 0.98 },
                    html2canvas: { scale: 2 },
                    jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' as const },
                }).from(content as HTMLElement).save();
                setTimeout(() => window.close(), 1000);
            } catch (error) {
                console.error('PDF generation failed:', error);
            }
        }
        setIsDownloading(false);
    };

    return (
        <div className="min-h-screen bg-white">
            <Head title={`${t('Local Purchase Order')} ${order.purchase_order_number}`} />

            {isDownloading && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                    <div className="rounded-lg bg-white p-6 shadow-lg">
                        <div className="flex items-center space-x-3">
                            <div className="h-6 w-6 animate-spin rounded-full border-b-2 border-blue-600"></div>
                            <p className="text-lg font-semibold text-gray-700">{t('Generating PDF...')}</p>
                        </div>
                    </div>
                </div>
            )}

            <div className="lpo-container mx-auto max-w-4xl bg-white p-8">
                {/* Header */}
                <div className="mb-8 flex items-start justify-between">
                    <div className="w-1/2">
                        <h1 className="mb-4 text-2xl font-bold">{getCompanySetting('company_name') || 'YOUR COMPANY'}</h1>
                        <div className="space-y-1 text-sm">
                            {getCompanySetting('company_address') && <p>{getCompanySetting('company_address')}</p>}
                            {(getCompanySetting('company_city') || getCompanySetting('company_state') || getCompanySetting('company_zipcode')) && (
                                <p>{getCompanySetting('company_city')}{getCompanySetting('company_state') && `, ${getCompanySetting('company_state')}`} {getCompanySetting('company_zipcode')}</p>
                            )}
                            {getCompanySetting('company_country') && <p>{getCompanySetting('company_country')}</p>}
                            {getCompanySetting('company_telephone') && <p>{t('Phone')}: {getCompanySetting('company_telephone')}</p>}
                            {getCompanySetting('company_email') && <p>{t('Email')}: {getCompanySetting('company_email')}</p>}
                            {getCompanySetting('registration_number') && <p>{t('Registration')}: {getCompanySetting('registration_number')}</p>}
                        </div>
                    </div>
                    <div className="w-1/2 text-right">
                        <h2 className="mb-2 text-2xl font-bold">{t('LOCAL PURCHASE ORDER')}</h2>
                        <p className="text-lg font-semibold">#{order.purchase_order_number}</p>
                        <div className="mt-2 space-y-1 text-sm">
                            <p>{t('Order Date')}: {formatDate(order.order_date)}</p>
                            {order.expected_delivery_date && <p>{t('Delivery Date')}: {formatDate(order.expected_delivery_date)}</p>}
                            {order.vendor_reference && <p>{t('Vendor Reference')}: {order.vendor_reference}</p>}
                            {order.vendor_quotation_number && <p>{t('Vendor Quotation Number')}: {order.vendor_quotation_number}</p>}
                        </div>
                    </div>
                </div>

                {/* Vendor and delivery */}
                <div className="mb-8 flex justify-between">
                    <div className="w-1/2">
                        <h3 className="mb-3 font-bold">{t('VENDOR')}</h3>
                        <div className="space-y-1 text-sm">
                            <p className="font-semibold">{vendor?.company_name || order.vendor?.name || '-'}</p>
                            {(vendor?.contact_person_name || order.vendor?.name) && <p>{t('Contact Person')}: {vendor?.contact_person_name || order.vendor?.name}</p>}
                            {order.vendor?.email && <p>{order.vendor.email}</p>}
                            {billing?.address_line_1 && <p>{billing.address_line_1}</p>}
                            {(billing?.city || billing?.state || billing?.zip_code) && <p>{[billing?.city, billing?.state].filter(Boolean).join(', ')} {billing?.zip_code}</p>}
                        </div>
                    </div>
                    <div className="w-1/2 text-right">
                        <h3 className="mb-3 font-bold">{t('DELIVER TO')}</h3>
                        <div className="space-y-1 text-sm">
                            {order.warehouse ? (
                                <>
                                    <p className="font-semibold">{order.warehouse.name}</p>
                                    {order.warehouse.address && <p>{order.warehouse.address}</p>}
                                    {(order.warehouse.city || order.warehouse.zip_code) && <p>{order.warehouse.city} {order.warehouse.zip_code}</p>}
                                    {order.warehouse.phone && <p>{t('Phone')}: {order.warehouse.phone}</p>}
                                </>
                            ) : (
                                <>
                                    <p className="font-semibold">{getCompanySetting('company_name') || '-'}</p>
                                    {getCompanySetting('company_address') && <p>{getCompanySetting('company_address')}</p>}
                                    {getCompanySetting('company_city') && <p>{getCompanySetting('company_city')}</p>}
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* Items */}
                <div className="mb-8">
                    <table className="w-full table-fixed">
                        <thead>
                            <tr className="border-b border-gray-300">
                                <th className="w-[40%] py-3 text-left font-bold">{t('ITEM')}</th>
                                <th className="py-3 text-center font-bold">{t('QTY')}</th>
                                <th className="py-3 text-right font-bold">{t('PRICE')}</th>
                                <th className="py-3 text-right font-bold">{t('DISCOUNT')}</th>
                                <th className="py-3 text-right font-bold">{t('TAX')}</th>
                                <th className="py-3 text-right font-bold">{t('TOTAL')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {order.items?.map((item: any) => (
                                <tr key={item.id} className="page-break-inside-avoid align-top">
                                    <td className="py-4">
                                        <div className="font-semibold">{item.item_name}</div>
                                        {item.description && <div className="mt-1 whitespace-pre-wrap text-xs text-gray-600">{item.description}</div>}
                                    </td>
                                    <td className="py-4 text-center">{Number(item.quantity)} {item.unit}</td>
                                    <td className="py-4 text-right">{money(item.unit_price)}</td>
                                    <td className="py-4 text-right text-sm">
                                        {lineDiscountLabel({ discount_type: item.discount_type, discount_percentage: item.discount_type === 'percentage' ? item.discount_value : 0, discount_amount: item.discount_amount }, money) ?? '-'}
                                    </td>
                                    <td className="py-4 text-right text-sm">
                                        {item.taxes?.length ? (
                                            <>
                                                {item.taxes.map((tax: any) => <div key={tax.id}>{tax.tax_name} ({Number(tax.tax_rate)}%)</div>)}
                                                <div className="font-medium">{money(item.tax_amount)}</div>
                                            </>
                                        ) : t('No Tax')}
                                    </td>
                                    <td className="py-4 text-right font-semibold">{money(item.total_amount)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Summary */}
                <div className="page-break-inside-avoid mb-8 flex justify-end">
                    <div className="w-80 border border-gray-400 p-4">
                        <div className="space-y-2">
                            <div className="flex justify-between"><span>{t('Subtotal')}:</span><span>{money(order.subtotal)}</span></div>
                            {discount > 0 && <div className="flex justify-between"><span>{t('Discount')}:</span><span>-{money(discount)}</span></div>}
                            {Number(order.tax_amount) > 0 && <div className="flex justify-between"><span>{t('Tax')}:</span><span>{money(order.tax_amount)}</span></div>}
                            {Number(order.shipping_amount) > 0 && <div className="flex justify-between"><span>{t('Shipping')}:</span><span>{money(order.shipping_amount)}</span></div>}
                            {Number(order.adjustment_amount) !== 0 && <div className="flex justify-between"><span>{t('Adjustment')}:</span><span>{money(order.adjustment_amount)}</span></div>}
                            <div className="mt-2 border-t border-gray-400 pt-2">
                                <div className="flex justify-between text-lg font-bold"><span>{t('TOTAL')}:</span><span>{money(order.total_amount)}</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                {(order.notes || order.terms) && (
                    <div className="page-break-inside-avoid mb-8 space-y-4 text-sm">
                        {order.notes && <div><h3 className="mb-1 font-bold">{t('NOTES')}</h3><p className="whitespace-pre-wrap">{order.notes}</p></div>}
                        {order.terms && <div><h3 className="mb-1 font-bold">{t('TERMS & CONDITIONS')}</h3><p className="whitespace-pre-wrap">{order.terms}</p></div>}
                    </div>
                )}

                {/* Signatures */}
                <div className="page-break-inside-avoid mt-12 flex justify-between text-sm">
                    <div className="w-1/3 border-t border-gray-500 pt-2 text-center">{t('Prepared by')}</div>
                    <div className="w-1/3 border-t border-gray-500 pt-2 text-center">{t('Authorised Signature')}</div>
                </div>
            </div>

            <style>{`
                body { -webkit-print-color-adjust: exact; color-adjust: exact; font-family: Arial, sans-serif; }
                @page { margin: 0.5in; size: A4; }
                .lpo-container { max-width: 100%; margin: 0; box-shadow: none; }
                .page-break-inside-avoid { page-break-inside: avoid; break-inside: avoid; }
                @media print { body { background: white; } }
            `}</style>
        </div>
    );
}

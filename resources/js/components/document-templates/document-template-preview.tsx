import { DocumentTemplate, DocumentTemplateConfig, TemplateSampleDocument } from '@/types/document-template';
import { formatCurrency, getImagePath } from '@/utils/helpers';
import { Globe2, Mail, MapPin, Phone } from 'lucide-react';
import { useState } from 'react';

const labels: Record<string, string> = {
    item: 'Item',
    description: 'Description',
    quantity: 'Qty',
    rate: 'Rate',
    discount: 'Discount',
    tax: 'Tax',
    total: 'Total',
};

const money = (value: number) => formatCurrency(value);
const hasTax = (item: Record<string, any>) => Boolean(item.has_tax ?? Number(item.tax) > 0);

const documentDate = (value?: string) => {
    if (!value) return '-';
    const date = new Date(`${value}T00:00:00`);
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

export default function DocumentTemplatePreview({
    template,
    document,
    compact = false,
}: {
    template: Partial<DocumentTemplate> & { config_json: DocumentTemplateConfig };
    document: TemplateSampleDocument;
    compact?: boolean;
}) {
    const config = template.config_json;
    const color = template.primary_color || '#10b981';
    const logo = template.logo_url || document.company.logo;
    const signature = template.signature_url;
    const alignment = config.header.alignment || 'left';
    const title = document.type === 'invoice' ? 'INVOICE' : 'QUOTATION';
    const columns = [...config.itemsTable.columns];
    if (document.items.some(item => Number(item.discount) > 0) && !columns.includes('discount')) {
        columns.splice(Math.max(0, columns.indexOf('total')), 0, 'discount');
    }
    const hasAnyTax = document.items.some(hasTax);
    const headerLayoutClass =
        alignment === 'center'
            ? 'flex-col items-center text-center'
            : 'items-start justify-between';
    const companyInfoClass = alignment === 'right' ? 'order-2 text-right' : '';
    const documentBadgeClass =
        alignment === 'right'
            ? 'order-1 mr-auto'
            : alignment === 'center'
                ? ''
                : 'ml-auto';
    const logoClass = alignment === 'center' ? 'mx-auto' : alignment === 'right' ? 'ml-auto' : '';

    if (document.type === 'payment') {
        return <PaymentTemplatePreview template={template} document={document} compact={compact} logo={logo} signature={signature} />;
    }

    if (document.type === 'invoice') {
        return <ReferenceInvoiceTemplate template={template} document={document} compact={compact} />;
    }

    if (document.type === 'quotation') {
        return <ReferenceQuotationTemplate template={template} document={document} compact={compact} />;
    }

    return (
        <div className={`bg-white text-slate-900 shadow-sm ${compact ? 'text-[10px]' : 'text-sm'}`}>
            <div className={`${compact ? 'p-5' : 'p-8'} mx-auto min-h-[720px] max-w-4xl border`}>
                <div className={`flex gap-6 border-b pb-6 ${headerLayoutClass}`}>
                    <div className={`space-y-2 ${companyInfoClass}`}>
                        {config.header.showLogo && logo && <img src={getImagePath(String(logo))} alt="Logo" className={`${logoClass} max-h-16 max-w-40 object-contain`} />}
                        {config.header.showCompanyName && <h2 className="text-xl font-bold">{document.company.name}</h2>}
                        {config.header.showCompanyAddress && <p className="whitespace-pre-line text-slate-600">{[document.company.address, document.company.city, document.company.country].filter(Boolean).join('\n')}</p>}
                        {config.header.showContactDetails && <p className="text-slate-600">{[document.company.phone, document.company.email].filter(Boolean).join(' | ')}</p>}
                    </div>
                    <div className={`${documentBadgeClass} min-w-52 rounded-lg p-4 text-white`} style={{ backgroundColor: color }}>
                        <div className="text-2xl font-bold">{title}</div>
                        {config.documentDetails.showDocumentNumber && <div>#{document.number}</div>}
                        {config.documentDetails.showDocumentDate && <div>Date: {document.date}</div>}
                        {config.documentDetails.showDueDate && <div>Valid Until: {document.due_date}</div>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 py-6 md:grid-cols-2">
                    <div>
                        <div className="mb-2 font-semibold" style={{ color }}>Bill To</div>
                        <div className="font-medium">{document.customer.name}</div>
                        {config.customerBlock.showContactPerson && <div>{document.customer.contact_person}</div>}
                        <div>{document.customer.email}</div>
                        {config.customerBlock.showBillingAddress && document.customer.billing_address?.map((line) => <div key={line}>{line}</div>)}
                    </div>
                    {config.customerBlock.showShippingAddress && (
                        <div>
                            <div className="mb-2 font-semibold" style={{ color }}>Ship To</div>
                            {document.customer.shipping_address?.map((line) => <div key={line}>{line}</div>)}
                        </div>
                    )}
                </div>

                <table className="w-full border-collapse">
                    <thead>
                        <tr style={{ backgroundColor: color }} className="text-white">
                            {columns.map((column) => (
                                <th key={column} className="px-3 py-2 text-left">{labels[column] || column}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {document.items.map((item, index) => (
                            <tr key={index} className="border-b">
                                {columns.map((column) => (
                                    <td key={column} className="px-3 py-3 align-top whitespace-pre-wrap [overflow-wrap:anywhere]">
                                        {column === 'discount'
                                            ? Number(item.discount) > 0 ? item.discount_type === 'fixed' ? money(Number(item.discount)) : `${item.discount_percentage}%` : '-'
                                            : column === 'tax'
                                            ? hasTax(item) ? money(Number(item.tax)) : '-'
                                            : ['rate', 'total'].includes(column) ? money(Number(item[column])) : item[column]}
                                        {column === 'item' && !columns.includes('description') && item.description && (
                                            <div className="mt-1 whitespace-pre-wrap font-normal">{item.description}</div>
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-6 flex justify-end">
                    <div className="w-72 space-y-2 rounded-lg bg-slate-50 p-4">
                        {config.totals.showSubtotal && <TotalRow label="Subtotal" value={money(document.totals.subtotal)} />}
                        {config.totals.showDiscount && <TotalRow label="Discount" value={`-${money(Number(document.totals.discount || 0))}`} />}
                        {config.totals.showTax && <TotalRow label="Tax" value={hasAnyTax ? money(document.totals.tax) : '-'} />}
                        {config.totals.showGrandTotal && <div className="flex justify-between border-t pt-2 text-lg font-bold"><span>Grand Total</span><span>{money(document.totals.grand_total)}</span></div>}
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div className="space-y-4">
                        {config.footer.showNotes && template.notes && <Section title="Notes" content={template.notes} />}
                        {config.footer.showTerms && template.terms && <Section title="Terms and Conditions" content={template.terms} />}
                        {config.footer.showBankDetails && template.bank_details && <Section title="Bank Details" content={template.bank_details} />}
                    </div>
                    {config.footer.showSignature && (
                        <div className="flex items-end justify-end">
                            <div className="w-56 text-center">
                                {signature && <img src={getImagePath(String(signature))} alt="Signature" className="mx-auto mb-2 max-h-16 max-w-40 object-contain" />}
                                <div className="border-t pt-2">{template.signature_text || 'Authorized Signature'}</div>
                            </div>
                        </div>
                    )}
                </div>

                {config.footer.footerText && <div className="mt-8 border-t pt-4 text-center text-slate-500">{config.footer.footerText}</div>}
            </div>
        </div>
    );
}

function PaymentTemplatePreview({ template, document, compact, logo, signature }: { template: Partial<DocumentTemplate> & { config_json: DocumentTemplateConfig }; document: TemplateSampleDocument; compact: boolean; logo?: string | null; signature?: string | null }) {
    const config = template.config_json;
    const amount = document.totals.grand_total || 600;

    return (
        <div className={`bg-white font-serif text-slate-900 shadow-sm ${compact ? 'text-[10px]' : 'text-sm'}`}>
            <div className={`${compact ? 'p-6' : 'p-10'} relative mx-auto min-h-[720px] max-w-4xl border`}>
                <div className="absolute left-0 top-0 h-9 w-9 bg-[#171918] [clip-path:polygon(0_0,100%_0,0_100%)]" />
                <div className="flex items-start justify-between gap-8 border-b pb-10">
                    <div className="pt-12">
                        <h1 className="text-2xl font-bold uppercase tracking-wide">PAYMENT</h1>
                        {config.documentDetails.showDocumentNumber && <p className="mt-2 text-slate-500">{document.number}</p>}
                    </div>
                    {config.header.showLogo && logo && <img src={getImagePath(String(logo))} alt="Logo" className="max-h-24 max-w-56 object-contain" />}
                </div>
                <div className="grid grid-cols-1 gap-10 border-b py-12 md:grid-cols-[1fr_1.1fr]">
                    <div className="space-y-8">
                        <div>
                            <p className="font-semibold text-slate-500">Amount Received</p>
                            <p className="mt-4 text-5xl font-bold tracking-tight">{money(amount)}</p>
                        </div>
                        <div>
                            <p className="font-semibold text-slate-500">Received From</p>
                            <p className="mt-4 font-semibold text-slate-900">{document.customer.name}</p>
                        </div>
                    </div>
                    <div className="border-l pl-10">
                        <PaymentDetail label="Payment Date" value={document.date} />
                        <PaymentDetail label="Reference Number" value={document.reference_number || '-'} />
                        <PaymentDetail label="Payment Mode" value={document.payment_mode || 'Bank Transfer'} />
                        <PaymentDetail label="Bank Account" value={document.bank_account || '-'} />
                    </div>
                </div>
                <div className="mt-20 flex justify-end">
                    {config.footer.showSignature && <PaymentSignature signature={signature} text={template.signature_text || 'Authorized Signature'} />}
                </div>
                {config.footer.footerText && <div className="mt-12 border-t pt-4 text-center text-slate-500">{config.footer.footerText}</div>}
            </div>
        </div>
    );
}

function PaymentDetail({ label, value }: { label: string; value: string }) {
    return <div className="mb-6 grid grid-cols-2 gap-6"><span className="text-slate-500">{label}</span><span className="font-bold">{value}</span></div>;
}

function PaymentSignature({ signature, text }: { signature?: string | null; text: string }) {
    return <div className="w-56 text-center">{signature && <img src={getImagePath(String(signature))} alt="Signature" className="mx-auto mb-2 max-h-16 max-w-40 object-contain" />}<div className="border-t pt-2">{text}</div></div>;
}

function ReferenceQuotationTemplate({
    template,
    document,
    compact,
}: {
    template: Partial<DocumentTemplate> & { config_json: DocumentTemplateConfig };
    document: TemplateSampleDocument;
    compact: boolean;
}) {
    const [logoFailed, setLogoFailed] = useState(false);
    const config = template.config_json;
    const logo = template.logo_url || document.company.logo;
    const watermark = template.watermark_url;
    const address = [
        document.company.address,
        document.company.city,
        document.company.state,
        document.company.postal_code,
        document.company.country,
    ].filter(Boolean).join(', ');
    const configuredColumns = [...config.itemsTable.columns].filter((column) => column !== 'tax' && column !== 'discount');
    const columns = configuredColumns.includes('item')
        ? configuredColumns.filter((column) => column !== 'description')
        : configuredColumns;
    const visibleColumns = columns.length ? columns : ['item', 'quantity', 'rate', 'total'];
    const pageStyle = compact ? { aspectRatio: '210 / 297' } : { minHeight: '297mm' };

    return (
        <div className={`bg-white text-[#303030] shadow-sm ${compact ? 'text-[9px]' : 'text-[13px]'}`}>
            <div className={`relative mx-auto flex w-[210mm] max-w-full flex-col border bg-white ${compact ? 'p-5' : 'px-10 py-10'}`} style={pageStyle}>
                <div className="relative z-10 flex items-start justify-between gap-8">
                    <div className="min-h-20 max-w-[42%]">
                        {logo && !logoFailed && <img src={getImagePath(String(logo))} alt={document.company.name || 'Company logo'} className="max-h-20 max-w-52 object-contain object-left" onError={() => setLogoFailed(true)} />}
                        {(!logo || logoFailed) && <div className="text-2xl font-bold uppercase">{document.company.name}</div>}
                    </div>
                    <div className="min-w-[36%] text-right">
                        <h1 className={`${compact ? 'text-3xl' : 'text-5xl'} font-normal leading-none text-black`}>Quotation</h1>
                        {config.documentDetails.showDocumentNumber && <div className="mt-3 font-bold"># {document.number}</div>}
                    </div>
                </div>

                <div className="relative z-10 mt-14 grid grid-cols-[1fr_44%] gap-10">
                    <div>
                        <div>Bill To</div>
                        <div className="font-bold">{document.customer.name || '-'}</div>
                        {config.customerBlock.showBillingAddress && document.customer.billing_address?.map((line) => <div key={line}>{line}</div>)}
                    </div>
                    <dl className="grid grid-cols-[1fr_auto] gap-x-7 gap-y-4 text-right">
                        {config.documentDetails.showDocumentDate && <><dt>Estimate Date :</dt><dd>{documentDate(document.date)}</dd></>}
                        {config.documentDetails.showDueDate && <><dt>Expiry Date :</dt><dd>{documentDate(document.due_date)}</dd></>}
                    </dl>
                </div>

                {document.subject && (
                    <div className="relative z-10 mt-7 max-w-[70%]">
                        <div>Subject :</div>
                        <div className="mt-2 whitespace-pre-wrap">{document.subject}</div>
                    </div>
                )}

                <table className="relative z-10 mt-7 w-full table-fixed border-collapse">
                    <thead>
                        <tr className="bg-[#383a36] text-white">
                            <th className="w-[5%] px-3 py-3 text-center font-normal">#</th>
                            {visibleColumns.map((column) => (
                                <th key={column} className={`${column === 'item' || column === 'description' ? 'text-left' : 'text-right'} px-3 py-3 font-normal`}>
                                    {column === 'item' ? 'Item & Description' : labels[column] || column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {document.items.map((item, index) => (
                            <tr key={index} className="border-b border-[#9f9f9f]">
                                <td className="px-3 py-4 text-center align-top">{index + 1}</td>
                                {visibleColumns.map((column) => (
                                    <td key={column} className={`${column === 'item' || column === 'description' ? 'text-left' : 'text-right'} px-3 py-4 align-top`}>
                                        {column === 'item' ? <><div>{item.item}</div>{item.description && <div className="mt-3 whitespace-pre-wrap leading-relaxed">{item.description}</div>}</>
                                            : column === 'description' ? <div className="whitespace-pre-wrap">{item.description || '-'}</div>
                                            : ['rate', 'total'].includes(column) ? money(Number(item[column])) : item[column]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="relative z-10 ml-auto w-[50%]">
                    {config.totals.showSubtotal && <InvoiceTotalRow label="Sub Total" value={money(document.totals.subtotal)} />}
                    {config.totals.showDiscount && Number(document.totals.discount) > 0 && <InvoiceTotalRow label="Discount" value={`-${money(document.totals.discount)}`} />}
                    {config.totals.showTax && Number(document.totals.tax) > 0 && <InvoiceTotalRow label="Tax" value={money(document.totals.tax)} />}
                    {config.totals.showGrandTotal && <InvoiceTotalRow label="Total" value={money(document.totals.grand_total)} bold shaded />}
                </div>

                <div className="relative z-10 mt-12 max-w-[68%] space-y-5">
                    {config.footer.showBankDetails && template.bank_details && <Section title="Bank Details" content={template.bank_details} />}
                    {config.footer.showNotes && (document.notes || template.notes) && <Section title="Notes" content={document.notes || template.notes || ''} />}
                    {config.footer.showTerms && template.terms && <Section title="Terms and Conditions" content={template.terms} />}
                </div>

                <div className="mt-auto" />
                {config.footer.showSignature && (
                    <div className="relative z-10 mb-2 mt-6 flex break-inside-avoid gap-10">
                        <SignatureLine label={template.signature_text || document.company.name || 'Authorized Signature'} image={template.signature_url} />
                        <SignatureLine label="Client Signature" />
                    </div>
                )}

                {watermark && <img src={getImagePath(String(watermark))} aria-hidden="true" className="pointer-events-none absolute left-1/2 top-1/2 z-0 aspect-square w-[64%] -translate-x-1/2 -translate-y-1/2 object-contain opacity-[0.045] grayscale" />}
                <div className="relative z-10 mt-3 break-inside-avoid border-t pt-3 text-center text-xs leading-relaxed">
                    <FooterContact config={config} company={document.company} fallbackAddress={address} />
                    {config.footer.footerText && <div>{config.footer.footerText}</div>}
                </div>
            </div>
        </div>
    );
}

function ReferenceInvoiceTemplate({
    template,
    document,
    compact,
}: {
    template: Partial<DocumentTemplate> & { config_json: DocumentTemplateConfig };
    document: TemplateSampleDocument;
    compact: boolean;
}) {
    const [logoFailed, setLogoFailed] = useState(false);
    const config = template.config_json;
    const logo = template.logo_url || document.company.logo;
    const watermark = template.watermark_url;
    const balanceDue = Number(document.balance_due ?? document.totals.grand_total);
    const address = [
        document.company.address,
        document.company.city,
        document.company.state,
        document.company.postal_code,
        document.company.country,
    ].filter(Boolean).join(', ');
    const configuredColumns = [...config.itemsTable.columns].filter((column) => column !== 'tax' && column !== 'discount');
    const columns = configuredColumns.includes('item')
        ? configuredColumns.filter((column) => column !== 'description')
        : configuredColumns;
    const visibleColumns = columns.length ? columns : ['item', 'quantity', 'rate', 'total'];
    const pageStyle = compact ? { aspectRatio: '210 / 297' } : { minHeight: '297mm' };

    return (
        <div className={`bg-white text-[#303030] shadow-sm ${compact ? 'text-[9px]' : 'text-[13px]'}`}>
            <div className={`relative mx-auto flex w-[210mm] max-w-full flex-col border bg-white ${compact ? 'p-5' : 'px-10 py-12'}`} style={pageStyle}>
                <div className="relative z-10 flex items-start justify-between gap-8">
                    <div className="min-h-24 max-w-[42%]">
                        {logo && !logoFailed && <img src={getImagePath(String(logo))} alt={document.company.name || 'Company logo'} className="max-h-20 max-w-52 object-contain object-left" onError={() => setLogoFailed(true)} />}
                        {(!logo || logoFailed) && <div className="text-2xl font-bold uppercase">{document.company.name}</div>}
                    </div>
                    <div className="min-w-[36%] text-right">
                        <h1 className={`${compact ? 'text-3xl' : 'text-5xl'} font-normal leading-none text-black`}>Invoice</h1>
                        {config.documentDetails.showDocumentNumber && <div className="mt-3 text-base font-bold"># {document.number}</div>}
                        <div className="mt-6 font-bold">Balance Due</div>
                        <div className={`${compact ? 'text-lg' : 'text-2xl'} font-bold text-black`}>{money(balanceDue)}</div>
                    </div>
                </div>

                <div className="relative z-10 mt-12 grid grid-cols-[1fr_44%] gap-10">
                    <div className="self-end">
                        <div className="text-base">Bill To</div>
                        <div className="text-base font-bold">{document.customer.name || '-'}</div>
                        {config.customerBlock.showBillingAddress && document.customer.billing_address?.map((line) => <div key={line}>{line}</div>)}
                    </div>
                    <dl className="grid grid-cols-[1fr_auto] gap-x-7 gap-y-4 text-right text-base">
                        {config.documentDetails.showDocumentDate && <><dt>Invoice Date :</dt><dd>{documentDate(document.date)}</dd></>}
                        <dt>Terms :</dt><dd>{document.payment_terms || '-'}</dd>
                        {config.documentDetails.showDueDate && <><dt>Due Date :</dt><dd>{documentDate(document.due_date)}</dd></>}
                    </dl>
                </div>

                {document.subject && (
                    <div className="relative z-10 mt-7 max-w-[70%] text-base">
                        <div>Subject :</div>
                        <div className="mt-2 whitespace-pre-wrap">{document.subject}</div>
                    </div>
                )}

                <table className="relative z-10 mt-7 w-full table-fixed border-collapse text-base">
                    <thead>
                        <tr className="bg-[#383a36] text-white">
                            <th className="w-[5%] px-3 py-3 text-center font-normal">#</th>
                            {visibleColumns.map((column) => (
                                <th key={column} className={`${column === 'item' || column === 'description' ? 'text-left' : 'text-right'} px-3 py-3 font-normal`}>
                                    {column === 'item' ? 'Item & Description' : labels[column] || column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {document.items.map((item, index) => (
                            <tr key={index} className="border-b border-[#b8b8b8]">
                                <td className="px-3 py-4 text-center align-top">{index + 1}</td>
                                {visibleColumns.map((column) => (
                                    <td key={column} className={`${column === 'item' || column === 'description' ? 'text-left' : 'text-right'} px-3 py-4 align-top`}>
                                        {column === 'item' ? <><div>{item.item}</div>{item.description && <div className="mt-1 whitespace-pre-wrap text-sm">{item.description}</div>}</>
                                            : column === 'description' ? <div className="whitespace-pre-wrap">{item.description || '-'}</div>
                                            : ['rate', 'total'].includes(column) ? money(Number(item[column])) : item[column]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="relative z-10 ml-auto w-[50%] text-base">
                    {config.totals.showSubtotal && <InvoiceTotalRow label="Sub Total" value={money(document.totals.subtotal)} />}
                    {config.totals.showDiscount && Number(document.totals.discount) > 0 && <InvoiceTotalRow label="Discount" value={`-${money(document.totals.discount)}`} />}
                    {config.totals.showTax && Number(document.totals.tax) > 0 && <InvoiceTotalRow label="Tax" value={money(document.totals.tax)} />}
                    {config.totals.showGrandTotal && <InvoiceTotalRow label="Total" value={money(document.totals.grand_total)} bold />}
                    <InvoiceTotalRow label="Balance Due" value={money(balanceDue)} bold shaded />
                </div>

                <div className="relative z-10 mt-12 max-w-[68%] space-y-5">
                    {config.footer.showBankDetails && template.bank_details && <Section title="Bank Details" content={template.bank_details} />}
                    {config.footer.showNotes && (document.notes || template.notes) && <Section title="Notes" content={document.notes || template.notes || ''} />}
                    {config.footer.showTerms && template.terms && <Section title="Terms and Conditions" content={template.terms} />}
                </div>

                <div className="mt-auto" />
                {config.footer.showSignature && (
                    <div className="relative z-10 mb-2 mt-6 flex break-inside-avoid gap-10 text-base">
                        <SignatureLine label={template.signature_text || document.company.name || 'Authorized Signature'} image={template.signature_url} />
                        <SignatureLine label="Client Signature" />
                    </div>
                )}

                {watermark && <img src={getImagePath(String(watermark))} aria-hidden="true" className="pointer-events-none absolute left-1/2 top-1/2 z-0 aspect-square w-[64%] -translate-x-1/2 -translate-y-1/2 object-contain opacity-[0.045] grayscale" />}
                <div className="relative z-10 mt-3 break-inside-avoid border-t pt-3 text-center text-xs leading-relaxed">
                    <FooterContact config={config} company={document.company} fallbackAddress={address} />
                    {config.footer.footerText && <div>{config.footer.footerText}</div>}
                </div>
            </div>
        </div>
    );
}

function InvoiceTotalRow({ label, value, bold = false, shaded = false }: { label: string; value: string; bold?: boolean; shaded?: boolean }) {
    return <div className={`grid grid-cols-2 px-4 py-3 text-right ${bold ? 'font-bold text-black' : ''} ${shaded ? 'bg-[#f3f3f2]' : ''}`}><span>{label}</span><span>{value}</span></div>;
}

function SignatureLine({ label, image }: { label: string; image?: string | null }) {
    return <div className="w-52"><div className="flex h-14 items-end">{image && <img src={getImagePath(String(image))} alt="Signature" className="max-h-14 max-w-40 object-contain" />}</div><div>{label}</div></div>;
}

function FooterContact({
    config,
    company,
    fallbackAddress,
}: {
    config: DocumentTemplateConfig;
    company: TemplateSampleDocument['company'];
    fallbackAddress: string;
}) {
    const phone = config.footer.contactPhone || company.phone;
    const address = config.footer.contactAddress || fallbackAddress;
    const email = config.footer.contactEmail || company.email;
    const website = config.footer.contactWebsite;

    return (
        <div className="space-y-1.5">
            <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                {phone && <FooterDetail icon={Phone} text={phone} />}
                {address && <FooterDetail icon={MapPin} text={address} />}
            </div>
            <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                <span className="rounded bg-[#e9e9e7] px-2 py-0.5">C.R. No. 156793</span>
                {email && <FooterDetail icon={Mail} text={email} />}
                {website && <FooterDetail icon={Globe2} text={website} />}
            </div>
        </div>
    );
}

function FooterDetail({ icon: Icon, text }: { icon: typeof Phone; text: string }) {
    return <span className="inline-flex items-center gap-1.5"><Icon className="h-3.5 w-3.5" strokeWidth={1.5} /><span>{text}</span></span>;
}

function TotalRow({ label, value }: { label: string; value: string }) {
    return <div className="flex justify-between"><span>{label}</span><span>{value}</span></div>;
}

function Section({ title, content }: { title: string; content: string }) {
    return <div><div className="mb-1 font-semibold">{title}</div><div className="whitespace-pre-line text-slate-600">{content}</div></div>;
}

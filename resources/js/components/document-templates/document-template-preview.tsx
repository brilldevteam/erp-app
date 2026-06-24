import { DocumentTemplate, DocumentTemplateConfig, TemplateSampleDocument } from '@/types/document-template';
import { getImagePath } from '@/utils/helpers';

const labels: Record<string, string> = {
    item: 'Item',
    description: 'Description',
    quantity: 'Qty',
    rate: 'Rate',
    tax: 'Tax',
    total: 'Total',
};

const money = (value: number) => `$${Number(value || 0).toFixed(2)}`;
const hasTax = (item: Record<string, any>) => Boolean(item.has_tax ?? Number(item.tax) > 0);

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
    const hasAnyTax = document.items.some(hasTax);

    return (
        <div className={`bg-white text-slate-900 shadow-sm ${compact ? 'text-[10px]' : 'text-sm'}`}>
            <div className={`${compact ? 'p-5' : 'p-8'} mx-auto min-h-[720px] max-w-4xl border`}>
                <div className={`flex gap-6 border-b pb-6 ${alignment === 'right' ? 'flex-row-reverse text-right' : alignment === 'center' ? 'flex-col items-center text-center' : 'items-start justify-between'}`}>
                    <div className="space-y-2">
                        {config.header.showLogo && logo && <img src={getImagePath(String(logo))} alt="Logo" className="max-h-16 max-w-40 object-contain" />}
                        {config.header.showCompanyName && <h2 className="text-xl font-bold">{document.company.name}</h2>}
                        {config.header.showCompanyAddress && <p className="whitespace-pre-line text-slate-600">{[document.company.address, document.company.city, document.company.country].filter(Boolean).join('\n')}</p>}
                        {config.header.showContactDetails && <p className="text-slate-600">{[document.company.phone, document.company.email].filter(Boolean).join(' | ')}</p>}
                    </div>
                    <div className="ml-auto min-w-52 rounded-lg p-4 text-white" style={{ backgroundColor: color }}>
                        <div className="text-2xl font-bold">{title}</div>
                        {config.documentDetails.showDocumentNumber && <div>#{document.number}</div>}
                        {config.documentDetails.showDocumentDate && <div>Date: {document.date}</div>}
                        {config.documentDetails.showDueDate && <div>{document.type === 'invoice' ? 'Due' : 'Valid Until'}: {document.due_date}</div>}
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
                            {config.itemsTable.columns.map((column) => (
                                <th key={column} className="px-3 py-2 text-left">{labels[column] || column}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {document.items.map((item, index) => (
                            <tr key={index} className="border-b">
                                {config.itemsTable.columns.map((column) => (
                                    <td key={column} className="px-3 py-3 align-top">
                                        {column === 'tax'
                                            ? hasTax(item) ? money(Number(item.tax)) : '-'
                                            : ['rate', 'total'].includes(column) ? money(Number(item[column])) : item[column]}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <div className="mt-6 flex justify-end">
                    <div className="w-72 space-y-2 rounded-lg bg-slate-50 p-4">
                        {config.totals.showSubtotal && <TotalRow label="Subtotal" value={money(document.totals.subtotal)} />}
                        {config.totals.showDiscount && <TotalRow label="Discount" value={`-${money(document.totals.discount)}`} />}
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

function TotalRow({ label, value }: { label: string; value: string }) {
    return <div className="flex justify-between"><span>{label}</span><span>{value}</span></div>;
}

function Section({ title, content }: { title: string; content: string }) {
    return <div><div className="mb-1 font-semibold">{title}</div><div className="whitespace-pre-line text-slate-600">{content}</div></div>;
}

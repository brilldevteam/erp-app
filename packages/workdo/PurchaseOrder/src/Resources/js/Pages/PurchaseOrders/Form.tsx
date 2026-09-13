import { useMemo } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

export const emptyLine = { product_id: '', item_name: '', description: '', unit: '', quantity: '1', unit_price: '0.00', discount_type: 'percentage', discount_value: '0.0', taxes: [] };

export default function Form({ data, setData, errors, vendors, warehouses, products, taxes, defaultCurrency, processing, onSubmit, submitLabel }: any) {
    const update = (index: number, key: string, value: any) => {
        const items = [...data.items];
        items[index] = { ...items[index], [key]: value };
        setData('items', items);
    };
    const selectProduct = (index: number, value: string) => {
        const product = products.find((item: any) => String(item.id) === value);
        if (!product) return;
        const taxIds = (product.tax_ids || []).map(String);
        const tax = taxes.find((item: any) => taxIds.includes(String(item.id)));
        const items = [...data.items];
        items[index] = { ...items[index], product_id: product.id, item_name: product.name, description: product.description || '', unit: product.unit || '', unit_price: Number(product.purchase_price || 0).toFixed(2), taxes: tax ? [{ tax_id: tax.id, tax_name: tax.tax_name, tax_rate: tax.rate }] : [] };
        setData('items', items);
    };
    const total = useMemo(() => {
        const roundMoney = (value: number) => Math.round((value + Number.EPSILON) * 100) / 100;
        let subtotal = 0;
        let lineDiscountTotal = 0;
        let taxTotal = 0;

        data.items.forEach((item: any) => {
            const gross = roundMoney(Number(item.quantity || 0) * Number(item.unit_price || 0));
            const discountValue = Math.max(0, Number(item.discount_value || 0));
            const discount = item.discount_type === 'fixed'
                ? Math.min(gross, discountValue)
                : roundMoney(gross * Math.min(100, discountValue) / 100);
            const taxable = gross - discount;
            const lineTax = (item.taxes || []).reduce(
                (sum: number, tax: any) => sum + roundMoney(taxable * Number(tax.tax_rate || 0) / 100),
                0,
            );

            subtotal += gross;
            lineDiscountTotal += discount;
            taxTotal += lineTax;
        });

        const discountedLines = subtotal - lineDiscountTotal;
        const documentDiscountValue = Math.max(0, Number(data.document_discount_value || 0));
        const documentDiscount = data.document_discount_type === 'percentage'
            ? roundMoney(discountedLines * Math.min(100, documentDiscountValue) / 100)
            : Math.min(discountedLines, documentDiscountValue);

        return roundMoney(
            discountedLines
            - documentDiscount
            + taxTotal
            + Number(data.shipping_amount || 0)
            + Number(data.adjustment_amount || 0),
        );
    }, [data]);

    return <form onSubmit={onSubmit} className="space-y-6">
        <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div><Label required>Vendor</Label><Select value={String(data.vendor_id || '')} onValueChange={value => setData('vendor_id', value)}><SelectTrigger><SelectValue placeholder="Select vendor" /></SelectTrigger><SelectContent>{vendors.map((vendor: any) => <SelectItem key={vendor.id} value={String(vendor.id)}>{vendor.name}</SelectItem>)}</SelectContent></Select>{errors.vendor_id && <p className="mt-1 text-sm text-red-600">{errors.vendor_id}</p>}</div>
            <div><Label required>Order Date</Label><DatePicker value={data.order_date} onChange={value => setData('order_date', value)} required />{errors.order_date && <p className="mt-1 text-sm text-red-600">{errors.order_date}</p>}</div>
            <div><Label>Expected Delivery Date</Label><DatePicker value={data.expected_delivery_date || ''} onChange={value => setData('expected_delivery_date', value)} placeholder="Select date" />{errors.expected_delivery_date && <p className="mt-1 text-sm text-red-600">{errors.expected_delivery_date}</p>}</div>
            <div><Label>Warehouse</Label><Select value={String(data.warehouse_id || 'none')} onValueChange={value => setData('warehouse_id', value === 'none' ? '' : value)}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="none">No warehouse</SelectItem>{warehouses.map((warehouse: any) => <SelectItem key={warehouse.id} value={String(warehouse.id)}>{warehouse.name}</SelectItem>)}</SelectContent></Select></div>
            <div><Label>Vendor Reference</Label><Input value={data.vendor_reference || ''} onChange={event => setData('vendor_reference', event.target.value)} /></div>
            <div><Label>Vendor Quotation No.</Label><Input value={data.vendor_quotation_number || ''} onChange={event => setData('vendor_quotation_number', event.target.value)} /></div>
        </section>

        <section className="space-y-3">
            <div className="flex items-center justify-between"><h2 className="text-lg font-semibold">Items</h2><Button type="button" variant="outline" onClick={() => setData('items', [...data.items, { ...emptyLine }])}><Plus className="mr-2 h-4 w-4" />Add Line</Button></div>
            {errors.items && <p className="text-sm text-red-600">{errors.items}</p>}
            {data.items.map((line: any, index: number) => <div key={index} className="grid grid-cols-1 gap-3 border-b pb-4 md:grid-cols-2 xl:grid-cols-[2fr_2fr_0.7fr_0.8fr_1fr_1.7fr_1.3fr_auto]">
                <div><Label>Product / Service</Label><Select value={String(line.product_id || 'custom')} onValueChange={value => value === 'custom' ? update(index, 'product_id', '') : selectProduct(index, value)}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="custom">Custom item</SelectItem>{products.map((product: any) => <SelectItem key={product.id} value={String(product.id)}>{product.name}{product.sku ? ` - ${product.sku}` : ''}</SelectItem>)}</SelectContent></Select></div>
                <div><Label required>Item Name</Label><Input value={line.item_name} onChange={event => update(index, 'item_name', event.target.value)} /></div>
                <div><Label>Unit</Label><Input value={line.unit || ''} onChange={event => update(index, 'unit', event.target.value)} /></div>
                <div><Label required>Qty</Label><Input type="number" min="1" step="1" value={line.quantity} onChange={event => update(index, 'quantity', event.target.value)} onBlur={event => update(index, 'quantity', String(Math.max(1, Math.round(Number(event.target.value) || 1))))} /></div>
                <div><Label required>Unit Price</Label><Input type="number" min="0" step="0.01" value={line.unit_price} onChange={event => update(index, 'unit_price', event.target.value)} onBlur={event => update(index, 'unit_price', Math.max(0, Number(event.target.value) || 0).toFixed(2))} /></div>
                <DiscountControl value={line.discount_value} type={line.discount_type} currency={defaultCurrency} onValue={value => update(index, 'discount_value', value)} onType={value => { const items = [...data.items]; items[index] = { ...items[index], discount_type: value, discount_value: 0 }; setData('items', items); }} />
                <div><Label>Tax</Label><Select value={line.taxes?.[0]?.tax_id ? String(line.taxes[0].tax_id) : 'none'} onValueChange={value => { const tax = taxes.find((item: any) => String(item.id) === value); update(index, 'taxes', value === 'none' || !tax ? [] : [{ tax_id: tax.id, tax_name: tax.tax_name, tax_rate: tax.rate }]); }}><SelectTrigger><SelectValue placeholder="_" /></SelectTrigger><SelectContent><SelectItem value="none">_</SelectItem>{taxes.filter((tax: any) => !(Number(tax.rate) === 0 && tax.tax_name.trim().toLowerCase() === 'no tax')).map((tax: any) => <SelectItem key={tax.id} value={String(tax.id)}>{tax.tax_name} ({tax.rate}%)</SelectItem>)}</SelectContent></Select></div>
                <Button type="button" variant="ghost" size="icon" className="mt-6 text-red-600" disabled={data.items.length === 1} onClick={() => setData('items', data.items.filter((_: any, itemIndex: number) => itemIndex !== index))}><Trash2 className="h-4 w-4" /></Button>
                <Textarea className="md:col-span-2 xl:col-span-8" placeholder="Description" value={line.description || ''} onChange={event => update(index, 'description', event.target.value)} />
            </div>)}
        </section>

        <section className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <DiscountControl label="Document Discount" value={data.document_discount_value} type={data.document_discount_type} currency={defaultCurrency} onValue={value => setData('document_discount_value', value)} onType={value => setData((current: any) => ({ ...current, document_discount_type: value, document_discount_value: 0 }))} />
            <div><Label>Shipping Charges ({defaultCurrency})</Label><Input type="number" min="0" step="0.01" value={data.shipping_amount} onChange={event => setData('shipping_amount', event.target.value)} /></div>
            <div><Label>Adjustment ({defaultCurrency})</Label><Input type="number" step="0.01" value={data.adjustment_amount} onChange={event => setData('adjustment_amount', event.target.value)} /></div>
            <div className="self-end text-right text-xl font-semibold">Estimated Total: {total.toFixed(2)} {defaultCurrency}</div>
        </section>
        <div className="grid gap-4 md:grid-cols-2"><div><Label>Notes</Label><Textarea value={data.notes || ''} onChange={event => setData('notes', event.target.value)} /></div><div><Label>Terms</Label><Textarea value={data.terms || ''} onChange={event => setData('terms', event.target.value)} /></div></div>
        <div><Label>Attachments</Label><Input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.csv,.txt" onChange={event => setData('attachments', Array.from(event.target.files || []))} />{errors.attachments && <p className="mt-1 text-sm text-red-600">{errors.attachments}</p>}<p className="mt-1 text-xs text-muted-foreground">PDF, images, Word, Excel, CSV, or text files. Maximum 10 MB per file.</p></div>
        <div className="flex justify-end"><Button disabled={processing}>{submitLabel}</Button></div>
    </form>;
}

function DiscountControl({ label = 'Discount', value, type, currency, onValue, onType }: any) {
    return <div><Label>{label}</Label><div className="grid grid-cols-[minmax(100px,1fr)_5rem] gap-2"><Input type="number" min="0" max={type === 'percentage' ? 100 : undefined} step="0.1" value={value} onChange={event => onValue(event.target.value)} onBlur={event => onValue(Math.max(0, Number(event.target.value) || 0).toFixed(1))} className="min-w-[100px]" /><Select value={type || 'fixed'} onValueChange={onType}><SelectTrigger className="w-20"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="percentage">%</SelectItem><SelectItem value="fixed">{currency}</SelectItem></SelectContent></Select></div></div>;
}

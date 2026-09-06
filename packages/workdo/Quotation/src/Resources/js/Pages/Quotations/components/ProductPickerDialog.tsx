import React, { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/utils/helpers';
import { Loader2, PackagePlus, Plus, Search } from 'lucide-react';

export interface QuotationProduct {
    id: number;
    name: string;
    sku?: string;
    description?: string;
    sale_price: number;
    unit?: string;
    unit_name?: string;
    type?: string;
    category_id?: number;
    category_name?: string;
    stock_quantity?: number;
    taxes?: Array<{ id: number; tax_name: string; rate: number }>;
}

interface CatalogOption { id: number; name: string }
interface UnitOption { id: number; unit_name: string }
interface TaxOption { id: number; tax_name: string; rate: number }

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    products: QuotationProduct[];
    categories: CatalogOption[];
    units: UnitOption[];
    taxes: TaxOption[];
    warehouses: Array<{ id: number; name: string }>;
    warehouseId?: string;
    canCreateProduct: boolean;
    loading?: boolean;
    onAdd: (products: QuotationProduct[]) => void;
    onProductCreated: (product: QuotationProduct) => void;
}

const initialProduct = {
    name: '', sku: '', category_id: '', type: 'product', unit: '', sale_price: '', purchase_price: '0',
    quantity: '0', warehouse_id: '', description: '', tax_ids: [] as string[],
};

export default function ProductPickerDialog(props: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [category, setCategory] = useState('all');
    const [selected, setSelected] = useState<number[]>([]);
    const [creating, setCreating] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(initialProduct);
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});

    const filtered = useMemo(() => {
        const needle = search.trim().toLowerCase();
        return props.products.filter(product => {
            const matchesSearch = !needle || product.name.toLowerCase().includes(needle) || (product.sku || '').toLowerCase().includes(needle);
            return matchesSearch && (category === 'all' || String(product.category_id) === category);
        });
    }, [props.products, search, category]);

    const close = () => {
        props.onOpenChange(false);
        setSelected([]);
        setCreating(false);
        setSearch('');
        setCategory('all');
    };

    const addSelected = () => {
        const chosen = props.products.filter(product => selected.includes(product.id));
        if (!chosen.length) return;
        props.onAdd(chosen);
        close();
    };

    const toggleProduct = (productId: number) => {
        setSelected(current => current.includes(productId)
            ? current.filter(id => id !== productId)
            : [...current, productId]);
    };

    const saveProduct = async (event: React.FormEvent) => {
        event.preventDefault();
        setSaving(true);
        setFormErrors({});
        try {
            const payload = {
                ...form,
                warehouse_id: form.type === 'service' ? null : (props.warehouseId || form.warehouse_id || null),
                unit: form.type === 'service' ? null : form.unit,
                quantity: form.type === 'service' ? null : Number(form.quantity || 0),
                sale_price: Number(form.sale_price),
                purchase_price: Number(form.purchase_price || 0),
                category_id: Number(form.category_id),
                tax_ids: form.tax_ids.map(Number),
            };
            const response = await fetch(route('product-service.items.store'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) {
                setFormErrors(result.errors || { form: [result.message || t('Unable to create product.')] });
                return;
            }
            props.onProductCreated(result.product);
            props.onAdd([result.product]);
            setForm(initialProduct);
            close();
        } catch {
            setFormErrors({ form: [t('Unable to create product. Please try again.')] });
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={props.open} onOpenChange={props.onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <PackagePlus className="h-5 w-5" />
                        {creating ? t('Create New Product') : t('Add Products')}
                    </DialogTitle>
                </DialogHeader>

                {creating ? (
                    <form onSubmit={saveProduct} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('Name')} error={formErrors.name?.[0]}><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></Field>
                            <Field label={t('SKU')} error={formErrors.sku?.[0]}><Input value={form.sku} onChange={e => setForm({ ...form, sku: e.target.value })} required /></Field>
                            <Field label={t('Type')} error={formErrors.type?.[0]}>
                                <Select value={form.type} onValueChange={value => setForm({ ...form, type: value })}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent><SelectItem value="product">{t('Product')}</SelectItem><SelectItem value="service">{t('Service')}</SelectItem><SelectItem value="part">{t('Part')}</SelectItem></SelectContent></Select>
                            </Field>
                            <Field label={t('Category')} error={formErrors.category_id?.[0]}>
                                <Select value={form.category_id} onValueChange={value => setForm({ ...form, category_id: value })}><SelectTrigger><SelectValue placeholder={t('Select Category')} /></SelectTrigger><SelectContent>{props.categories.map(option => <SelectItem key={option.id} value={String(option.id)}>{option.name}</SelectItem>)}</SelectContent></Select>
                            </Field>
                            <Field label={t('Selling Price')} error={formErrors.sale_price?.[0]}><Input type="number" min="0" step="0.01" value={form.sale_price} onChange={e => setForm({ ...form, sale_price: e.target.value })} required /></Field>
                            <Field label={t('Purchase Price')} error={formErrors.purchase_price?.[0]}><Input type="number" min="0" step="0.01" value={form.purchase_price} onChange={e => setForm({ ...form, purchase_price: e.target.value })} required /></Field>
                            {form.type !== 'service' && <Field label={t('Unit')} error={formErrors.unit?.[0]}><Select value={form.unit} onValueChange={value => setForm({ ...form, unit: value })}><SelectTrigger><SelectValue placeholder={t('Select Unit')} /></SelectTrigger><SelectContent>{props.units.map(option => <SelectItem key={option.id} value={String(option.id)}>{option.unit_name}</SelectItem>)}</SelectContent></Select></Field>}
                            {form.type !== 'service' && <Field label={t('Opening Stock')} error={formErrors.quantity?.[0]}><Input type="number" min="0" step="1" value={form.quantity} onChange={e => setForm({ ...form, quantity: e.target.value })} /></Field>}
                        </div>
                        <Field label={t('Description')} error={formErrors.description?.[0]}><Textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} rows={3} /></Field>
                        {!!props.taxes.length && <div><Label>{t('Taxes')}</Label><div className="mt-2 flex flex-wrap gap-3">{props.taxes.map(tax => <label key={tax.id} className="flex items-center gap-2 text-sm"><Checkbox checked={form.tax_ids.includes(String(tax.id))} onCheckedChange={checked => setForm({ ...form, tax_ids: checked ? [...form.tax_ids, String(tax.id)] : form.tax_ids.filter(id => id !== String(tax.id)) })} />{tax.tax_name} ({tax.rate}%)</label>)}</div></div>}
                        {formErrors.form?.[0] && <p className="text-sm text-destructive">{formErrors.form[0]}</p>}
                        <DialogFooter><Button type="button" variant="outline" onClick={() => setCreating(false)}>{t('Back')}</Button><Button type="submit" disabled={saving}>{saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}{t('Create and Add')}</Button></DialogFooter>
                    </form>
                ) : (
                    <>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <div className="relative flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input className="pl-9" value={search} onChange={e => setSearch(e.target.value)} placeholder={t('Search by product name or SKU')} /></div>
                            <Select value={category} onValueChange={setCategory}><SelectTrigger className="sm:w-56"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="all">{t('All Categories')}</SelectItem>{props.categories.map(option => <SelectItem key={option.id} value={String(option.id)}>{option.name}</SelectItem>)}</SelectContent></Select>
                        </div>
                        <div className="max-h-[50vh] overflow-auto rounded border">
                            {props.loading ? <div className="flex items-center justify-center p-10 text-muted-foreground"><Loader2 className="mr-2 h-5 w-5 animate-spin" />{t('Loading products...')}</div> : filtered.length ? (
                                <table className="w-full min-w-[680px] text-sm"><thead className="sticky top-0 bg-muted"><tr><th className="w-12 p-3"></th><th className="p-3 text-left">{t('Product')}</th><th className="p-3 text-left">{t('SKU')}</th><th className="p-3 text-left">{t('Unit')}</th><th className="p-3 text-right">{t('Selling Price')}</th><th className="p-3 text-right">{t('Availability')}</th></tr></thead><tbody className="divide-y">{filtered.map(product => {
                                    const isSelected = selected.includes(product.id);
                                    return <tr
                                        key={product.id}
                                        role="checkbox"
                                        aria-checked={isSelected}
                                        tabIndex={0}
                                        onClick={() => toggleProduct(product.id)}
                                        onKeyDown={event => {
                                            if (event.target !== event.currentTarget) return;
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                toggleProduct(product.id);
                                            }
                                        }}
                                        className={`cursor-pointer transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${isSelected ? 'bg-primary/10' : ''}`}
                                    ><td className="p-3 text-center" onClick={event => event.stopPropagation()}><Checkbox checked={isSelected} onCheckedChange={() => toggleProduct(product.id)} aria-label={t('Select {{product}}', { product: product.name })} /></td><td className="p-3"><div className="font-medium">{product.name}</div>{product.description && <div className="max-w-md truncate text-xs text-muted-foreground">{product.description}</div>}</td><td className="p-3">{product.sku || '-'}</td><td className="p-3">{product.unit_name || product.unit || '-'}</td><td className="p-3 text-right">{formatCurrency(Number(product.sale_price || 0))}</td><td className="p-3 text-right">{product.type === 'service' ? t('Service') : `${t('Stock')}: ${product.stock_quantity ?? 0}`}</td></tr>;
                                })}</tbody></table>
                            ) : <div className="p-10 text-center text-muted-foreground"><p>{t('No products found.')}</p>{props.canCreateProduct && <Button type="button" variant="link" onClick={() => setCreating(true)}>{t('Create your first product')}</Button>}</div>}
                        </div>
                        <DialogFooter className="gap-2 sm:justify-between">
                            <div>{props.canCreateProduct && <Button type="button" variant="outline" onClick={() => setCreating(true)}><Plus className="mr-2 h-4 w-4" />{t('Create New Product')}</Button>}</div>
                            <div className="flex gap-2"><Button type="button" variant="outline" onClick={close}>{t('Cancel')}</Button><Button type="button" onClick={addSelected} disabled={!selected.length}>{t(selected.length === 1 ? 'Add 1 Product' : 'Add {{count}} Products', { count: selected.length })}</Button></div>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return <div className="space-y-1.5"><Label>{label}</Label>{children}{error && <p className="text-sm text-destructive">{error}</p>}</div>;
}

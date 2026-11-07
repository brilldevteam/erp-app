import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/utils/helpers';
import { Loader2, PackagePlus, Search } from 'lucide-react';

export interface QuotationProduct {
    id: number;
    name: string;
    sku?: string;
    description?: string;
    sale_price: number;
    purchase_price?: number;
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
    defaultProductType?: 'product' | 'service';
    catalogMode?: 'all' | 'stock' | 'service';
    priceMode?: 'sale' | 'purchase';
    requireAvailableStock?: boolean;
    canCreateProduct: boolean;
    loading?: boolean;
    onAdd: (products: QuotationProduct[]) => void;
    onProductCreated: (product: QuotationProduct) => void;
    initialView?: 'browse' | 'create';
    /** Opened from the Products or Services list: only creates the item, with no document to add it to. */
    createOnly?: boolean;
}

const initialProduct = {
    name: '', sku: '', category_id: '', type: 'product', unit: '', sale_price: '', purchase_price: '0',
    quantity: '0', warehouse_id: '', description: '', tax_ids: [] as string[],
};

export default function ProductPickerDialog(props: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState<number[]>([]);
    const [creating, setCreating] = useState(false);
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(initialProduct);
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (!props.open) return;
        if (props.initialView === 'create' && props.canCreateProduct) {
            beginCreate();
            return;
        }
        setCreating(false);
    }, [props.open, props.initialView]);

    const filtered = useMemo(() => {
        const normalize = (value: string) => value.trim().toLocaleLowerCase().replace(/\s+/g, ' ');
        const needle = normalize(search);
        return props.products.filter(product => {
            const searchableText = normalize(`${product.name || ''} ${product.sku || ''}`);
            return !needle || searchableText.includes(needle);
        });
    }, [props.products, search]);

    const close = () => {
        props.onOpenChange(false);
        setSelected([]);
        setCreating(false);
        setSearch('');
    };

    const addSelected = () => {
        const chosen = props.products.filter(product => selected.includes(product.id) && (
            props.requireAvailableStock === false || product.type === 'service' || Number(product.stock_quantity) > 0
        ));
        if (!chosen.length) return;
        props.onAdd(chosen);
        close();
    };

    const beginCreate = () => {
        const type = props.catalogMode === 'service' ? 'service' : (props.defaultProductType || 'product');
        setForm({ ...initialProduct, type, warehouse_id: props.warehouseId || '' });
        setFormErrors({});
        setCreating(true);
    };

    const toggleProduct = (productId: number) => {
        const product = props.products.find(item => item.id === productId);
        if (props.requireAvailableStock !== false && product?.type !== 'service' && Number(product?.stock_quantity) <= 0) return;
        setSelected(current => current.includes(productId)
            ? current.filter(id => id !== productId)
            : [...current, productId]);
    };

    const saveProduct = async (event: React.FormEvent) => {
        event.preventDefault();
        setSaving(true);
        setFormErrors({});
        const warehouseId = props.warehouseId || form.warehouse_id;
        if (props.catalogMode === 'stock' && !props.createOnly && form.type !== 'service' && Number(form.quantity) < 1) {
            setFormErrors({ quantity: [t('Opening stock must be at least 1 to add this product.')] });
            setSaving(false);
            return;
        }
        try {
            const payload = {
                ...form,
                warehouse_id: form.type === 'service' ? null : (warehouseId || null),
                unit: form.type === 'service' ? null : form.unit,
                quantity: form.type === 'service' ? null : Number(form.quantity || 0),
                sale_price: Number(form.sale_price),
                purchase_price: Number(form.purchase_price || 0),
                category_id: Number(form.category_id),
                tax_ids: form.tax_ids.map(Number),
            };
            const storeRoute = form.type === 'service' ? 'product-service.services.store' : 'product-service.products.store';
            const response = await fetch(route(storeRoute), {
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
            if (!result.product?.id) {
                setFormErrors({ form: [t('The product was created, but the server did not return it correctly. Please refresh and select it from the list.')] });
                return;
            }

            const createdProduct: QuotationProduct = {
                ...result.product,
                id: Number(result.product.id),
                sale_price: Number(result.product.sale_price) || 0,
                stock_quantity: result.product.stock_quantity == null
                    ? undefined
                    : Number(result.product.stock_quantity),
            };

            props.onProductCreated(createdProduct);
            if (props.createOnly) {
                setForm(initialProduct);
                close();
                return;
            }
            requestAnimationFrame(() => {
                props.onAdd([createdProduct]);
                setForm(initialProduct);
                close();
            });
        } catch {
            setFormErrors({ form: [t('Unable to create product. Please try again.')] });
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog open={props.open} onOpenChange={props.onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-4xl">
                <DialogHeader className={creating ? '!py-3' : undefined}>
                    <DialogTitle className="flex items-center gap-2">
                        <PackagePlus className="h-5 w-5" />
                        {creating ? (form.type === 'service' ? t('Create New Service') : t('Create New Product')) : props.catalogMode === 'service' ? t('Add Services') : t('Add Products')}
                    </DialogTitle>
                </DialogHeader>

                {creating ? (
                    <form onSubmit={saveProduct} className="min-h-0 space-y-3 overflow-y-auto overscroll-contain pr-1">
                        <div className="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                            <Field label={t('Name')} error={formErrors.name?.[0]}><Input value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></Field>
                            <Field label={t('SKU')} error={formErrors.sku?.[0]}>
                                <div className="flex gap-2">
                                    <Input value={form.sku} onChange={e => setForm({ ...form, sku: e.target.value })} required />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setForm({ ...form, sku: `SKU-${Date.now()}` })}
                                    >
                                        {t('Generate')}
                                    </Button>
                                </div>
                            </Field>
                            <Field label={t('Type')} error={formErrors.type?.[0]}>
                                <Select value={form.type} onValueChange={value => setForm({ ...form, type: value })} disabled={props.catalogMode === 'service'}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{props.catalogMode !== 'service' && <SelectItem value="product">{t('Product')}</SelectItem>}{props.catalogMode !== 'stock' && <SelectItem value="service">{t('Service')}</SelectItem>}{props.catalogMode !== 'service' && !props.createOnly && <SelectItem value="part">{t('Part')}</SelectItem>}</SelectContent></Select>
                            </Field>
                            <Field label={t('Category')} error={formErrors.category_id?.[0]}>
                                <Select value={form.category_id} onValueChange={value => setForm({ ...form, category_id: value })}><SelectTrigger><SelectValue placeholder={t('Select Category')} /></SelectTrigger><SelectContent>{props.categories.map(option => <SelectItem key={option.id} value={String(option.id)}>{option.name}</SelectItem>)}</SelectContent></Select>
                            </Field>
                            <Field label={t('Selling Price')} error={formErrors.sale_price?.[0]}><Input type="number" min="0" step="0.01" value={form.sale_price} onChange={e => setForm({ ...form, sale_price: e.target.value })} required /></Field>
                            <Field label={t('Purchase Price')} error={formErrors.purchase_price?.[0]}><Input type="number" min="0" step="0.01" value={form.purchase_price} onChange={e => setForm({ ...form, purchase_price: e.target.value })} required={props.priceMode === 'purchase'} /></Field>
                            {form.type !== 'service' && <Field label={t('Unit')} error={formErrors.unit?.[0]}><Select value={form.unit} onValueChange={value => setForm({ ...form, unit: value })}><SelectTrigger><SelectValue placeholder={t('Select Unit')} /></SelectTrigger><SelectContent>{props.units.map(option => <SelectItem key={option.id} value={String(option.id)}>{option.unit_name}</SelectItem>)}</SelectContent></Select></Field>}
                            {form.type !== 'service' && <Field label={t('Warehouse')} error={formErrors.warehouse_id?.[0]}><Select value={form.warehouse_id} onValueChange={value => setForm({ ...form, warehouse_id: value })} disabled={Boolean(props.warehouseId)}><SelectTrigger><SelectValue placeholder={t('Select Warehouse')} /></SelectTrigger><SelectContent>{props.warehouses.map(warehouse => <SelectItem key={warehouse.id} value={String(warehouse.id)}>{warehouse.name}</SelectItem>)}</SelectContent></Select></Field>}
                            {form.type !== 'service' && <Field label={t('Opening Stock')} error={formErrors.quantity?.[0]}><Input type="number" min={props.catalogMode === 'stock' && !props.createOnly ? 1 : 0} step="1" value={form.quantity} onChange={e => setForm({ ...form, quantity: e.target.value })} /></Field>}
                            <div className={form.type === 'service' ? 'sm:col-span-2' : undefined}><Label>{t('Tax')}</Label><Select value={form.tax_ids[0] || 'none'} onValueChange={value => setForm({ ...form, tax_ids: value === 'none' ? [] : [value] })}><SelectTrigger><SelectValue placeholder="_" /></SelectTrigger><SelectContent><SelectItem value="none">_</SelectItem>{props.taxes.filter(tax => !(Number(tax.rate) === 0 && tax.tax_name.trim().toLowerCase() === 'no tax')).map(tax => <SelectItem key={tax.id} value={String(tax.id)}>{tax.tax_name} ({tax.rate}%)</SelectItem>)}</SelectContent></Select></div>
                        </div>
                        <Field label={t('Description')} error={formErrors.description?.[0]}><Textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} rows={2} className="min-h-[60px]" /></Field>
                        {formErrors.form?.[0] && <p className="text-sm text-destructive">{formErrors.form[0]}</p>}
                        <DialogFooter className="py-3"><Button type="button" variant="outline" onClick={() => props.createOnly ? close() : setCreating(false)}>{props.createOnly ? t('Cancel') : t('Back')}</Button><Button type="submit" disabled={saving}>{saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}{props.createOnly ? t('Create') : t('Create and Add')}</Button></DialogFooter>
                    </form>
                ) : (
                    <>
                        {/* The dialog pads its direct children; this wrapper keeps that padding outside the search border. */}
                        <div className="!pb-0">
                            <div className="flex h-10 items-center gap-2 rounded-lg border border-input bg-white px-3 shadow-[0_1px_2px_rgba(15,23,42,0.03)] focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/10 dark:bg-slate-950">
                                <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <Input className="h-auto border-0 bg-transparent p-0 shadow-none focus-visible:border-0 focus-visible:ring-0 dark:bg-transparent" value={search} onChange={e => setSearch(e.target.value)} placeholder={t('Search by product name or SKU')} />
                            </div>
                        </div>
                        <div className="!pt-4"><div className="max-h-[50vh] overflow-y-auto overflow-x-hidden rounded-lg border">
                            {props.loading ? <div className="flex items-center justify-center p-10 text-muted-foreground"><Loader2 className="mr-2 h-5 w-5 animate-spin" />{t('Loading products...')}</div> : filtered.length ? (
                                <table className="w-full table-fixed text-sm"><thead className="sticky top-0 z-10 bg-muted"><tr><th className="w-12 p-3"></th><th className="p-3 text-left">{t('Product')}</th><th className="w-28 p-3 text-left">{t('SKU')}</th><th className="w-20 p-3 text-left">{t('Unit')}</th><th className="w-36 p-3 text-right">{t(props.priceMode === 'purchase' ? 'Purchase Price' : 'Selling Price')}</th><th className="w-28 p-3 text-right">{t('Availability')}</th></tr></thead><tbody className="divide-y">{filtered.map(product => {
                                    const isSelected = selected.includes(product.id);
                                    const isUnavailable = props.requireAvailableStock !== false && product.type !== 'service' && Number(product.stock_quantity) <= 0;
                                    return <tr
                                        key={product.id}
                                        role="checkbox"
                                        aria-checked={isSelected}
                                        tabIndex={isUnavailable ? -1 : 0}
                                        onClick={() => toggleProduct(product.id)}
                                        onKeyDown={event => {
                                            if (event.target !== event.currentTarget) return;
                                            if (event.key === 'Enter' || event.key === ' ') {
                                                event.preventDefault();
                                                toggleProduct(product.id);
                                            }
                                        }}
                                        className={`${isUnavailable ? 'cursor-not-allowed opacity-50' : 'cursor-pointer hover:bg-muted/60'} transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary ${isSelected ? 'bg-primary/10' : ''}`}
                                    ><td className="p-3 text-center" onClick={event => event.stopPropagation()}><Checkbox checked={isSelected} disabled={isUnavailable} onCheckedChange={() => toggleProduct(product.id)} aria-label={t('Select {{product}}', { product: product.name })} /></td><td className="p-3"><div className="break-words font-medium">{product.name}</div>{product.description && <div className="truncate text-xs text-muted-foreground">{product.description}</div>}</td><td className="break-words p-3">{product.sku || '-'}</td><td className="break-words p-3">{product.unit_name || product.unit || '-'}</td><td className="p-3 text-right">{formatCurrency(Number(props.priceMode === 'purchase' ? product.purchase_price : product.sale_price) || 0)}</td><td className="p-3 text-right">{product.type === 'service' ? t('Service') : `${t('Stock')}: ${product.stock_quantity ?? 0}`}</td></tr>;
                                })}</tbody></table>
                            ) : <div className="p-10 text-center text-muted-foreground"><p>{t('No products found.')}</p></div>}
                        </div></div>
                        <DialogFooter className="gap-2">
                            <Button type="button" variant="outline" onClick={close}>{t('Cancel')}</Button><Button type="button" onClick={addSelected} disabled={!selected.length}>{t(selected.length === 1 ? 'Add 1 Item' : 'Add {{count}} Items', { count: selected.length })}</Button>
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

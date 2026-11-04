import { useMemo, useState } from 'react';
import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export type TaxOption = { id: number; tax_name: string; rate: number };

interface Props {
    value: string;
    taxes: TaxOption[];
    onChange: (tax: TaxOption | null) => void;
    onTaxesChange: (taxes: TaxOption[]) => void;
    canManage?: boolean;
    canCreate?: boolean;
    canEdit?: boolean;
    canDelete?: boolean;
}

export default function TaxField({ value, taxes, onChange, onTaxesChange, canManage = false, canCreate = false, canEdit = false, canDelete = false }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<TaxOption | null>(null);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [rate, setRate] = useState('0');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [optimisticTaxes, setOptimisticTaxes] = useState<TaxOption[]>([]);
    const availableTaxes = useMemo(() => [
        ...taxes,
        ...optimisticTaxes.filter(optimistic => !taxes.some(tax => tax.id === optimistic.id)),
    ], [taxes, optimisticTaxes]);
    const selectedTax = availableTaxes.find(tax => String(tax.id) === value);
    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return needle ? availableTaxes.filter(tax => `${tax.tax_name} ${tax.rate}`.toLowerCase().includes(needle)) : availableTaxes;
    }, [query, availableTaxes]);

    const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const request = async (url: string, method: string, body?: Record<string, string>) => {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: body ? JSON.stringify(body) : undefined,
        });
        const payload = response.status === 204 ? null : await response.json();
        if (!response.ok) throw new Error(payload?.message || Object.values(payload?.errors || {}).flat()[0] || t('Unable to save tax.'));
        return payload as TaxOption | null;
    };
    const resetEditor = () => { setCreating(false); setEditing(null); setName(''); setRate('0'); setError(''); };
    const startCreate = () => { setEditing(null); setCreating(true); setName(''); setRate('0'); setError(''); };
    const startEdit = (tax: TaxOption) => { setCreating(false); setEditing(tax); setName(tax.tax_name); setRate(String(tax.rate)); setError(''); };
    const save = async () => {
        setSaving(true); setError('');
        try {
            const tax = await request(
                editing ? route('product-service.taxes.update', editing.id) : route('product-service.taxes.store'),
                editing ? 'PUT' : 'POST',
                { tax_name: name, rate },
            );
            if (!tax) return;
            setOptimisticTaxes(current => editing
                ? current.map(item => item.id === tax.id ? tax : item)
                : [...current.filter(item => item.id !== tax.id), tax]);
            onTaxesChange(editing ? availableTaxes.map(item => item.id === tax.id ? tax : item) : [...availableTaxes, tax]);
            if (editing && value === String(editing.id)) onChange(tax);
            resetEditor();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : t('Unable to save tax.'));
        } finally { setSaving(false); }
    };
    const remove = async (tax: TaxOption) => {
        if (!window.confirm(t('Delete this tax?'))) return;
        try {
            await request(route('product-service.taxes.destroy', tax.id), 'DELETE');
            setOptimisticTaxes(current => current.filter(item => item.id !== tax.id));
            onTaxesChange(availableTaxes.filter(item => item.id !== tax.id));
            if (value === String(tax.id)) onChange(null);
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : t('Unable to delete tax.'));
        }
    };

    return <>
        <Select value={selectedTax ? String(selectedTax.id) : 'none'} onValueChange={(next) => {
            if (next === 'manage') setOpen(true);
            else onChange(next === 'none' ? null : availableTaxes.find(tax => String(tax.id) === next) || null);
        }}>
            <SelectTrigger className="w-40">
                <SelectValue>{selectedTax ? `${selectedTax.tax_name} (${selectedTax.rate}%)` : t('No Tax')}</SelectValue>
            </SelectTrigger>
            <SelectContent searchable>
                <SelectItem value="none">{t('No Tax')}</SelectItem>
                {availableTaxes.map(tax => <SelectItem key={tax.id} value={String(tax.id)}>{tax.tax_name} ({tax.rate}%)</SelectItem>)}
                {canManage && <SelectItem value="manage" className="mt-1 border-t pt-2 font-medium text-primary">{t('Manage Taxes')}</SelectItem>}
            </SelectContent>
        </Select>

        <Dialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) resetEditor(); }}>
            <DialogContent className="max-w-2xl gap-0 overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-5"><DialogTitle>{t('Manage Taxes')}</DialogTitle></DialogHeader>
                {(creating || editing) && <div className="border-b bg-muted/20 p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div><Label required>{t('Tax Name')}</Label><Input value={name} onChange={event => setName(event.target.value)} autoFocus /></div>
                        <div><Label required>{t('Rate (%)')}</Label><Input type="number" min="0" max="100" step="0.01" value={rate} onChange={event => setRate(event.target.value)} /></div>
                    </div>
                    {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
                    <div className="mt-4 flex gap-2"><Button type="button" onClick={save} disabled={saving || !name.trim() || Number(rate) < 0 || Number(rate) > 100}>{t('Save')}</Button><Button type="button" variant="outline" onClick={resetEditor}>{t('Cancel')}</Button></div>
                </div>}
                <div className="flex items-center justify-between gap-4 border-b p-6">
                    <div className="relative max-w-sm flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input className="pl-9" value={query} onChange={event => setQuery(event.target.value)} placeholder={t('Search Taxes')} /></div>
                    {canCreate && !creating && !editing && <Button type="button" onClick={startCreate}><Plus className="mr-2 h-4 w-4" />{t('New Tax')}</Button>}
                </div>
                <div className="max-h-[420px] overflow-y-auto">
                    <div className="grid grid-cols-[1fr_120px_92px] border-b bg-muted/30 px-6 py-3 text-xs font-semibold uppercase text-muted-foreground"><span>{t('Tax Name')}</span><span>{t('Rate')}</span><span className="text-right">{t('Actions')}</span></div>
                    {filtered.map(tax => <div key={tax.id} className="group grid min-h-12 grid-cols-[1fr_120px_92px] items-center border-b px-6 py-2 text-sm last:border-0">
                        <span>{tax.tax_name}</span><span className="text-muted-foreground">{tax.rate}%</span>
                        <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                            {canEdit && <Button type="button" size="icon" variant="outline" className="h-8 w-8" title={t('Edit')} onClick={() => startEdit(tax)}><Pencil className="h-4 w-4" /></Button>}
                            {canDelete && <Button type="button" size="icon" variant="outline" className="h-8 w-8 text-destructive" title={t('Delete')} onClick={() => remove(tax)}><Trash2 className="h-4 w-4" /></Button>}
                        </div>
                    </div>)}
                    {!filtered.length && <div className="p-10 text-center text-sm text-muted-foreground">{t('No taxes found.')}</div>}
                </div>
            </DialogContent>
        </Dialog>
    </>;
}

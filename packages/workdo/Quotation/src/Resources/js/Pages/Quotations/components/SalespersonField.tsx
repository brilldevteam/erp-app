import { useMemo, useState } from 'react';
import { MoreVertical, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export type Salesperson = { id: number; name: string; email?: string | null; is_active: boolean };

export default function SalespersonField({ value, initialSalespersons, onChange }: {
    value: string;
    initialSalespersons: Salesperson[];
    onChange: (value: string) => void;
}) {
    const { t } = useTranslation();
    const [salespersons, setSalespersons] = useState(initialSalespersons);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<Salesperson | null>(null);
    const [creating, setCreating] = useState(false);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const activeSalespersons = salespersons.filter(person => person.is_active);
    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return needle ? salespersons.filter(person => `${person.name} ${person.email || ''}`.toLowerCase().includes(needle)) : salespersons;
    }, [query, salespersons]);

    const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const request = async (url: string, method: string, body?: Record<string, string>) => {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: body ? JSON.stringify(body) : undefined,
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || t('Unable to save salesperson.'));
        return payload as Salesperson;
    };
    const resetEditor = () => { setCreating(false); setEditing(null); setName(''); setEmail(''); setError(''); };
    const startCreate = () => { setEditing(null); setCreating(true); setName(''); setEmail(''); setError(''); };
    const startEdit = (person: Salesperson) => { setCreating(false); setEditing(person); setName(person.name); setEmail(person.email || ''); setError(''); };
    const save = async () => {
        setSaving(true); setError('');
        try {
            const person = await request(
                editing ? route('quotation-salespersons.update', editing.id) : route('quotation-salespersons.store'),
                editing ? 'PUT' : 'POST',
                { name, email },
            );
            setSalespersons(current => editing ? current.map(item => item.id === person.id ? person : item) : [...current, person]);
            onChange(String(person.id));
            resetEditor();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : t('Unable to save salesperson.'));
        } finally { setSaving(false); }
    };
    const toggle = async (person: Salesperson) => {
        const updated = await request(route('quotation-salespersons.toggle', person.id), 'PATCH');
        setSalespersons(current => current.map(item => item.id === updated.id ? updated : item));
        if (!updated.is_active && value === String(updated.id)) onChange('');
    };
    const remove = async (person: Salesperson) => {
        if (!window.confirm(t('Delete this salesperson?'))) return;
        await request(route('quotation-salespersons.destroy', person.id), 'DELETE');
        setSalespersons(current => current.filter(item => item.id !== person.id));
        if (value === String(person.id)) onChange('');
    };

    return <>
        <Label htmlFor="salesperson_id">{t('Salesperson')}</Label>
        <Select value={value || 'none'} onValueChange={(next) => next === 'manage' ? setOpen(true) : onChange(next === 'none' ? '' : next)}>
            <SelectTrigger id="salesperson_id"><SelectValue placeholder={t('Select Salesperson')} /></SelectTrigger>
            <SelectContent searchable>
                <SelectItem value="none">{t('No Salesperson')}</SelectItem>
                {activeSalespersons.map(person => <SelectItem key={person.id} value={String(person.id)}>{person.name}</SelectItem>)}
                <SelectItem value="manage" className="mt-1 border-t pt-2 font-medium text-primary">{t('Manage Salespersons')}</SelectItem>
            </SelectContent>
        </Select>

        <Dialog open={open} onOpenChange={(next) => { setOpen(next); if (!next) resetEditor(); }}>
            <DialogContent className="max-w-3xl gap-0 overflow-hidden p-0">
                <DialogHeader className="border-b px-6 py-5"><DialogTitle>{t('Manage Salespersons')}</DialogTitle></DialogHeader>
                {(creating || editing) && <div className="border-b bg-muted/20 p-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div><Label required>{t('Name')}</Label><Input value={name} onChange={event => setName(event.target.value)} autoFocus /></div>
                        <div><Label>{t('Email')}</Label><Input type="email" value={email} onChange={event => setEmail(event.target.value)} /></div>
                    </div>
                    {error && <p className="mt-2 text-sm text-destructive">{error}</p>}
                    <div className="mt-4 flex gap-2"><Button type="button" onClick={save} disabled={saving || !name.trim()}>{editing ? t('Save') : t('Save and Select')}</Button><Button type="button" variant="outline" onClick={resetEditor}>{t('Cancel')}</Button></div>
                </div>}
                <div className="flex items-center justify-between gap-4 border-b p-6">
                    <div className="relative max-w-sm flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" /><Input className="pl-9" value={query} onChange={event => setQuery(event.target.value)} placeholder={t('Search Salesperson')} /></div>
                    {!creating && !editing && <Button type="button" onClick={startCreate}><Plus className="mr-2 h-4 w-4" />{t('New Salesperson')}</Button>}
                </div>
                <div className="max-h-[420px] overflow-y-auto">
                    <div className="grid grid-cols-[1fr_1fr_92px] border-b bg-muted/30 px-6 py-3 text-xs font-semibold uppercase text-muted-foreground"><span>{t('Salesperson Name')}</span><span>{t('Email')}</span><span className="text-right">{t('Actions')}</span></div>
                    {filtered.map(person => <div key={person.id} className="group grid min-h-12 grid-cols-[1fr_1fr_92px] items-center border-b px-6 py-2 text-sm last:border-0">
                        <span className={!person.is_active ? 'text-muted-foreground line-through' : ''}>{person.name}</span><span className="truncate text-muted-foreground">{person.email || '-'}</span>
                        <div className="flex justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                            <Button type="button" size="icon" variant="outline" className="h-8 w-8" title={t('Edit')} onClick={() => startEdit(person)}><Pencil className="h-4 w-4" /></Button>
                            <DropdownMenu><DropdownMenuTrigger asChild><Button type="button" size="icon" variant="outline" className="h-8 w-8" title={t('More actions')}><MoreVertical className="h-4 w-4" /></Button></DropdownMenuTrigger><DropdownMenuContent align="end"><DropdownMenuItem onClick={() => toggle(person)}>{person.is_active ? t('Mark as Inactive') : t('Mark as Active')}</DropdownMenuItem><DropdownMenuItem className="text-destructive" onClick={() => remove(person)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem></DropdownMenuContent></DropdownMenu>
                        </div>
                    </div>)}
                    {!filtered.length && <div className="p-10 text-center text-sm text-muted-foreground">{t('No salespersons found.')}</div>}
                </div>
            </DialogContent>
        </Dialog>
    </>;
}

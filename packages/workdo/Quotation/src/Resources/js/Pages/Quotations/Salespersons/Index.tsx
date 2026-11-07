import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Head, router, usePage } from '@inertiajs/react';
import { MoreVertical, Pencil, Plus, Search, Trash2, UserRound, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

type Salesperson = { id: number; name: string; email?: string | null; is_active: boolean };
type SalespersonView = 'all' | 'active' | 'inactive';

interface Props {
    salespersons: Salesperson[];
    [key: string]: any;
}

export default function SalespersonIndex() {
    const { t } = useTranslation();
    const { salespersons: initialSalespersons } = usePage<Props>().props;
    const [salespersons, setSalespersons] = useState(initialSalespersons);
    const [query, setQuery] = useState('');
    const [activeView, setActiveView] = useState<SalespersonView>('all');
    const [editorOpen, setEditorOpen] = useState(false);
    const [editing, setEditing] = useState<Salesperson | null>(null);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return salespersons.filter(person => {
            const matchesView = activeView === 'all' || (activeView === 'active' ? person.is_active : !person.is_active);
            const matchesSearch = !needle || `${person.name} ${person.email || ''}`.toLowerCase().includes(needle);
            return matchesView && matchesSearch;
        });
    }, [activeView, query, salespersons]);
    const views: Array<[SalespersonView, string]> = [['all', 'All Salespersons'], ['active', 'Active'], ['inactive', 'Inactive']];

    const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '';
    const request = async (url: string, method: string, body?: Record<string, string>) => {
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: body ? JSON.stringify(body) : undefined,
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || t('Unable to save salesperson.'));
        return payload as Salesperson;
    };
    const closeEditor = () => { setEditorOpen(false); setEditing(null); setName(''); setEmail(''); setError(''); };
    const create = () => { setEditing(null); setName(''); setEmail(''); setError(''); setEditorOpen(true); };
    const edit = (person: Salesperson) => { setEditing(person); setName(person.name); setEmail(person.email || ''); setError(''); setEditorOpen(true); };
    const save = async () => {
        setSaving(true); setError('');
        try {
            const person = await request(
                editing ? route('quotation-salespersons.update', editing.id) : route('quotation-salespersons.store'),
                editing ? 'PUT' : 'POST',
                { name, email },
            );
            setSalespersons(current => editing ? current.map(item => item.id === person.id ? person : item) : [...current, person]);
            closeEditor();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : t('Unable to save salesperson.'));
        } finally {
            setSaving(false);
        }
    };
    const toggle = async (person: Salesperson) => {
        const updated = await request(route('quotation-salespersons.toggle', person.id), 'PATCH');
        setSalespersons(current => current.map(item => item.id === updated.id ? updated : item));
    };
    const remove = async (person: Salesperson) => {
        if (!window.confirm(t('Delete this salesperson?'))) return;
        await request(route('quotation-salespersons.destroy', person.id), 'DELETE');
        setSalespersons(current => current.filter(item => item.id !== person.id));
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[{ label: t('Sales') }, { label: t('Salespersons') }]}
            pageTitle={t('Salespersons')}
            pageActions={<Button size="sm" onClick={create}><Plus className="mr-2 h-4 w-4" />{t('New Salesperson')}</Button>}
        >
            <Head title={t('Salespersons')} />
            <div className="mx-auto max-w-[1600px] space-y-5 pb-8">
                <Card className="overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <CardContent className="p-0">
                        <div className="p-4 sm:p-5">
                            <div className="relative" role="search">
                                <Search className="absolute left-3.5 top-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    className="h-10 border-slate-200 bg-slate-50/70 pl-10 pr-10 focus-visible:bg-background"
                                    value={query}
                                    onChange={event => setQuery(event.target.value)}
                                    placeholder={t('Search salespersons by name or email')}
                                />
                                {query && <button type="button" onClick={() => setQuery('')} className="absolute right-3 top-2.5 rounded-md p-1 text-muted-foreground hover:bg-slate-200 hover:text-foreground" aria-label={t('Clear search')}><X className="h-4 w-4" /></button>}
                            </div>
                        </div>
                        <div className="flex gap-1 overflow-x-auto border-t bg-slate-50/50 px-4 py-2 sm:px-5" role="tablist" aria-label={t('Salesperson views')}>
                            {views.map(([value, label]) => <button key={value} type="button" role="tab" aria-selected={activeView === value} onClick={() => setActiveView(value)} className={`whitespace-nowrap rounded-lg px-3.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary ${activeView === value ? 'bg-background text-foreground shadow-sm ring-1 ring-slate-200' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}`}>{t(label)}</button>)}
                        </div>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden rounded-2xl border shadow-sm shadow-slate-200/40">
                    <div className="hidden grid-cols-[minmax(260px,1.2fr)_minmax(240px,1fr)_160px_96px] gap-4 border-b bg-slate-50/70 px-6 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-500 md:grid">
                        <span>{t('Salesperson')}</span><span>{t('Email')}</span><span>{t('Status')}</span><span />
                    </div>
                    <div className="divide-y">
                        {filtered.map(person => (
                            <div key={person.id} role="button" tabIndex={0} onClick={() => router.get(route('quotation-salespersons.show', person.id))} onKeyDown={event => { if (event.key === 'Enter' || event.key === ' ') router.get(route('quotation-salespersons.show', person.id)); }} className="group grid cursor-pointer gap-4 bg-background p-5 transition-colors hover:bg-blue-50/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary md:grid-cols-[minmax(260px,1.2fr)_minmax(240px,1fr)_160px_96px] md:items-center md:px-6 md:py-4">
                                <div className="flex min-w-0 items-center gap-3.5">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-blue-100 bg-blue-50 text-sm font-semibold text-blue-700">{person.name.charAt(0).toUpperCase()}</span>
                                    <div className="min-w-0"><p className="truncate font-semibold text-slate-900">{person.name}</p><p className="mt-0.5 text-xs text-slate-500">{t('Salesperson')}</p></div>
                                </div>
                                <div className="min-w-0 text-sm"><span className="mr-2 text-slate-500 md:hidden">{t('Email')}:</span><span className="break-all text-slate-700">{person.email || t('No email')}</span></div>
                                <div><span className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium ${person.is_active ? 'border-emerald-200 bg-emerald-50/70 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600'}`}><span className={`h-1.5 w-1.5 rounded-full ${person.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`} />{person.is_active ? t('Active') : t('Inactive')}</span></div>
                                <div className="flex justify-end gap-0.5 opacity-100 transition-opacity md:opacity-20 md:group-hover:opacity-100 md:group-focus-within:opacity-100" onClick={event => event.stopPropagation()}>
                                        <Button type="button" size="icon" variant="ghost" title={t('Edit')} onClick={() => edit(person)}><Pencil className="h-4 w-4" /></Button>
                                        <DropdownMenu><DropdownMenuTrigger asChild><Button type="button" size="icon" variant="ghost" title={t('More actions')}><MoreVertical className="h-4 w-4" /></Button></DropdownMenuTrigger><DropdownMenuContent align="end"><DropdownMenuItem onClick={() => toggle(person)}>{person.is_active ? t('Mark as Inactive') : t('Mark as Active')}</DropdownMenuItem><DropdownMenuItem className="text-destructive" onClick={() => remove(person)}><Trash2 className="mr-2 h-4 w-4" />{t('Delete')}</DropdownMenuItem></DropdownMenuContent></DropdownMenu>
                                </div>
                            </div>
                        ))}
                        {!filtered.length && <div className="p-14 text-center"><UserRound className="mx-auto mb-3 h-10 w-10 text-muted-foreground" /><p className="font-medium">{t('No salespersons found')}</p><p className="mt-1 text-sm text-muted-foreground">{t('Adjust your search or add a new salesperson.')}</p></div>}
                    </div>
                </Card>
            </div>

            <Dialog open={editorOpen} onOpenChange={open => { if (!open) closeEditor(); }}>
                <DialogContent className="max-w-xl">
                    <DialogHeader><DialogTitle>{editing ? t('Edit Salesperson') : t('Add Salesperson')}</DialogTitle></DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div><Label required>{t('Name')}</Label><Input className="mt-2" value={name} onChange={event => setName(event.target.value)} autoFocus /></div>
                        <div><Label>{t('Email')}</Label><Input className="mt-2" type="email" value={email} onChange={event => setEmail(event.target.value)} /></div>
                    </div>
                    {error && <p className="text-sm text-destructive">{error}</p>}
                    <div className="flex justify-end gap-2 pt-2"><Button type="button" variant="outline" onClick={closeEditor}>{t('Cancel')}</Button><Button type="button" onClick={save} disabled={saving || !name.trim()}>{saving ? t('Saving...') : t('Save')}</Button></div>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}

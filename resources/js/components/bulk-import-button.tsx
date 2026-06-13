import { router } from '@inertiajs/react';
import { Download, FileWarning, History, LoaderCircle, Upload } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface PreviewRow {
    row_number: number;
    data: Record<string, unknown>;
    errors: string[];
    duplicate: boolean;
}

interface ImportRecord {
    id: number;
    status: string;
    original_filename: string;
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    duplicate_rows: number;
    new_rows: number;
    processed_rows: number;
    imported_rows: number;
    updated_rows: number;
    skipped_rows: number;
    failure_message?: string;
    has_errors: boolean;
    created_at: string;
    preview?: PreviewRow[];
}

interface BulkImportButtonProps {
    entity: 'customers' | 'product-service-items';
    label: string;
}

const terminalStatuses = ['ready', 'completed', 'failed'];

export function BulkImportButton({ entity, label }: BulkImportButtonProps) {
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState<File | null>(null);
    const [current, setCurrent] = useState<ImportRecord | null>(null);
    const [history, setHistory] = useState<ImportRecord[]>([]);
    const [strategy, setStrategy] = useState('skip');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const pollRef = useRef<number | null>(null);
    const refreshed = useRef<Set<number>>(new Set());

    const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    const request = async (url: string, options: RequestInit = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                ...(options.headers ?? {}),
            },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(body.message || 'The import request failed.');

        return body;
    };

    const loadHistory = async () => {
        const body = await request(route('bulk-imports.history', entity));
        setHistory(body.data ?? []);
    };

    const poll = async (id: number) => {
        try {
            const record = await request(route('bulk-imports.show', id));
            setCurrent(record);
            if (terminalStatuses.includes(record.status)) {
                if (pollRef.current) window.clearInterval(pollRef.current);
                pollRef.current = null;
                setBusy(false);
                loadHistory();
                if (record.status === 'completed' && !refreshed.current.has(record.id)) {
                    refreshed.current.add(record.id);
                    router.reload();
                }
            }
        } catch (pollError) {
            setError(pollError instanceof Error ? pollError.message : 'Unable to refresh import status.');
            setBusy(false);
        }
    };

    const startPolling = (id: number) => {
        if (pollRef.current) window.clearInterval(pollRef.current);
        pollRef.current = window.setInterval(() => poll(id), 1500);
        poll(id);
    };

    useEffect(() => {
        if (open) loadHistory().catch((historyError) => setError(historyError.message));
        return () => {
            if (pollRef.current) window.clearInterval(pollRef.current);
        };
    }, [open]);

    const upload = async () => {
        if (!file) return;
        setBusy(true);
        setError('');
        const form = new FormData();
        form.append('file', file);

        try {
            const record = await request(route('bulk-imports.store', entity), {
                method: 'POST',
                body: form,
            });
            setCurrent(record);
            startPolling(record.id);
        } catch (uploadError) {
            setError(uploadError instanceof Error ? uploadError.message : 'Upload failed.');
            setBusy(false);
        }
    };

    const confirm = async () => {
        if (!current) return;
        setBusy(true);
        setError('');

        try {
            const record = await request(route('bulk-imports.confirm', current.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ strategy }),
            });
            setCurrent(record);
            startPolling(record.id);
        } catch (confirmError) {
            setError(confirmError instanceof Error ? confirmError.message : 'Import could not be started.');
            setBusy(false);
        }
    };

    const reset = () => {
        setFile(null);
        setCurrent(null);
        setStrategy('skip');
        setError('');
        if (pollRef.current) window.clearInterval(pollRef.current);
        pollRef.current = null;
    };

    const progress = current?.valid_rows
        ? Math.min(100, Math.round((current.processed_rows / current.valid_rows) * 100))
        : 0;

    return (
        <>
            <Button variant="outline" size="sm" onClick={() => setOpen(true)}>
                <Upload className="h-4 w-4" />
                <span className="hidden sm:inline">Import</span>
            </Button>

            <Dialog open={open} onOpenChange={(value) => {
                setOpen(value);
                if (!value) reset();
            }}>
                <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Import {label}</DialogTitle>
                    </DialogHeader>

                    <div className="space-y-5">
                        {!current && (
                            <>
                                <div className="rounded-lg border bg-muted/30 p-4 text-sm">
                                    Download a template, keep its columns unchanged, delete the example row,
                                    and upload no more than 10,000 records.
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={route('bulk-imports.template', { entity, format: 'xlsx' })}>
                                            <Download className="h-4 w-4" /> XLSX Template
                                        </a>
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={route('bulk-imports.template', { entity, format: 'csv' })}>
                                            <Download className="h-4 w-4" /> CSV Template
                                        </a>
                                    </Button>
                                </div>
                                <div>
                                    <Label htmlFor={`bulk-import-${entity}`}>CSV or XLSX file</Label>
                                    <Input
                                        id={`bulk-import-${entity}`}
                                        type="file"
                                        accept=".csv,.xlsx"
                                        onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                                    />
                                </div>
                                <Button onClick={upload} disabled={!file || busy}>
                                    {busy && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    Validate File
                                </Button>
                            </>
                        )}

                        {current && (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p className="font-medium">{current.original_filename}</p>
                                        <p className="text-sm capitalize text-muted-foreground">
                                            Status: {current.status.replace('_', ' ')}
                                        </p>
                                    </div>
                                    {busy && <LoaderCircle className="h-5 w-5 animate-spin text-primary" />}
                                </div>

                                {['queued', 'importing'].includes(current.status) && (
                                    <div>
                                        <div className="mb-1 flex justify-between text-sm">
                                            <span>Import progress</span><span>{progress}%</span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded bg-muted">
                                            <div className="h-full bg-primary transition-all" style={{ width: `${progress}%` }} />
                                        </div>
                                    </div>
                                )}

                                {['ready', 'queued', 'importing', 'completed'].includes(current.status) && (
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                        {[
                                            ['Total', current.total_rows],
                                            ['Valid', current.valid_rows],
                                            ['Invalid', current.invalid_rows],
                                            ['New', current.new_rows],
                                            ['Existing', current.duplicate_rows],
                                        ].map(([name, value]) => (
                                            <div key={name} className="rounded-lg border p-3 text-center">
                                                <div className="text-xl font-semibold">{value}</div>
                                                <div className="text-xs text-muted-foreground">{name}</div>
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {current.status === 'ready' && (
                                    <div className="space-y-3">
                                        {current.preview && current.preview.length > 0 && (
                                            <div className="max-h-52 overflow-auto rounded-lg border">
                                                <table className="w-full text-sm">
                                                    <thead className="sticky top-0 bg-muted">
                                                        <tr>
                                                            <th className="p-2 text-left">Row</th>
                                                            <th className="p-2 text-left">Result</th>
                                                            <th className="p-2 text-left">Details</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {current.preview.map((row) => (
                                                            <tr key={row.row_number} className="border-t">
                                                                <td className="p-2">{row.row_number}</td>
                                                                <td className="p-2">
                                                                    {row.errors.length ? 'Invalid' : row.duplicate ? 'Existing' : 'New'}
                                                                </td>
                                                                <td className="p-2 text-muted-foreground">
                                                                    {row.errors.join(' ') || Object.values(row.data)
                                                                        .filter(Boolean).slice(0, 3).join(' | ')}
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}

                                        <div>
                                            <Label>Existing records</Label>
                                            <Select value={strategy} onValueChange={setStrategy}>
                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="skip">Skip existing records</SelectItem>
                                                    <SelectItem value="update">Update existing records</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button onClick={confirm} disabled={busy || current.valid_rows === 0}>
                                                Start Import
                                            </Button>
                                            <Button variant="outline" onClick={reset}>Choose Another File</Button>
                                        </div>
                                    </div>
                                )}

                                {current.status === 'completed' && (
                                    <div className="rounded-lg border bg-green-50 p-4 text-sm text-green-800">
                                        Imported {current.imported_rows}, updated {current.updated_rows},
                                        and skipped {current.skipped_rows} records.
                                    </div>
                                )}

                                {current.status === 'failed' && (
                                    <div className="flex gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                        <FileWarning className="h-5 w-5 shrink-0" />
                                        {current.failure_message || 'The import failed.'}
                                    </div>
                                )}

                                {current.has_errors && (
                                    <Button variant="outline" size="sm" asChild>
                                        <a href={route('bulk-imports.errors', current.id)}>
                                            <Download className="h-4 w-4" /> Download Error Report
                                        </a>
                                    </Button>
                                )}
                            </div>
                        )}

                        {error && <p className="text-sm text-destructive">{error}</p>}

                        {history.length > 0 && (
                            <div className="border-t pt-4">
                                <h3 className="mb-2 flex items-center gap-2 font-medium">
                                    <History className="h-4 w-4" /> Recent Imports
                                </h3>
                                <div className="max-h-40 space-y-2 overflow-y-auto">
                                    {history.map((item) => (
                                        <button
                                            key={item.id}
                                            type="button"
                                            className="flex w-full justify-between rounded border p-2 text-left text-sm hover:bg-muted"
                                            onClick={() => {
                                                setCurrent(item);
                                                if (!terminalStatuses.includes(item.status)) startPolling(item.id);
                                            }}
                                        >
                                            <span className="truncate">{item.original_filename}</span>
                                            <span className="ml-3 capitalize text-muted-foreground">{item.status}</span>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

export default BulkImportButton;

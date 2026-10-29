import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    AlertTriangle, ArrowUpRight, Check, CheckCircle2, ChevronRight, Circle,
    Clock3, Download, FileSpreadsheet, History, Import, Loader2, LockKeyhole,
    ClipboardPaste, Plus, RefreshCw, Upload, XCircle,
} from 'lucide-react';

type MigrationFile = {
    id: number;
    original_filename: string;
    status: string;
    dataset_type: string | null;
    profile_key: string | null;
    detection_confidence: number | null;
    headers: string[] | null;
    row_count: number;
    failure_message: string | null;
    preview_rows: Array<{ source_row_number: number; original_values: Record<string, unknown> }>;
    created_at: string;
};

type Stage = {
    key: string;
    title: string;
    description: string;
    position: number;
    supported: boolean;
    manual_entry?: boolean;
    status: string;
    locked: boolean;
    accepted_count: number;
    rejected_count: number;
    warning_count: number;
    completed_at: string | null;
    destination_url: string | null;
    files: MigrationFile[];
};

type Batch = {
    id: number;
    name: string;
    status: string;
    workflow_mode: 'full' | 'standalone';
    accounting_mode: string;
    base_currency: string;
    source_date_format: string;
    inventory_cutover_at: string;
    default_warehouse_id: number;
    duplicate_strategy: string;
    ai_enabled: boolean;
    stages: Stage[];
    issues?: Array<{ id: number; stage_key: string; message: string; severity: string; resolution_url: string | null }>;
    events?: Array<{ id: number; event_type: string; message: string; stage_key: string | null; created_at: string }>;
};

type Props = {
    batches: Batch[];
    permissions: { create: boolean; upload: boolean; approve: boolean; execute: boolean };
};

const statusStyle: Record<string, { marker: string; text: string; icon: typeof Circle; label: string }> = {
    completed: { marker: 'border-emerald-500 bg-emerald-500 text-white', text: 'text-emerald-700', icon: Check, label: 'Completed' },
    processing: { marker: 'border-sky-500 bg-sky-50 text-sky-600', text: 'text-sky-700', icon: Loader2, label: 'Processing' },
    review_required: { marker: 'border-amber-500 bg-amber-50 text-amber-700', text: 'text-amber-700', icon: AlertTriangle, label: 'Review required' },
    ready: { marker: 'border-sky-500 bg-sky-50 text-sky-700', text: 'text-sky-700', icon: CheckCircle2, label: 'Ready to import' },
    failed: { marker: 'border-red-500 bg-red-50 text-red-600', text: 'text-red-700', icon: XCircle, label: 'Failed' },
    locked: { marker: 'border-slate-300 bg-slate-100 text-slate-400', text: 'text-slate-500', icon: LockKeyhole, label: 'Locked' },
    unsupported: { marker: 'border-slate-300 bg-white text-slate-400', text: 'text-slate-500', icon: Clock3, label: 'Not yet supported' },
    not_started: { marker: 'border-slate-300 bg-white text-slate-400', text: 'text-slate-500', icon: Circle, label: 'Not started' },
};

export default function ZohoMigrations({ batches: initialBatches, permissions }: Props) {
    const [batches, setBatches] = useState(initialBatches);
    const [selectedBatchId, setSelectedBatchId] = useState<number | null>(initialBatches[0]?.id ?? null);
    const [selectedStageKey, setSelectedStageKey] = useState<string | null>(initialBatches[0]?.stages?.[0]?.key ?? null);
    const [showCreate, setShowCreate] = useState(initialBatches.length === 0);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [uploadProgress, setUploadProgress] = useState<number | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const selectedBatch = batches.find(batch => batch.id === selectedBatchId) ?? null;
    const selectedStage = selectedBatch?.stages.find(stage => stage.key === selectedStageKey) ?? selectedBatch?.stages[0] ?? null;

    const completed = selectedBatch?.stages.filter(stage => stage.status === 'completed').length ?? 0;
    const progress = selectedBatch ? Math.round((completed / selectedBatch.stages.length) * 100) : 0;
    const imported = selectedBatch?.stages.reduce((total, stage) => total + stage.accepted_count, 0) ?? 0;
    const rejected = selectedBatch?.stages.reduce((total, stage) => total + stage.rejected_count, 0) ?? 0;
    const warnings = selectedBatch?.stages.reduce((total, stage) => total + stage.warning_count, 0) ?? 0;

    const refreshBatch = async (batchId: number) => {
        const response = await axios.get<Batch>(route('zoho-migrations.show', batchId));
        setBatches(current => current.map(batch => batch.id === batchId ? response.data : batch));
    };

    useEffect(() => {
        const hasProcessing = selectedBatch?.stages.some(stage => stage.status === 'processing' || stage.files.some(file => ['uploaded', 'detecting'].includes(file.status)));
        if (!hasProcessing || !selectedBatch) return;
        const timer = window.setInterval(() => refreshBatch(selectedBatch.id), 2500);
        return () => window.clearInterval(timer);
    }, [selectedBatch]);

    const createBatch = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        const values = new FormData(event.currentTarget);
        try {
            const response = await axios.post<Batch>(route('zoho-migrations.store'), Object.fromEntries(values.entries()));
            setBatches(current => [response.data, ...current]);
            setSelectedBatchId(response.data.id);
            setSelectedStageKey('configuration');
            setShowCreate(false);
        } catch (exception: any) {
            setError(exception.response?.data?.message ?? 'The migration workspace could not be created.');
        } finally {
            setBusy(false);
        }
    };

    const validateStage = async () => {
        if (!selectedBatch || !selectedStage) return;
        setBusy(true);
        setError(null);
        try {
            await axios.post(route('zoho-migrations.stages.validate', [selectedBatch.id, selectedStage.key]));
            await refreshBatch(selectedBatch.id);
        } catch (exception: any) {
            setError(exception.response?.data?.message ?? 'Validation could not be completed.');
        } finally {
            setBusy(false);
        }
    };

    const uploadFile = async (file: File) => {
        if (!selectedBatch || !selectedStage) return;
        setBusy(true);
        setError(null);
        setUploadProgress(0);
        const data = new FormData();
        data.append('stage_key', selectedStage.key);
        data.append('file', file);
        try {
            await axios.post(route('zoho-migrations.files.store', selectedBatch.id), data, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: event => setUploadProgress(event.total ? Math.round((event.loaded / event.total) * 100) : 0),
            });
            await refreshBatch(selectedBatch.id);
        } catch (exception: any) {
            const errors = exception.response?.data?.errors;
            setError(errors ? Object.values(errors).flat().join(' ') : (exception.response?.data?.message ?? 'The file could not be uploaded.'));
        } finally {
            setBusy(false);
            setUploadProgress(null);
            if (fileInput.current) fileInput.current.value = '';
        }
    };

    const executeStage = async () => {
        if (!selectedBatch || !selectedStage) return;
        setBusy(true);
        setError(null);
        try {
            await axios.post(route('zoho-migrations.stages.execute', [selectedBatch.id, selectedStage.key]));
            await refreshBatch(selectedBatch.id);
        } catch (exception: any) {
            setError(exception.response?.data?.message ?? 'The import could not be started.');
        } finally {
            setBusy(false);
        }
    };

    const importManualPrerequisite = async (pastedData: string) => {
        if (!selectedBatch || !selectedStage) return;
        await axios.post(route('zoho-migrations.stages.manual-prerequisite', [selectedBatch.id, selectedStage.key]), {
            pasted_data: pastedData,
            accuracy_confirmed: true,
        });
        await refreshBatch(selectedBatch.id);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bulk Import" />
            <main className="mx-auto w-full max-w-[1500px] px-4 py-5 sm:px-6 lg:px-8">
                <header className="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase text-emerald-700">Data migration</p>
                        <h1 className="mt-1 text-2xl font-semibold text-slate-950">Bulk Import</h1>
                        <p className="mt-1 max-w-2xl text-sm text-slate-600">Import unchanged Zoho exports in a controlled sequence with validation and reconciliation.</p>
                    </div>
                    <div className="flex items-center gap-2">
                        {selectedBatch && <Button variant="outline" size="icon" title="Refresh workspace" onClick={() => refreshBatch(selectedBatch.id)}><RefreshCw className="h-4 w-4" /></Button>}
                        {permissions.create && <Button onClick={() => setShowCreate(value => !value)}><Plus className="mr-2 h-4 w-4" />New migration</Button>}
                    </div>
                </header>

                {showCreate && (
                    <form onSubmit={createBatch} className="mt-5 border-b border-slate-200 bg-white pb-6">
                        <div className="mb-4 flex items-center justify-between"><h2 className="text-base font-semibold">Create migration workspace</h2><Button type="button" variant="ghost" onClick={() => setShowCreate(false)}>Cancel</Button></div>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Field label="Migration name"><Input name="name" required placeholder="Company data migration" /></Field>
                            <Field label="Workflow"><NativeSelect name="workflow_mode" options={[['full', 'Full guided migration'], ['standalone', 'Standalone imports']]} /></Field>
                            <Field label="Accounting mode"><NativeSelect name="accounting_mode" options={[['rebuild', 'Rebuild from documents'], ['full_history', 'Full Zoho ledger history']]} /></Field>
                            <Field label="Base currency"><Input name="base_currency" required maxLength={3} defaultValue="QAR" /></Field>
                            <Field label="Zoho organization ID"><Input name="zoho_organization_id" placeholder="Optional" /></Field>
                            <Field label="Zoho date format"><NativeSelect name="source_date_format" options={[["dd/MM/yyyy", 'DD/MM/YYYY'], ["MM/dd/yyyy", 'MM/DD/YYYY'], ["yyyy-MM-dd", 'YYYY-MM-DD'], ["dd-MM-yyyy", 'DD-MM-YYYY']]} /></Field>
                            <Field label="Inventory cutover"><Input name="inventory_cutover_at" type="date" required /></Field>
                            <Field label="Default warehouse">
                                <Input name="default_warehouse_name" required placeholder="Main Warehouse" />
                                <p className="text-xs text-slate-500">An exact existing name is reused; otherwise a new active warehouse is added to ERP.</p>
                            </Field>
                            <input type="hidden" name="duplicate_strategy" value="block" />
                            <input type="hidden" name="ai_enabled" value="0" />
                        </div>
                        {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
                        <Button className="mt-5" disabled={busy}>{busy && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}Create workspace</Button>
                    </form>
                )}

                {batches.length > 0 && (
                    <div className="mt-5 flex flex-col gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <Label htmlFor="batch">Workspace</Label>
                            <Select value={String(selectedBatchId)} onValueChange={value => { const id = Number(value); setSelectedBatchId(id); setSelectedStageKey(batches.find(item => item.id === id)?.stages[0]?.key ?? null); }}>
                                <SelectTrigger id="batch" className="w-[280px]"><SelectValue /></SelectTrigger>
                                <SelectContent>{batches.map(batch => <SelectItem key={batch.id} value={String(batch.id)}>{batch.name}</SelectItem>)}</SelectContent>
                            </Select>
                            {selectedBatch && <span className="text-xs font-medium uppercase text-slate-500">{selectedBatch.workflow_mode === 'full' ? 'Guided migration' : 'Standalone imports'}</span>}
                        </div>
                        <div className="text-sm text-slate-600">{completed} of {selectedBatch?.stages.length ?? 0} stages completed</div>
                    </div>
                )}

                {selectedBatch ? (
                    <>
                        <section className="grid grid-cols-2 gap-px border-b border-slate-200 bg-slate-200 sm:grid-cols-4">
                            <Metric label="Overall progress" value={`${progress}%`} accent="text-emerald-700" />
                            <Metric label="Validated rows" value={String(imported)} />
                            <Metric label="Rejected" value={String(rejected)} accent={rejected ? 'text-red-600' : undefined} />
                            <Metric label="Warnings" value={String(warnings)} accent={warnings ? 'text-amber-600' : undefined} />
                        </section>
                        <div className="h-1.5 bg-slate-200"><div className="h-full bg-emerald-500 transition-all" style={{ width: `${progress}%` }} /></div>

                        <div className="grid gap-0 lg:grid-cols-[minmax(360px,0.9fr)_minmax(0,1.5fr)]">
                            <section className="border-r border-slate-200 bg-white px-3 py-5 sm:px-5">
                                <div className="mb-5 flex items-center gap-2"><Import className="h-4 w-4 text-slate-500" /><h2 className="text-sm font-semibold uppercase text-slate-700">Import journey</h2></div>
                                <div>
                                    {selectedBatch.stages.map((stage, index) => <TimelineStage key={stage.key} stage={stage} isSelected={selectedStage?.key === stage.key} connectorComplete={stage.status === 'completed'} isLast={index === selectedBatch.stages.length - 1} onSelect={() => setSelectedStageKey(stage.key)} />)}
                                </div>
                            </section>
                            <StagePanel stage={selectedStage} batch={selectedBatch} permissions={permissions} busy={busy} error={error} uploadProgress={uploadProgress} fileInput={fileInput} onUpload={uploadFile} onValidate={validateStage} onExecute={executeStage} onManualImport={importManualPrerequisite} />
                        </div>
                    </>
                ) : !showCreate ? (
                    <div className="py-24 text-center"><FileSpreadsheet className="mx-auto h-10 w-10 text-slate-300" /><h2 className="mt-4 text-lg font-semibold">No migration workspace yet</h2><p className="mt-1 text-sm text-slate-500">Create one to begin importing Zoho exports.</p></div>
                ) : null}
            </main>
        </AuthenticatedLayout>
    );
}

function TimelineStage({ stage, isSelected, connectorComplete, isLast, onSelect }: { stage: Stage; isSelected: boolean; connectorComplete: boolean; isLast: boolean; onSelect: () => void }) {
    const style = statusStyle[stage.status] ?? statusStyle.not_started;
    const Icon = style.icon;
    return <button type="button" onClick={onSelect} className={`relative flex w-full gap-3 rounded-md px-2 pb-6 text-left transition-colors ${isSelected ? 'bg-slate-100' : 'hover:bg-slate-50'}`}>
        <span className="relative z-10 mt-1 flex h-8 w-8 shrink-0 items-center justify-center">
            {!isLast && <span className={`absolute left-1/2 top-8 h-[calc(100%+16px)] w-0.5 -translate-x-1/2 ${connectorComplete ? 'bg-emerald-500' : 'bg-slate-200'}`} />}
            <span className={`relative flex h-8 w-8 items-center justify-center rounded-full border-2 ${style.marker}`}><Icon className={`h-4 w-4 ${stage.status === 'processing' ? 'animate-spin' : ''}`} /></span>
        </span>
        <span className="min-w-0 flex-1 py-1">
            <span className="flex items-start justify-between gap-2"><span className="text-sm font-semibold text-slate-900">{stage.position}. {stage.title}</span><ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" /></span>
            <span className={`mt-1 block text-xs font-medium ${style.text}`}>{style.label}</span>
        </span>
    </button>;
}

function StagePanel({ stage, batch, permissions, busy, error, uploadProgress, fileInput, onUpload, onValidate, onExecute, onManualImport }: { stage: Stage | null; batch: Batch; permissions: Props['permissions']; busy: boolean; error: string | null; uploadProgress: number | null; fileInput: React.RefObject<HTMLInputElement>; onUpload: (file: File) => void; onValidate: () => void; onExecute: () => void; onManualImport: (pastedData: string) => Promise<void> }) {
    if (!stage) return null;
    const style = statusStyle[stage.status] ?? statusStyle.not_started;
    const latestFile = stage.files[stage.files.length - 1];
    const stageIssues = batch.issues?.filter(issue => issue.stage_key === stage.key) ?? [];
    const stageEvents = batch.events?.filter(event => event.stage_key === stage.key) ?? [];
    const canUpload = permissions.upload && stage.supported && !stage.manual_entry && !stage.locked && stage.key !== 'configuration';

    return <section className="min-w-0 bg-slate-50 px-4 py-6 sm:px-7 lg:px-8">
        <div className="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p className={`text-xs font-semibold uppercase ${style.text}`}>{style.label}</p><h2 className="mt-1 text-xl font-semibold text-slate-950">{stage.title}</h2><p className="mt-2 max-w-2xl text-sm text-slate-600">{stage.description}</p></div>
            {stage.destination_url && <Button variant="outline" asChild><a href={stage.destination_url}>View ERP records<ArrowUpRight className="ml-2 h-4 w-4" /></a></Button>}
        </div>

        {stage.status === 'unsupported' && <Notice icon={Clock3} title="Not yet supported" text="This stage remains visible in the complete migration sequence, but execution is disabled until its Zoho profile and reconciliation rules are verified." />}
        {stage.locked && <Notice icon={LockKeyhole} title="Complete the previous stage" text="Guided migrations enforce the approved top-to-bottom order. This stage will unlock automatically after the previous stage reconciles." />}
        {stage.key === 'configuration' && <Notice icon={CheckCircle2} title="Configuration complete" text={`${batch.base_currency} base currency, ${batch.accounting_mode === 'rebuild' ? 'document rebuild' : 'full ledger history'} accounting mode, and ${batch.workflow_mode} workflow are recorded for this migration.`} />}
        {stage.status === 'processing' && <Notice icon={Loader2} title="Import processing" text="The source is being inspected, validated, or imported. This page updates automatically; you can safely leave it open." />}
        {stage.status === 'ready' && <Notice icon={CheckCircle2} title="Validation passed" text={`${stage.accepted_count} rows passed validation. Review the counts, then import them into ERP.`} />}
        {stage.status === 'completed' && <Notice icon={CheckCircle2} title="Import completed" text={`${stage.accepted_count} validated rows were processed successfully. The next journey stage is now available.`} />}

        {stage.manual_entry && !stage.locked && permissions.upload && <ManualPrerequisitePanel key={stage.key} stage={stage} busy={busy} onImport={onManualImport} />}

        {canUpload && (
            <div className="mt-6 border border-dashed border-slate-300 bg-white p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 className="text-sm font-semibold">Upload original Zoho export</h3><p className="mt-1 text-xs text-slate-500">CSV or XLSX, up to 10 MB and 10,000 rows.</p></div>
                    <div><input ref={fileInput} className="hidden" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" onChange={event => event.target.files?.[0] && onUpload(event.target.files[0])} /><Button disabled={busy} onClick={() => fileInput.current?.click()}>{busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}Choose file</Button></div>
                </div>
                {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
                {uploadProgress !== null && <div className="mt-4"><div className="mb-1 flex justify-between text-xs font-medium text-slate-600"><span>Uploading source</span><span>{uploadProgress}%</span></div><div className="h-2 overflow-hidden bg-slate-200"><div className="h-full bg-emerald-500 transition-all" style={{ width: `${uploadProgress}%` }} /></div></div>}
            </div>
        )}

        {stage.status === 'processing' && <div className="mt-5 h-2 overflow-hidden bg-slate-200"><div className="h-full w-1/2 animate-pulse bg-sky-500" /></div>}

        {latestFile && <>
            <div className="mt-5 flex flex-wrap gap-2">
                {stage.status === 'review_required' && <Button onClick={onValidate} disabled={busy}>{busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}Recheck corrections</Button>}
                {stage.status === 'ready' && permissions.execute && <Button onClick={onExecute} disabled={busy}>{busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Import className="mr-2 h-4 w-4" />}Import {stage.accepted_count} rows</Button>}
                <Button variant="outline" asChild><a href={route('zoho-migrations.files.source', [batch.id, latestFile.id])}><Download className="mr-2 h-4 w-4" />Source file</a></Button>
                {stageIssues.length > 0 && <Button variant="outline" asChild><a href={route('zoho-migrations.stages.issues', [batch.id, stage.key])}><Download className="mr-2 h-4 w-4" />Issue report</a></Button>}
            </div>
            <FilePreview file={latestFile} />
        </>}
        {stageIssues.length > 0 && <div className="mt-6"><SectionTitle icon={AlertTriangle} title="Correct these before import" /><p className="mt-2 text-sm text-slate-600">Fix the source Excel and upload it again, or add the missing reference in ERP and choose Recheck corrections. No rows will be imported while errors remain.</p><div className="mt-3 divide-y divide-slate-200 border-y border-slate-200 bg-white">{stageIssues.map((issue: any) => <div key={issue.id} className="flex items-start justify-between gap-4 px-4 py-3"><div><p className="text-sm font-medium text-slate-800">{issue.message}</p>{issue.source_value && <p className="mt-1 text-xs text-slate-500">{issue.field}: {issue.source_value}</p>}</div>{issue.resolution_url && <a className="shrink-0 text-sm font-medium text-sky-700" href={issue.resolution_url}>Fix in ERP</a>}</div>)}</div></div>}
        <div className="mt-7"><SectionTitle icon={History} title="Activity" /><div className="mt-3 space-y-3">{stageEvents.length ? stageEvents.map(event => <div key={event.id} className="flex gap-3 text-sm"><span className="mt-1 h-2 w-2 rounded-full bg-slate-300" /><div><p className="text-slate-700">{event.message}</p><p className="mt-0.5 text-xs text-slate-400">{new Date(event.created_at).toLocaleString()}</p></div></div>) : <p className="text-sm text-slate-500">No activity recorded for this stage.</p>}</div></div>
    </section>;
}

const manualHeaders: Record<string, string[]> = {
    items: ['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price', 'description'],
    inventory_cutover: ['sku', 'warehouse', 'quantity'],
};

function ManualPrerequisitePanel({ stage, busy, onImport }: { stage: Stage; busy: boolean; onImport: (pastedData: string) => Promise<void> }) {
    const headers = manualHeaders[stage.key] ?? [];
    const headerLine = headers.join('\t');
    const [pastedData, setPastedData] = useState(headerLine);
    const [confirmed, setConfirmed] = useState(false);
    const [localBusy, setLocalBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const submit = async () => {
        setLocalBusy(true);
        setLocalError(null);
        try {
            await onImport(pastedData);
            setConfirmed(false);
        } catch (exception: any) {
            const errors = exception.response?.data?.errors;
            setLocalError(errors ? Object.values(errors).flat().join(' ') : (exception.response?.data?.message ?? 'The pasted data could not be imported.'));
        } finally {
            setLocalBusy(false);
        }
    };

    return <div className="mt-6 border-y border-amber-300 bg-amber-50">
        <div className="flex gap-3 border-b border-amber-200 px-4 py-4">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
            <div>
                <h3 className="text-sm font-semibold text-amber-950">Accuracy required before later imports</h3>
                <p className="mt-1 text-sm text-amber-900">Zoho did not provide this dataset. Copy and paste the approved data without changing the column order. Product, invoice, bill and stock imports will rely on these exact values. Incorrect SKUs, names, warehouses or quantities can link later records incorrectly.</p>
            </div>
        </div>
        <div className="bg-white px-4 py-5">
            <div className="flex items-center gap-2"><ClipboardPaste className="h-4 w-4 text-slate-500" /><h3 className="text-sm font-semibold text-slate-900">Paste from Excel</h3></div>
            <p className="mt-2 overflow-x-auto whitespace-pre border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700">{headerLine}</p>
            <Textarea value={pastedData} onChange={event => setPastedData(event.target.value)} className="mt-3 min-h-[220px] resize-y rounded-none font-mono text-xs" spellCheck={false} />
            <label className="mt-4 flex items-start gap-3 text-sm text-slate-700">
                <input type="checkbox" checked={confirmed} onChange={event => setConfirmed(event.target.checked)} className="mt-0.5 h-4 w-4" />
                <span>I checked every pasted row against the approved source. I understand later imports will use these SKUs, names, warehouses and quantities.</span>
            </label>
            {localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
            <Button className="mt-4" disabled={busy || localBusy || !confirmed || pastedData.trim() === headerLine} onClick={submit}>
                {(busy || localBusy) ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
                Confirm and import
            </Button>
        </div>
    </div>;
}

function FilePreview({ file }: { file: MigrationFile }) {
    const headers = file.headers?.slice(0, 6) ?? [];
    return <div className="mt-6">
        <SectionTitle icon={FileSpreadsheet} title="Source preview" />
        <div className="mt-3 overflow-hidden border border-slate-200 bg-white">
            <div className="flex flex-col gap-2 border-b border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-semibold text-slate-900">{file.original_filename}</p><p className="text-xs text-slate-500">{file.profile_key ? `${file.profile_key} · ${file.row_count} rows · ${file.detection_confidence}% match` : file.status}</p></div>{file.failure_message && <span className="text-xs font-medium text-red-600">{file.failure_message}</span>}</div>
            {file.preview_rows.length > 0 && <div className="overflow-x-auto"><table className="w-full min-w-[680px] text-left text-xs"><thead className="bg-slate-50 text-slate-500"><tr><th className="px-3 py-2">Row</th>{headers.map(header => <th key={header} className="px-3 py-2 font-medium">{header}</th>)}</tr></thead><tbody className="divide-y divide-slate-100">{file.preview_rows.map(row => <tr key={row.source_row_number}><td className="px-3 py-2 text-slate-400">{row.source_row_number}</td>{headers.map(header => <td key={header} className="max-w-[180px] truncate px-3 py-2 text-slate-700">{String(row.original_values[header] ?? '')}</td>)}</tr>)}</tbody></table></div>}
        </div>
    </div>;
}

function Metric({ label, value, accent = 'text-slate-950' }: { label: string; value: string; accent?: string }) { return <div className="bg-white px-5 py-4"><p className="text-xs font-medium uppercase text-slate-500">{label}</p><p className={`mt-1 text-xl font-semibold ${accent}`}>{value}</p></div>; }
function Field({ label, children }: { label: string; children: React.ReactNode }) { return <div className="space-y-1.5"><Label>{label}</Label>{children}</div>; }
function NativeSelect({ name, options }: { name: string; options: Array<[string, string]> }) { return <select name={name} required className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">{options.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select>; }
function Notice({ icon: Icon, title, text }: { icon: typeof Circle; title: string; text: string }) { return <div className="mt-6 flex gap-3 border-l-4 border-slate-300 bg-white px-4 py-4"><Icon className="mt-0.5 h-5 w-5 shrink-0 text-slate-500" /><div><h3 className="text-sm font-semibold text-slate-900">{title}</h3><p className="mt-1 text-sm text-slate-600">{text}</p></div></div>; }
function SectionTitle({ icon: Icon, title }: { icon: typeof Circle; title: string }) { return <div className="flex items-center gap-2"><Icon className="h-4 w-4 text-slate-500" /><h3 className="text-sm font-semibold text-slate-900">{title}</h3></div>; }

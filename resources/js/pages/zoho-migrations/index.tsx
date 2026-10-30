import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { toast } from 'sonner';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import {
    AlertTriangle, ArrowUpRight, Check, CheckCircle2, ChevronRight, Circle,
    Clock3, Download, FileSpreadsheet, History, Import, Info, Loader2, LockKeyhole,
    ClipboardPaste, Plus, RefreshCw, Trash2, Upload, XCircle,
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

type UploadGuideFile = {
    datasets: string[];
    title: string;
    where: string[];
    note?: string;
    optional?: boolean;
};
type UploadGuide = { files: UploadGuideFile[]; tips: string[] };

type Stage = {
    key: string;
    title: string;
    description: string;
    position: number;
    supported: boolean;
    manual_entry?: boolean;
    executable?: boolean;
    multi_source?: boolean;
    top_up?: boolean;
    status: string;
    locked: boolean;
    accepted_count: number;
    rejected_count: number;
    warning_count: number;
    completed_at: string | null;
    summary?: { no_records_used?: boolean; no_taxes_used?: boolean; existing_records_used?: boolean } | null;
    destination_url: string | null;
    upload_guide?: UploadGuide | null;
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

    const batchesRef = useRef(batches);
    batchesRef.current = batches;

    const refreshBatch = async (batchId: number, announceCompletions = true) => {
        const response = await axios.get<Batch>(route('zoho-migrations.show', batchId));
        if (announceCompletions) {
            const previous = batchesRef.current.find(batch => batch.id === batchId);
            // A later stage shown as locked while an earlier one is redone (a newer export) was not imported again.
            response.data.stages
                .filter(stage => stage.status === 'completed' && previous?.stages.some(old => old.key === stage.key && old.status !== 'completed' && old.status !== 'locked'))
                .forEach(stage => toast.success(`${stage.title} imported successfully`));
        }
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

    const removeFile = async (file: MigrationFile) => {
        if (!selectedBatch) return;
        setBusy(true);
        setError(null);
        try {
            await axios.delete(route('zoho-migrations.files.destroy', [selectedBatch.id, file.id]));
            toast.success(`${file.original_filename} removed`);
            await refreshBatch(selectedBatch.id, false);
        } catch (exception: any) {
            setError(exception.response?.data?.message ?? 'The file could not be removed.');
        } finally {
            setBusy(false);
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

    // A dry run only validates the file; a real run imports it and advances the journey like the other manual imports.
    const uploadManualPrerequisite: ManualUpload = async (file, dryRun) => {
        if (!selectedBatch || !selectedStage) throw new Error('Select a migration stage first.');
        const data = new FormData();
        data.append('file', file);
        data.append('dry_run', dryRun ? '1' : '0');
        if (!dryRun) data.append('accuracy_confirmed', '1');
        const response = await axios.post(route('zoho-migrations.stages.manual-upload', [selectedBatch.id, selectedStage.key]), data, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (!dryRun) {
            await refreshBatch(selectedBatch.id, false);
            if (response.data.rejected === 0) toast.success(`${selectedStage.title} imported successfully`);
        }

        return response.data;
    };

    const importManualPrerequisite = async (pastedData: string, noRecordsUsed = false) => {
        if (!selectedBatch || !selectedStage) return;
        const response = await axios.post<{ rejected: number }>(route('zoho-migrations.stages.manual-prerequisite', [selectedBatch.id, selectedStage.key]), {
            pasted_data: pastedData,
            no_records_used: noRecordsUsed,
            accuracy_confirmed: true,
        });
        await refreshBatch(selectedBatch.id, false);
        if (response.data.rejected === 0) {
            toast.success(!noRecordsUsed ? `${selectedStage.title} imported successfully`
                : rowEntryConfigs[selectedStage.key]?.none?.toast ?? `${selectedStage.title} confirmed successfully`);
        }
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
                            <StagePanel stage={selectedStage} batch={selectedBatch} permissions={permissions} busy={busy} error={error} uploadProgress={uploadProgress} fileInput={fileInput} onUpload={uploadFile} onRemove={removeFile} onValidate={validateStage} onExecute={executeStage} onManualImport={importManualPrerequisite} onManualUpload={uploadManualPrerequisite} />
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

function StagePanel({ stage, batch, permissions, busy, error, uploadProgress, fileInput, onUpload, onRemove, onValidate, onExecute, onManualImport, onManualUpload }: { stage: Stage | null; batch: Batch; permissions: Props['permissions']; busy: boolean; error: string | null; uploadProgress: number | null; fileInput: React.RefObject<HTMLInputElement>; onUpload: (file: File) => void; onRemove: (file: MigrationFile) => Promise<void>; onValidate: () => void; onExecute: () => void; onManualImport: (pastedData: string, noRecordsUsed?: boolean) => Promise<void>; onManualUpload: ManualUpload }) {
    const [guideOpen, setGuideOpen] = useState(false);
    const [removing, setRemoving] = useState<MigrationFile | null>(null);
    if (!stage) return null;
    const style = statusStyle[stage.status] ?? statusStyle.not_started;
    const latestFile = [...stage.files].reverse().find(file => file.status !== 'superseded');
    // Steps that take several kinds of file keep one current file of each kind.
    const currentFiles = stage.multi_source ? stage.files.filter(file => file.status !== 'superseded') : (latestFile ? [latestFile] : []);
    const stageIssues = batch.issues?.filter(issue => issue.stage_key === stage.key) ?? [];
    const stageEvents = batch.events?.filter(event => event.stage_key === stage.key) ?? [];
    // An imported step takes no new file, except a top-up step (a newer full export).
    const canUpload = permissions.upload && stage.supported && !stage.manual_entry && !stage.locked && stage.key !== 'configuration' && (stage.status !== 'completed' || Boolean(stage.top_up));
    // Files can be taken off until the step is imported (and not while a check or import is running).
    const canRemove = (file: MigrationFile) => permissions.upload && file.status !== 'superseded' && !['completed', 'processing'].includes(stage.status);
    const guide = stage.upload_guide;
    const topUp = Boolean(stage.top_up && stage.status === 'completed');
    // A completed top-up step shows its imported file as done; a newer export is optional.
    const chooseFile = () => guide ? setGuideOpen(true) : fileInput.current?.click();
    const rowEntry = rowEntryConfigs[stage.key];
    // no_taxes_used is the flag stored by taxes stages completed before the flag was generalised.
    const confirmedUnused = Boolean(stage.summary?.no_records_used || stage.summary?.no_taxes_used);
    const usedExisting = Boolean(stage.summary?.existing_records_used);

    return <section className="min-w-0 bg-slate-50 px-4 py-6 sm:px-7 lg:px-8">
        <div className="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
            <div><p className={`text-xs font-semibold uppercase ${style.text}`}>{style.label}</p><h2 className="mt-1 text-xl font-semibold text-slate-950">{stage.title}</h2><p className="mt-2 max-w-2xl text-sm text-slate-600">{stage.description}</p></div>
            {stage.destination_url && <Button variant="outline" asChild><a href={stage.destination_url}>View ERP records<ArrowUpRight className="ml-2 h-4 w-4" /></a></Button>}
        </div>

        {stage.status === 'unsupported' && <Notice icon={Clock3} title="Not yet supported" text="This stage remains visible in the complete migration sequence, but execution is disabled until its Zoho profile and reconciliation rules are verified." />}
        {stage.locked && <Notice icon={LockKeyhole} title="Complete the previous stage" text="Guided migrations enforce the approved top-to-bottom order. This stage will unlock automatically after the previous stage reconciles." />}
        {stage.key === 'configuration' && <Notice icon={CheckCircle2} title="Configuration complete" text={`${batch.base_currency} base currency, ${batch.accounting_mode === 'rebuild' ? 'document rebuild' : 'full ledger history'} accounting mode, and ${batch.workflow_mode} workflow are recorded for this migration.`} />}
        {stage.status === 'processing' && <Notice icon={Loader2} title="Import processing" text="The source is being inspected, validated, or imported. This page updates automatically; you can safely leave it open." />}
        {stage.status === 'ready' && (stage.executable || stage.manual_entry) && <Notice icon={CheckCircle2} title="Validation passed" text={`${stage.accepted_count} rows passed validation. Review the counts, then import them into ERP.`} />}
        {stage.status === 'ready' && !stage.executable && !stage.manual_entry && <Notice icon={Clock3} title="Checked, import not available yet" text={`${stage.accepted_count} rows passed the file checks, but importing this stage into ERP is not built yet. The file stays attached and can be imported once it is.`} />}
        {stage.status === 'completed' && confirmedUnused && stage.key === 'inventory_cutover' && <Notice icon={CheckCircle2} title="No stock confirmed" text="This company was confirmed as holding no stock (all items are services). No opening stock was recorded. The next journey stage is now available." />}
        {stage.status === 'completed' && confirmedUnused && stage.key !== 'inventory_cutover' && <Notice icon={CheckCircle2} title={`No ${rowEntry?.nounPlural ?? 'records'} confirmed`} text={`This migration was confirmed as using no ${rowEntry?.nounPlural ?? 'records'}. No ${rowEntry?.noun ?? ''} records were created. The next journey stage is now available.`} />}
        {stage.status === 'completed' && usedExisting && <Notice icon={CheckCircle2} title="Existing records confirmed" text={`This step was completed with the ${rowEntry?.nounPlural ?? 'categories and units'} already in ERP. No new records were created. The next journey stage is now available.`} />}
        {stage.status === 'completed' && !confirmedUnused && !usedExisting && <Notice icon={CheckCircle2} title="Import completed" text={`${stage.accepted_count} validated rows were processed successfully. The next journey stage is now available.`} />}

        {stage.manual_entry && !stage.locked && permissions.upload && (
            rowEntry ? <RowEntryPanel key={stage.key} config={rowEntry} busy={busy} onImport={onManualImport} />
            : stage.key === 'categories_units' ? <CategoriesUnitsPanel key={stage.key} busy={busy} onImport={onManualImport} />
            : stage.key === 'items' ? <ProductEntryPanel key={stage.key} batchId={batch.id} busy={busy} onImport={onManualImport} onUpload={onManualUpload} />
            : <ManualPrerequisitePanel key={stage.key} stage={stage} batchId={batch.id} busy={busy} onImport={onManualImport} />
        )}

        {canUpload && (
            <div className="mt-6 border border-dashed border-slate-300 bg-white p-5">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><h3 className="text-sm font-semibold">Upload original Zoho export</h3><p className="mt-1 text-xs text-slate-500">CSV or XLSX, up to 10 MB and 10,000 rows.{guide && <> <button type="button" className="font-medium text-sky-700 hover:underline" onClick={() => setGuideOpen(true)}>Which file do I need?</button></>}</p>{stage.top_up && stage.status === 'completed' ? <p className="mt-1 text-xs font-medium text-amber-700">Already imported. To add newer records, upload a new full export: records imported earlier are checked and skipped, and only the new ones are imported.</p> : latestFile && <p className="mt-1 text-xs font-medium text-amber-700">{stage.multi_source ? 'This step takes more than one file. Adding a file of the same kind replaces the earlier one; the others stay.' : 'Choosing another file replaces the current unimported source and restarts validation.'}</p>}</div>
                    <div><input ref={fileInput} className="hidden" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" onChange={event => event.target.files?.[0] && onUpload(event.target.files[0])} /><Button disabled={busy} onClick={chooseFile}>{busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}{stage.top_up && stage.status === 'completed' ? 'Upload newer export' : latestFile ? (stage.multi_source ? 'Add file' : 'Replace file') : 'Choose file'}</Button></div>
                </div>
                {guide && <UploadChecklist guide={guide} files={currentFiles} multiple={Boolean(stage.multi_source)} completed={stage.status === 'completed'} />}
                {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
                {uploadProgress !== null && <div className="mt-4"><div className="mb-1 flex justify-between text-xs font-medium text-slate-600"><span>Uploading source</span><span>{uploadProgress}%</span></div><div className="h-2 overflow-hidden bg-slate-200"><div className="h-full bg-emerald-500 transition-all" style={{ width: `${uploadProgress}%` }} /></div></div>}
            </div>
        )}

        {guide && <UploadGuideDialog open={guideOpen} onOpenChange={setGuideOpen} stage={stage} guide={guide} files={currentFiles} topUp={topUp} onChoose={() => { setGuideOpen(false); fileInput.current?.click(); }} />}
        <ConfirmationDialog
            open={removing !== null}
            onOpenChange={open => !open && setRemoving(null)}
            title="Remove this file?"
            message={removing ? `${removing.original_filename} will be taken off this step before anything is imported. ${stage.multi_source ? 'The other files stay and are checked again.' : 'You can then upload the right file.'}` : ''}
            confirmText="Remove file"
            variant="destructive"
            onConfirm={() => { if (removing) void onRemove(removing); setRemoving(null); }}
        />

        {stage.status === 'processing' && <div className="mt-5 h-2 overflow-hidden bg-slate-200"><div className="h-full w-1/2 animate-pulse bg-sky-500" /></div>}

        {latestFile && <>
            <div className="mt-5 flex flex-wrap gap-2">
                {stage.status === 'review_required' && <Button onClick={onValidate} disabled={busy}>{busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <RefreshCw className="mr-2 h-4 w-4" />}Recheck corrections</Button>}
                {stage.status === 'ready' && permissions.execute && stage.executable && <Button onClick={onExecute} disabled={busy}>{busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Import className="mr-2 h-4 w-4" />}{stage.key === 'reconciliation' ? 'Confirm reconciliation' : `Import ${stage.accepted_count} rows`}</Button>}
                {currentFiles.map(file => <span key={file.id} className="inline-flex"><Button variant="outline" className={canRemove(file) ? 'rounded-r-none' : ''} asChild><a href={route('zoho-migrations.files.source', [batch.id, file.id])}><Download className="mr-2 h-4 w-4" />{currentFiles.length > 1 ? file.original_filename : 'Source file'}</a></Button>{canRemove(file) && <Button variant="outline" className="rounded-l-none border-l-0 px-3 text-red-600 hover:bg-red-50 hover:text-red-700" disabled={busy} title={`Remove ${file.original_filename}`} aria-label={`Remove ${file.original_filename}`} onClick={() => setRemoving(file)}><Trash2 className="h-4 w-4" /></Button>}</span>)}
                {stageIssues.length > 0 && <Button variant="outline" asChild><a href={route('zoho-migrations.stages.issues', [batch.id, stage.key])}><Download className="mr-2 h-4 w-4" />Issue report</a></Button>}
            </div>
            {currentFiles.map(file => <FilePreview key={file.id} file={file} />)}
        </>}
        {stageIssues.length > 0 && <div className="mt-6"><SectionTitle icon={AlertTriangle} title="Correct these before import" /><p className="mt-2 text-sm text-slate-600">Fix the source Excel and upload it again, or add the missing reference in ERP and choose Recheck corrections. No rows will be imported while errors remain.</p><div className="mt-3 divide-y divide-slate-200 border-y border-slate-200 bg-white">{stageIssues.map((issue: any) => <div key={issue.id} className="flex items-start justify-between gap-4 px-4 py-3"><div><p className="text-sm font-medium text-slate-800">{issue.message}</p>{issue.source_value && <p className="mt-1 text-xs text-slate-500">{issue.field}: {issue.source_value}</p>}</div>{issue.resolution_url && <a className="shrink-0 text-sm font-medium text-sky-700" href={issue.resolution_url}>Fix in ERP</a>}</div>)}</div></div>}
        <div className="mt-7"><SectionTitle icon={History} title="Activity" /><div className="mt-3 space-y-3">{stageEvents.length ? stageEvents.map(event => <div key={event.id} className="flex gap-3 text-sm"><span className="mt-1 h-2 w-2 rounded-full bg-slate-300" /><div><p className="text-slate-700">{event.message}</p><p className="mt-0.5 text-xs text-slate-400">{new Date(event.created_at).toLocaleString()}</p></div></div>) : <p className="text-sm text-slate-500">No activity recorded for this stage.</p>}</div></div>
    </section>;
}

const manualHeaders: Record<string, string[]> = {
    items: ['name', 'sku', 'type', 'category', 'unit', 'taxes', 'sale_price', 'purchase_price', 'description'],
    inventory_cutover: ['sku', 'warehouse', 'quantity'],
};

type ManualImport = (pastedData: string, noRecordsUsed?: boolean) => Promise<void>;
type UploadPreview = {
    total: number;
    valid: number;
    invalid: number;
    new: number;
    existing: number;
    rows: Array<{ row_number: number; name: string; sku: string; result: 'new' | 'existing' | 'invalid'; errors: string[] }>;
};
type ImportSummary = { accepted: number; rejected: number; imported: number; updated: number; linked: number };
type ManualUpload = (file: File, dryRun: boolean) => Promise<UploadPreview | ImportSummary>;
type RowEntryField = {
    key: string;
    label: string;
    placeholder?: string;
    kind?: 'text' | 'number' | 'color';
    defaultValue?: string;
    palette?: string[];
    optional?: boolean;
    // Layout only: a CSS grid column width, and whether the field sits on a row's second line.
    width?: string;
    line?: 2;
};
type EntryRow = Record<string, string>;
type RowEntryConfig = {
    fields: RowEntryField[];
    noun: string;
    nounPlural: string;
    // Only stages that may legitimately need no new records offer the "none" checkbox.
    none?: { title: string; text: string; button: string; toast: string };
    confirmText: string;
};

const rowEntryConfigs: Record<string, RowEntryConfig> = {
    taxes: {
        fields: [
            { key: 'tax_name', label: 'Tax name', placeholder: 'Tax name, e.g. VAT 5%' },
            { key: 'rate', label: 'Tax rate', placeholder: 'Rate %', kind: 'number' },
        ],
        noun: 'tax',
        nounPlural: 'taxes',
        none: { title: 'No taxes were used', text: 'No tax records will be created, and later document imports must contain no tax allocations.', button: 'Confirm no taxes', toast: 'No taxes confirmed successfully' },
        confirmText: 'I checked every tax name and rate against the approved source.',
    },
    warehouses: {
        fields: [
            { key: 'name', label: 'Warehouse name', placeholder: 'Warehouse name', width: 'minmax(0,2fr)' },
            { key: 'address', label: 'Address', placeholder: 'Building, street, area', width: 'minmax(0,3fr)' },
            { key: 'city', label: 'City', placeholder: 'City', width: 'minmax(0,1.5fr)' },
            { key: 'zip_code', label: 'Zip code', placeholder: 'Zip code', line: 2 },
            { key: 'phone', label: 'Phone', placeholder: 'Phone (optional)', optional: true, line: 2 },
            { key: 'email', label: 'Email', placeholder: 'Email (optional)', optional: true, line: 2, width: 'minmax(0,1.5fr)' },
        ],
        noun: 'warehouse',
        nounPlural: 'warehouses',
        none: { title: 'Only the warehouses already in ERP are used', text: 'No new warehouses will be created. The default warehouse from the migration setup stays available.', button: 'Continue with existing warehouses', toast: 'Existing warehouses confirmed successfully' },
        confirmText: 'I checked every warehouse name and address against the approved source.',
    },
};

const categoryFields: RowEntryField[] = [
    { key: 'name', label: 'Category name', placeholder: 'Category name, e.g. Consulting' },
    // Starts with the ERP's default category green.
    { key: 'color', label: 'Category color', kind: 'color', palette: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'] },
];
const unitFields: RowEntryField[] = [{ key: 'name', label: 'Unit name', placeholder: 'Unit name, e.g. Pcs, Kg, Hours' }];

// A color always has a value and optional fields may stay empty, so only required typed fields decide blank or complete.
const typedFields = (fields: RowEntryField[]) => fields.filter(field => field.kind !== 'color' && !field.optional);
// A palette field takes the first colour no other row uses, so each new row looks distinct.
const defaultFor = (field: RowEntryField, otherRows: EntryRow[]) => {
    if (!field.palette) return field.defaultValue ?? '';
    const used = new Set(otherRows.map(row => (row[field.key] ?? '').toLowerCase()));
    return field.palette.find(color => !used.has(color)) ?? field.palette[otherRows.length % field.palette.length];
};
const emptyEntryRow = (fields: RowEntryField[], otherRows: EntryRow[] = []): EntryRow => Object.fromEntries(fields.map(field => [field.key, defaultFor(field, otherRows)]));
const isBlankEntryRow = (fields: RowEntryField[], row: EntryRow) => typedFields(fields).every(field => (row[field.key] ?? '').trim() === '');
const isCompleteEntryRow = (fields: RowEntryField[], row: EntryRow) => typedFields(fields).every(field => (row[field.key] ?? '').trim() !== '');
const cleanCell = (value: string) => value.replace(/[\t\n\r]+/g, ' ').trim();
const importErrorMessage = (exception: any, fallback: string) => {
    const errors = exception.response?.data?.errors;
    return errors ? Object.values(errors).flat().join(' ') : (exception.response?.data?.message ?? fallback);
};

function RowEditor({ title, noun, fields, rows, onChange }: { title: string; noun: string; fields: RowEntryField[]; rows: EntryRow[]; onChange: (rows: EntryRow[]) => void }) {
    const updateRow = (index: number, field: string, value: string) => {
        onChange(rows.map((row, rowIndex) => rowIndex === index ? { ...row, [field]: value } : row));
    };
    // Pasting several lines copied from Excel fills one row per line, starting at the row pasted into.
    const pasteRows = (index: number, event: React.ClipboardEvent<HTMLInputElement>) => {
        const lines = event.clipboardData.getData('text').replace(/\r/g, '').split('\n').filter(line => line.trim() !== '');
        if (lines.length < 2 && !lines[0]?.includes('\t')) return;
        event.preventDefault();
        const kept = rows.filter((_, rowIndex) => rowIndex !== index);
        const pasted: EntryRow[] = [];
        lines.forEach(line => {
            const cells = line.split('\t');
            pasted.push(Object.fromEntries(fields.map((field, cellIndex) => [field.key, (cells[cellIndex] ?? '').trim() || defaultFor(field, [...kept, ...pasted])])));
        });
        onChange([...rows.slice(0, index), ...pasted, ...rows.slice(index + 1)]);
    };
    const columnWidth = (field: RowEntryField) => field.width ?? (field.kind === 'number' ? '140px' : field.kind === 'color' ? '64px' : 'minmax(0,1fr)');
    const gridTemplateColumns = (lineFields: RowEntryField[]) => [...lineFields.map(columnWidth), '40px'].join(' ');
    // Fields marked for line 2 turn each row into a small two-line card instead of one long row.
    const firstLine = fields.filter(field => field.line !== 2);
    const secondLine = fields.filter(field => field.line === 2);
    const pasteHint = fields.length > 1
        ? `Type each ${noun}, or paste rows copied from Excel with the columns in this order: ${fields.map(field => field.label).join(', ')}.`
        : `Type each ${noun}, or paste several lines copied from Excel.`;
    const removeButton = (index: number) => <Button type="button" variant="ghost" size="icon" title={`Remove ${noun} row`} disabled={rows.length === 1} onClick={() => onChange(rows.filter((_, rowIndex) => rowIndex !== index))}><Trash2 className="h-4 w-4" /></Button>;
    const input = (field: RowEntryField, row: EntryRow, index: number) => field.kind === 'color'
        ? <input type="color" aria-label={`${field.label} ${index + 1}`} value={row[field.key] || defaultFor(field, [])} onChange={event => updateRow(index, field.key, event.target.value)} className="h-10 w-full cursor-pointer rounded-md border border-slate-200 bg-white p-1" />
        : <Input aria-label={`${field.label} ${index + 1}`} value={row[field.key] ?? ''} onChange={event => updateRow(index, field.key, event.target.value)} onPaste={event => pasteRows(index, event)} placeholder={field.placeholder} {...(field.kind === 'number' ? { type: 'number', min: '0', max: '100', step: '0.01' } : {})} />;
    const label = (field: RowEntryField) => <>{field.label}{!field.optional && field.kind !== 'color' && <span className="text-red-500"> *</span>}</>;
    // Stacks on phones; the set column widths apply from the md breakpoint.
    const line = (lineFields: RowEntryField[], row: EntryRow, index: number, trailing: React.ReactNode) =>
        <div className="grid grid-cols-1 gap-2 md:[grid-template-columns:var(--row-columns)]" style={{ '--row-columns': gridTemplateColumns(lineFields) } as React.CSSProperties}>
            {lineFields.map(field => <div key={field.key} className="min-w-0"><span className="mb-1 block text-xs font-medium text-slate-500">{label(field)}</span>{input(field, row, index)}</div>)}
            <div className="flex items-end">{trailing}</div>
        </div>;

    return <div>
        <div className="mb-3 flex items-center justify-between gap-3">
            <div><h3 className="text-sm font-semibold text-slate-900">{title}</h3><p className="mt-0.5 text-xs text-slate-500">{pasteHint}</p></div>
            <Button type="button" variant="outline" onClick={() => onChange([...rows, emptyEntryRow(fields, rows)])}><Plus className="mr-2 h-4 w-4" />Add {noun}</Button>
        </div>
        {secondLine.length > 0
            ? <div className="space-y-3">{rows.map((row, index) => <div key={index} className="space-y-2 border border-slate-200 p-3">
                {line(firstLine, row, index, removeButton(index))}
                {line(secondLine, row, index, null)}
            </div>)}</div>
            : <>
                {fields.length > 2 && <div className="mb-1 grid gap-2 text-xs font-medium text-slate-500" style={{ gridTemplateColumns: gridTemplateColumns(fields) }}>
                    {fields.map(field => <span key={field.key}>{label(field)}</span>)}<span />
                </div>}
                <div className="space-y-2">{rows.map((row, index) => <div key={index} className="grid gap-2" style={{ gridTemplateColumns: gridTemplateColumns(fields) }}>
                    {fields.map(field => <div key={field.key} className="min-w-0">{input(field, row, index)}</div>)}
                    {removeButton(index)}
                </div>)}</div>
            </>}
    </div>;
}

function ConfirmCheckbox({ checked, onChange, text }: { checked: boolean; onChange: (checked: boolean) => void; text: string }) {
    return <label className="mt-4 flex items-start gap-3 text-sm text-slate-700"><input type="checkbox" checked={checked} onChange={event => onChange(event.target.checked)} className="mt-0.5 h-4 w-4" /><span>{text}</span></label>;
}

function SubmitButton({ disabled, busy, label, onClick }: { disabled: boolean; busy: boolean; label: string; onClick: () => void }) {
    return <Button className="mt-4" disabled={disabled || busy} onClick={onClick}>
        {busy ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}
        {label}
    </Button>;
}

function RowEntryPanel({ config, busy, onImport }: { config: RowEntryConfig; busy: boolean; onImport: ManualImport }) {
    const [rows, setRows] = useState<EntryRow[]>([emptyEntryRow(config.fields)]);
    const [noRecordsUsed, setNoRecordsUsed] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [localBusy, setLocalBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const submit = async () => {
        setLocalBusy(true);
        setLocalError(null);
        try {
            const pastedData = noRecordsUsed ? '' : [
                config.fields.map(field => field.key).join('\t'),
                ...rows.map(row => config.fields.map(field => cleanCell(row[field.key] ?? '')).join('\t')),
            ].join('\n');
            await onImport(pastedData, noRecordsUsed);
            setConfirmed(false);
        } catch (exception: any) {
            setLocalError(importErrorMessage(exception, `The ${config.noun} data could not be imported.`));
        } finally {
            setLocalBusy(false);
        }
    };
    const hasCompleteRows = rows.length > 0 && rows.every(row => isCompleteEntryRow(config.fields, row));

    return <div className="mt-6 border-y border-slate-200 bg-white px-4 py-5">
        {config.none && <label className="mb-5 flex items-start gap-3 border-b border-slate-200 pb-4 text-sm text-slate-700">
            <input type="checkbox" checked={noRecordsUsed} onChange={event => { setNoRecordsUsed(event.target.checked); setConfirmed(false); }} className="mt-0.5 h-4 w-4" />
            <span><strong className="block font-semibold text-slate-900">{config.none.title}</strong>{config.none.text}</span>
        </label>}
        {!noRecordsUsed && <RowEditor title={`${config.noun[0].toUpperCase()}${config.noun.slice(1)} rows`} noun={config.noun} fields={config.fields} rows={rows} onChange={next => { setRows(next); setConfirmed(false); }} />}
        {!noRecordsUsed && <ConfirmCheckbox checked={confirmed} onChange={setConfirmed} text={config.confirmText} />}
        {localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
        <SubmitButton disabled={busy || (!noRecordsUsed && (!confirmed || !hasCompleteRows))} busy={busy || localBusy} onClick={submit}
            label={noRecordsUsed && config.none ? config.none.button : `Import ${rows.length} ${config.noun} ${rows.length === 1 ? 'row' : 'rows'}`} />
    </div>;
}

function CategoriesUnitsPanel({ busy, onImport }: { busy: boolean; onImport: ManualImport }) {
    const [categories, setCategories] = useState<EntryRow[]>([emptyEntryRow(categoryFields)]);
    const [units, setUnits] = useState<EntryRow[]>([emptyEntryRow(unitFields)]);
    const [confirmed, setConfirmed] = useState(false);
    const [localBusy, setLocalBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    // Fully blank rows are ignored, so a section can be left empty when its records already exist in ERP.
    const filledCategories = categories.filter(row => !isBlankEntryRow(categoryFields, row));
    const filledUnits = units.filter(row => !isBlankEntryRow(unitFields, row));
    const plural = (count: number, singular: string, pluralWord: string) => `${count} ${count === 1 ? singular : pluralWord}`;

    // With both sections empty the step completes from the categories and units already in ERP.
    const useExisting = filledCategories.length + filledUnits.length === 0;

    const submit = async () => {
        setLocalBusy(true);
        setLocalError(null);
        try {
            const pastedData = useExisting ? '' : [
                'record_type\tname\tcolor',
                ...filledCategories.map(row => ['category', cleanCell(row.name ?? ''), cleanCell(row.color ?? '')].join('\t')),
                ...filledUnits.map(row => ['unit', cleanCell(row.name ?? ''), ''].join('\t')),
            ].join('\n');
            await onImport(pastedData, useExisting);
            setConfirmed(false);
        } catch (exception: any) {
            setLocalError(importErrorMessage(exception, 'The categories and units could not be imported.'));
        } finally {
            setLocalBusy(false);
        }
    };

    return <div className="mt-6 border-y border-slate-200 bg-white px-4 py-5">
        <RowEditor title="Categories" noun="category" fields={categoryFields} rows={categories} onChange={next => { setCategories(next); setConfirmed(false); }} />
        <div className="mt-6 border-t border-slate-200 pt-5">
            <RowEditor title="Units" noun="unit" fields={unitFields} rows={units} onChange={next => { setUnits(next); setConfirmed(false); }} />
        </div>
        <p className="mt-4 text-xs text-slate-500">Leave a section empty only if those records already exist in ERP. Leave both empty to continue with the categories and units already in ERP. Products need a category, and every product (not service) needs a unit.</p>
        <ConfirmCheckbox checked={confirmed} onChange={setConfirmed} text={useExisting ? 'I checked that the categories and units already in ERP are the ones to use.' : 'I checked every category and unit name against the approved source.'} />
        {localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
        <SubmitButton disabled={busy || !confirmed} busy={busy || localBusy} onClick={submit}
            label={useExisting ? 'Continue with existing categories and units' : `Import ${plural(filledCategories.length, 'category', 'categories')} and ${plural(filledUnits.length, 'unit', 'units')}`} />
    </div>;
}

type ProductEntry = { type: string; name: string; sku: string; tax: string; category: string; unit: string; sale_price: string; purchase_price: string; description: string };
type ReferenceData = {
    categories: string[];
    units: string[];
    taxes: Array<{ tax_name: string; rate: number | string }>;
    stock_items: Array<{ sku: string; name: string }>;
    warehouses: string[];
    stock: Array<{ sku: string; warehouse: string; quantity: number | string }>;
};

const emptyProduct = (): ProductEntry => ({ type: 'product', name: '', sku: '', tax: 'none', category: '', unit: '', sale_price: '', purchase_price: '0', description: '' });
// Mirrors the ERP item form: services have no unit; everything else needs one.
const isCompleteProduct = (product: ProductEntry) => product.name.trim() !== '' && product.sku.trim() !== '' && product.category !== ''
    && product.sale_price.trim() !== '' && product.purchase_price.trim() !== '' && (product.type === 'service' || product.unit !== '');

function FormField({ label, htmlFor, required, children }: { label: string; htmlFor?: string; required?: boolean; children: React.ReactNode }) {
    return <div className="min-w-0"><Label htmlFor={htmlFor} className="mb-1.5 block text-sm font-medium text-slate-800">{label}{required && <span className="text-red-500"> *</span>}</Label>{children}</div>;
}

// Mirrors the ERP Items import: template, upload, validation counts and row results, then import.
function ProductUploadPanel({ batchId, busy, onUpload }: { batchId: number; busy: boolean; onUpload: ManualUpload }) {
    const [file, setFile] = useState<File | null>(null);
    const [inputKey, setInputKey] = useState(0);
    const [preview, setPreview] = useState<UploadPreview | null>(null);
    const [result, setResult] = useState<ImportSummary | null>(null);
    const [confirmed, setConfirmed] = useState(false);
    const [localBusy, setLocalBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    const reset = () => {
        setFile(null);
        setInputKey(key => key + 1);
        setPreview(null);
        setConfirmed(false);
        setLocalError(null);
    };
    const run = async (dryRun: boolean) => {
        if (!file) return;
        setLocalBusy(true);
        setLocalError(null);
        try {
            if (dryRun) {
                setResult(null);
                setPreview(await onUpload(file, true) as UploadPreview);
            } else {
                setResult(await onUpload(file, false) as ImportSummary);
                reset();
            }
        } catch (exception: any) {
            setLocalError(importErrorMessage(exception, dryRun ? 'The file could not be validated.' : 'The file could not be imported.'));
        } finally {
            setLocalBusy(false);
        }
    };
    const downloadErrorReport = () => {
        if (!preview) return;
        // Leading formula characters are escaped so the report is safe to open in Excel.
        const cell = (value: string | number) => {
            const text = String(value);
            return `"${(/^[=+\-@]/.test(text) ? `'${text}` : text).replace(/"/g, '""')}"`;
        };
        const lines = [['row_number', 'name', 'sku', 'errors'], ...preview.rows.filter(row => row.result === 'invalid')
            .map(row => [row.row_number, row.name, row.sku, row.errors.join(' | ')])].map(columns => columns.map(cell).join(','));
        const url = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/csv' }));
        const link = document.createElement('a');
        link.href = url;
        link.download = 'products-services-errors.csv';
        link.click();
        URL.revokeObjectURL(url);
    };
    const templateUrl = (format: 'xlsx' | 'csv') => route('zoho-migrations.stages.manual-template', { zohoMigration: batchId, stageKey: 'items', format });
    const sortedRows = preview ? [...preview.rows].sort((a, b) => Number(b.result === 'invalid') - Number(a.result === 'invalid')).slice(0, 100) : [];

    return <div className="mt-4 border-y border-slate-200 bg-white px-4 py-5">
        {result && <div className={`mb-4 border p-3 text-sm ${result.rejected > 0 ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'}`}>
            {result.rejected > 0
                ? `${result.rejected} rows failed validation, so nothing was imported. See the issues below.`
                : `Imported ${result.imported}, updated ${result.updated} and linked ${result.linked} existing items.`}
        </div>}

        {!preview && <>
            <p className="text-sm text-slate-600">Download the template, fill one row per product or service, then upload it. The template lists the categories, units and taxes already in ERP. Files may contain up to 10,000 rows.</p>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button variant="outline" size="sm" asChild><a href={templateUrl('xlsx')}><Download className="mr-2 h-4 w-4" />XLSX Template</a></Button>
                <Button variant="outline" size="sm" asChild><a href={templateUrl('csv')}><Download className="mr-2 h-4 w-4" />CSV Template</a></Button>
            </div>
            <div className="mt-4 max-w-md">
                <Label htmlFor="items-upload-file" className="mb-1.5 block text-sm font-medium text-slate-800">CSV or XLSX file</Label>
                <Input key={inputKey} id="items-upload-file" type="file" accept=".csv,.xlsx" onChange={event => { setFile(event.target.files?.[0] ?? null); setLocalError(null); }} />
            </div>
            <SubmitButton disabled={busy || !file} busy={localBusy} onClick={() => run(true)} label="Validate File" />
        </>}

        {preview && <div>
            <p className="text-sm font-medium text-slate-900">{file?.name}</p>
            <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                {([['Total', preview.total], ['Valid', preview.valid], ['Invalid', preview.invalid], ['New', preview.new], ['Existing', preview.existing]] as const).map(([name, value]) =>
                    <div key={name} className="border border-slate-200 p-3 text-center"><div className="text-xl font-semibold text-slate-900">{value}</div><div className="text-xs text-slate-500">{name}</div></div>)}
            </div>
            <div className="mt-4 max-h-64 overflow-auto border border-slate-200">
                <table className="w-full text-sm">
                    <thead className="sticky top-0 bg-slate-50"><tr><th className="p-2 text-left">Row</th><th className="p-2 text-left">Result</th><th className="p-2 text-left">Details</th></tr></thead>
                    <tbody>{sortedRows.map(row => <tr key={row.row_number} className="border-t border-slate-200">
                        <td className="p-2">{row.row_number}</td>
                        <td className={`p-2 capitalize ${row.result === 'invalid' ? 'font-medium text-red-700' : 'text-slate-700'}`}>{row.result}</td>
                        <td className="p-2 text-slate-600">{row.errors.join(' ') || [row.name, row.sku].filter(Boolean).join(' | ')}</td>
                    </tr>)}</tbody>
                </table>
            </div>
            {preview.rows.length > sortedRows.length && <p className="mt-2 text-xs text-slate-500">Showing the first {sortedRows.length} rows, invalid rows first.</p>}
            {preview.existing > 0 && <p className="mt-3 text-xs text-slate-500">Existing SKUs follow this migration's duplicate setting.</p>}

            {preview.invalid > 0
                ? <div className="mt-4 border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    Fix the {preview.invalid} invalid {preview.invalid === 1 ? 'row' : 'rows'} in your file, then choose it again. Nothing is imported while errors remain.
                    <div className="mt-2"><Button variant="outline" size="sm" onClick={downloadErrorReport}><Download className="mr-2 h-4 w-4" />Download Error Report</Button></div>
                </div>
                : <ConfirmCheckbox checked={confirmed} onChange={setConfirmed} text="I checked every item, price, category, unit and tax in this file against the approved source." />}
            {localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
            <div className="flex flex-wrap gap-2">
                {preview.invalid === 0 && <SubmitButton disabled={busy || !confirmed || preview.valid === 0} busy={localBusy} onClick={() => run(false)} label={`Import ${preview.valid} ${preview.valid === 1 ? 'item' : 'items'}`} />}
                <Button type="button" variant="outline" className="mt-4" onClick={reset}>Choose Another File</Button>
            </div>
        </div>}
        {!preview && localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
    </div>;
}

function ProductEntryPanel({ batchId, busy, onImport, onUpload }: { batchId: number; busy: boolean; onImport: ManualImport; onUpload: ManualUpload }) {
    const [mode, setMode] = useState<'form' | 'upload'>('form');
    const [reference, setReference] = useState<ReferenceData | null>(null);
    const [referenceError, setReferenceError] = useState<string | null>(null);
    const [products, setProducts] = useState<ProductEntry[]>([emptyProduct()]);
    const [confirmed, setConfirmed] = useState(false);
    const [localBusy, setLocalBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);

    useEffect(() => {
        axios.get<ReferenceData>(route('zoho-migrations.reference-data', batchId))
            .then(response => setReference(response.data))
            .catch(() => setReferenceError('The categories, units and taxes could not be loaded. Refresh the page to try again.'));
    }, [batchId]);

    const update = (index: number, field: keyof ProductEntry, value: string) => {
        setProducts(current => current.map((product, productIndex) => productIndex !== index ? product : {
            ...product,
            [field]: value,
            ...(field === 'type' && value === 'service' ? { unit: '' } : {}),
        }));
        setConfirmed(false);
    };
    const submit = async () => {
        setLocalBusy(true);
        setLocalError(null);
        try {
            const pastedData = [
                manualHeaders.items.join('\t'),
                ...products.map(product => [
                    product.name, product.sku, product.type, product.category,
                    product.type === 'service' ? '' : product.unit,
                    product.tax === 'none' ? '' : product.tax,
                    product.sale_price, product.purchase_price, product.description,
                ].map(cleanCell).join('\t')),
            ].join('\n');
            await onImport(pastedData);
            setConfirmed(false);
        } catch (exception: any) {
            setLocalError(importErrorMessage(exception, 'The products and services could not be imported.'));
        } finally {
            setLocalBusy(false);
        }
    };
    const allComplete = products.every(isCompleteProduct);

    return <div className="mt-6">
        <div className="flex flex-wrap gap-2">
            <Button type="button" variant={mode === 'form' ? 'default' : 'outline'} onClick={() => setMode('form')}><Plus className="mr-2 h-4 w-4" />Enter one by one</Button>
            <Button type="button" variant={mode === 'upload' ? 'default' : 'outline'} onClick={() => setMode('upload')}><Upload className="mr-2 h-4 w-4" />Upload Excel file</Button>
        </div>

        {mode === 'upload' && <ProductUploadPanel batchId={batchId} busy={busy} onUpload={onUpload} />}

        {mode === 'form' && <div className="mt-4 border-y border-slate-200 bg-white px-4 py-5">
            {referenceError && <p className="mb-4 text-sm font-medium text-red-700">{referenceError}</p>}
            {reference && (reference.categories.length === 0 || reference.units.length === 0) && <p className="mb-4 text-sm font-medium text-amber-700">
                {reference.categories.length === 0 ? 'No categories exist yet. ' : ''}{reference.units.length === 0 ? 'No units exist yet. ' : ''}Complete the Categories and units step first.
            </p>}
            <div className="space-y-4">{products.map((product, index) => {
                const id = (field: string) => `product-${index}-${field}`;

                return <div key={index} className="border border-slate-200 p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="text-sm font-semibold text-slate-900">Item {index + 1}</h3>
                        <Button type="button" variant="ghost" size="icon" title="Remove item" disabled={products.length === 1} onClick={() => { setProducts(current => current.filter((_, productIndex) => productIndex !== index)); setConfirmed(false); }}><Trash2 className="h-4 w-4" /></Button>
                    </div>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField label="Item Type (Product / Service / Part)">
                            <Select value={product.type} onValueChange={value => update(index, 'type', value)}>
                                <SelectTrigger aria-label={`Item type ${index + 1}`}><SelectValue /></SelectTrigger>
                                <SelectContent><SelectItem value="product">Product</SelectItem><SelectItem value="service">Service</SelectItem><SelectItem value="part">Part</SelectItem></SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Name" htmlFor={id('name')} required><Input id={id('name')} value={product.name} onChange={event => update(index, 'name', event.target.value)} placeholder="Enter Name" /></FormField>
                    </div>
                    <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <FormField label="SKU" htmlFor={id('sku')} required>
                            <div className="flex gap-2">
                                <Input id={id('sku')} value={product.sku} onChange={event => update(index, 'sku', event.target.value)} placeholder="Enter SKU" />
                                <Button type="button" variant="outline" onClick={() => update(index, 'sku', `SKU-${Date.now()}`)}>Generate</Button>
                            </div>
                        </FormField>
                        <FormField label="Tax">
                            <Select value={product.tax} onValueChange={value => update(index, 'tax', value)}>
                                <SelectTrigger aria-label={`Tax ${index + 1}`}><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No tax</SelectItem>
                                    {reference?.taxes.map(tax => <SelectItem key={tax.tax_name} value={tax.tax_name}>{tax.tax_name} ({Number(tax.rate)}%)</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField label="Category" required>
                            <Select value={product.category} onValueChange={value => update(index, 'category', value)}>
                                <SelectTrigger aria-label={`Category ${index + 1}`}><SelectValue placeholder="Select Category" /></SelectTrigger>
                                <SelectContent>{reference?.categories.map(category => <SelectItem key={category} value={category}>{category}</SelectItem>)}</SelectContent>
                            </Select>
                        </FormField>
                    </div>
                    <div className="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <FormField label="Sale Price" htmlFor={id('sale-price')} required><Input id={id('sale-price')} type="number" min="0" step="0.01" value={product.sale_price} onChange={event => update(index, 'sale_price', event.target.value)} placeholder="0.00" /></FormField>
                        <FormField label="Purchase Price" htmlFor={id('purchase-price')} required><Input id={id('purchase-price')} type="number" min="0" step="0.01" value={product.purchase_price} onChange={event => update(index, 'purchase_price', event.target.value)} placeholder="0.00" /></FormField>
                        {product.type === 'service'
                            ? <FormField label="Unit"><p className="flex h-10 items-center text-sm text-slate-500">Services have no unit.</p></FormField>
                            : <FormField label="Unit" required>
                                <Select value={product.unit} onValueChange={value => update(index, 'unit', value)}>
                                    <SelectTrigger aria-label={`Unit ${index + 1}`}><SelectValue placeholder="Select Unit" /></SelectTrigger>
                                    <SelectContent>{reference?.units.map(unit => <SelectItem key={unit} value={unit}>{unit}</SelectItem>)}</SelectContent>
                                </Select>
                            </FormField>}
                    </div>
                    <div className="mt-4">
                        <FormField label="Short Description" htmlFor={id('description')}><Textarea id={id('description')} rows={2} value={product.description} onChange={event => update(index, 'description', event.target.value)} placeholder="Enter Short Description" /></FormField>
                    </div>
                </div>;
            })}</div>
            <Button type="button" variant="outline" className="mt-4" onClick={() => { setProducts(current => [...current, emptyProduct()]); setConfirmed(false); }}><Plus className="mr-2 h-4 w-4" />Add item</Button>
            <ConfirmCheckbox checked={confirmed} onChange={setConfirmed} text="I checked every item, price, category, unit and tax against the approved source." />
            {localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
            <SubmitButton disabled={busy || !confirmed || !allComplete} busy={busy || localBusy} onClick={submit}
                label={`Import ${products.length} ${products.length === 1 ? 'item' : 'items'}`} />
        </div>}
    </div>;
}

function ManualPrerequisitePanel({ stage, batchId, busy, onImport }: { stage: Stage; batchId: number; busy: boolean; onImport: ManualImport }) {
    // Zoho has no warehouse stock export: every stock-tracked product is listed with a box per warehouse for its
    // opening quantity. Services hold no stock, so a services-only company confirms there is none.
    const [reference, setReference] = useState<ReferenceData | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [quantities, setQuantities] = useState<Record<string, string>>({});
    const [search, setSearch] = useState('');
    const [noneHeld, setNoneHeld] = useState(false);
    const [confirmed, setConfirmed] = useState(false);
    const [localBusy, setLocalBusy] = useState(false);
    const [localError, setLocalError] = useState<string | null>(null);
    const cell = (sku: string, warehouse: string) => `${sku}\t${warehouse}`;

    useEffect(() => {
        axios.get<ReferenceData>(route('zoho-migrations.reference-data', batchId))
            .then(response => {
                setReference(response.data);
                // Stock already recorded in ERP starts filled in.
                setQuantities(Object.fromEntries(response.data.stock.map(row => [cell(row.sku, row.warehouse), String(Number(row.quantity))])));
            })
            .catch(() => setLoadError('The products and warehouses could not be loaded. Refresh the page to try again.'));
    }, [batchId]);

    const items = reference?.stock_items ?? [];
    const warehouses = reference?.warehouses ?? [];
    const term = search.trim().toLowerCase();
    const shown = term ? items.filter(item => `${item.name} ${item.sku}`.toLowerCase().includes(term)) : items;
    const entered = Object.entries(quantities).filter(([, value]) => value.trim() !== '');
    const invalid = entered.some(([, value]) => !/^\d+$/.test(value.trim()));

    const submit = async () => {
        setLocalBusy(true);
        setLocalError(null);
        try {
            if (noneHeld) {
                await onImport('', true);
            } else {
                await onImport([manualHeaders.inventory_cutover.join('\t'), ...entered.map(([key, value]) => `${key}\t${value.trim()}`)].join('\n'));
                setConfirmed(false);
            }
        } catch (exception: any) {
            setLocalError(importErrorMessage(exception, noneHeld ? 'No stock could not be confirmed.' : 'The stock could not be imported.'));
        } finally {
            setLocalBusy(false);
        }
    };

    return <div className="mt-6 border-y border-amber-300 bg-amber-50">
        <div className="flex gap-3 border-b border-amber-200 px-4 py-4">
            <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
            <div>
                <h3 className="text-sm font-semibold text-amber-950">Accuracy required before later imports</h3>
                <p className="mt-1 text-sm text-amber-900">Zoho has no warehouse stock export. Enter the approved opening quantity of each product in each warehouse. Stock reports and later stock movements start from these quantities.</p>
            </div>
        </div>
        <div className="bg-white px-4 py-5">
            <label className="flex items-start gap-3 border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                <input type="checkbox" checked={noneHeld} onChange={event => { setNoneHeld(event.target.checked); setConfirmed(false); setLocalError(null); }} className="mt-0.5 h-4 w-4" />
                <span><span className="font-medium text-slate-900">No stock to enter</span><br />This company holds no stock: all its items are services.</span>
            </label>

            {!noneHeld && <div className="mt-5">
                {loadError && <p className="text-sm font-medium text-red-700">{loadError}</p>}
                {!reference && !loadError && <p className="flex items-center gap-2 text-sm text-slate-500"><Loader2 className="h-4 w-4 animate-spin" />Loading products and warehouses…</p>}
                {reference && items.length === 0 && <p className="text-sm text-slate-600">ERP has no stock-tracked products: all items are services. Tick <span className="font-medium">No stock to enter</span> above.</p>}
                {reference && items.length > 0 && warehouses.length === 0 && <p className="text-sm text-red-700">ERP has no warehouse yet. Complete the Warehouses step first.</p>}
                {reference && items.length > 0 && warehouses.length > 0 && <>
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-slate-600">{items.length} stock-tracked {items.length === 1 ? 'product' : 'products'}. Type each opening quantity; leave a box empty when there is none.</p>
                        {items.length > 8 && <Input value={search} onChange={event => setSearch(event.target.value)} placeholder="Search name or SKU" className="h-9 sm:max-w-xs" />}
                    </div>
                    <div className="mt-3 max-h-[420px] overflow-auto border border-slate-200">
                        <table className="w-full min-w-[480px] text-sm">
                            <thead className="sticky top-0 bg-slate-50 text-slate-600"><tr>
                                <th className="p-2 text-left font-medium">Product</th>
                                {warehouses.map(warehouse => <th key={warehouse} className="w-36 p-2 text-left font-medium">{warehouse}</th>)}
                            </tr></thead>
                            <tbody className="divide-y divide-slate-100">{shown.map(item => <tr key={item.sku}>
                                <td className="p-2"><p className="font-medium text-slate-900">{item.name}</p><p className="text-xs text-slate-500">{item.sku}</p></td>
                                {warehouses.map(warehouse => {
                                    const key = cell(item.sku, warehouse);
                                    const value = quantities[key] ?? '';
                                    return <td key={warehouse} className="p-2"><Input inputMode="numeric" value={value} placeholder="0" aria-label={`${item.name} in ${warehouse}`}
                                        className={`h-9 ${value.trim() !== '' && !/^\d+$/.test(value.trim()) ? 'border-red-400' : ''}`}
                                        onChange={event => { setQuantities(current => ({ ...current, [key]: event.target.value })); setConfirmed(false); }} /></td>;
                                })}
                            </tr>)}</tbody>
                        </table>
                    </div>
                    {invalid && <p className="mt-2 text-sm text-red-700">Quantities must be whole numbers of zero or more.</p>}
                    <ConfirmCheckbox checked={confirmed} onChange={setConfirmed} text="I checked every quantity against the approved stock count." />
                </>}
            </div>}
            {localError && <p className="mt-3 text-sm font-medium text-red-700">{localError}</p>}
            {(noneHeld || (items.length > 0 && warehouses.length > 0)) && <SubmitButton
                disabled={busy || (!noneHeld && (!confirmed || invalid || entered.length === 0))} busy={localBusy} onClick={submit}
                label={noneHeld ? 'Confirm no stock' : `Import ${entered.length} stock ${entered.length === 1 ? 'quantity' : 'quantities'}`} />}
        </div>
    </div>;
}

/** The file attached for a guide entry, matched on the kind of file the check recognised. */
function attachedFor(entry: UploadGuideFile, files: MigrationFile[]) {
    return files.find(file => file.dataset_type !== null && entry.datasets.includes(file.dataset_type));
}

function UploadChecklist({ guide, files, multiple, completed }: { guide: UploadGuide; files: MigrationFile[]; multiple: boolean; completed: boolean }) {
    // Files still being checked have no kind yet.
    const pending = files.filter(file => file.dataset_type === null);
    return <div className="mt-4 border-t border-slate-200 pt-4">
        <p className="text-xs font-semibold uppercase text-slate-500">{multiple ? 'Files for this step, in this order' : 'File for this step'}</p>
        <ol className="mt-2 space-y-2">
            {guide.files.map((entry, index) => {
                const attached = attachedFor(entry, files);
                return <li key={entry.title} className="flex items-start gap-3 text-sm">
                    <span className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ${attached ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600'}`}>{attached ? <Check className="h-3 w-3" /> : index + 1}</span>
                    <div className="min-w-0">
                        <span className="font-medium text-slate-800">{entry.title}</span>
                        {entry.optional ? <span className="ml-2 text-xs text-slate-500">optional</span> : multiple && <span className="ml-2 text-xs text-slate-500">required</span>}
                        <p className="truncate text-xs text-slate-500">{attached ? `${completed ? 'Imported' : 'Attached'}: ${attached.original_filename}` : entry.where[0]}</p>
                    </div>
                </li>;
            })}
        </ol>
        {pending.map(file => <p key={file.id} className="mt-2 text-xs text-slate-500">{file.status === 'review_required' ? `${file.original_filename} was not recognised: ${file.failure_message ?? 'check the file'}` : `Checking ${file.original_filename}…`}</p>)}
    </div>;
}

function UploadGuideDialog({ open, onOpenChange, stage, guide, files, topUp, onChoose }: { open: boolean; onOpenChange: (open: boolean) => void; stage: Stage; guide: UploadGuide; files: MigrationFile[]; topUp: boolean; onChoose: () => void }) {
    const multiple = guide.files.length > 1;
    const next = guide.files.find(entry => !attachedFor(entry, files));
    // Wide enough to show the files side by side, so the whole guide and its buttons fit without scrolling.
    const width = guide.files.length > 2 ? 'sm:max-w-5xl' : multiple ? 'sm:max-w-3xl' : 'sm:max-w-xl';
    const columns = guide.files.length > 2 ? 'md:grid-cols-3' : multiple ? 'md:grid-cols-2' : '';
    return <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className={width}>
            <DialogHeader className="pb-2 pr-12">
                <DialogTitle>Before you upload: {stage.title}</DialogTitle>
                <DialogDescription>
                    {topUp ? 'This step is already imported. Upload a newer full export only to add records created since; records imported earlier are checked and skipped.'
                        : multiple ? 'This step takes more than one file. Add them one at a time, in this order. Each file is recognised automatically, and a file of the same kind replaces the earlier one.'
                        : 'Export this file from Zoho Books and upload it unchanged.'}
                </DialogDescription>
            </DialogHeader>
            <div className="!py-2">
                <ol className={`grid gap-3 ${columns}`}>
                    {guide.files.map((entry, index) => {
                        const attached = attachedFor(entry, files);
                        const isNext = multiple && entry === next;
                        return <li key={entry.title} className={`flex flex-col rounded-md border p-3 ${isNext ? 'border-emerald-400 bg-emerald-50/50' : 'border-slate-200'}`}>
                            <div className="flex flex-wrap items-center gap-1.5">
                                {multiple && <span className="flex h-5 w-5 items-center justify-center rounded-full bg-slate-900 text-[11px] font-semibold text-white">{index + 1}</span>}
                                <h4 className="text-sm font-semibold text-slate-900">{entry.title}</h4>
                                {entry.optional && <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600">Optional</span>}
                                {isNext && <span className="rounded bg-emerald-100 px-1.5 py-0.5 text-[11px] font-medium text-emerald-800">Add next</span>}
                            </div>
                            {attached && <p className="mt-1 inline-flex items-center gap-1 truncate text-xs font-medium text-emerald-700"><Check className="h-3 w-3 shrink-0" />{attached.original_filename}</p>}
                            <ol className="mt-2 list-decimal space-y-0.5 pl-4 text-xs text-slate-700">{entry.where.map(step => <li key={step}>{step}</li>)}</ol>
                            {entry.note && <p className="mt-2 flex gap-1.5 text-xs text-slate-500"><Info className="mt-0.5 h-3 w-3 shrink-0" />{entry.note}</p>}
                        </li>;
                    })}
                </ol>
                <p className="mt-3 text-xs text-slate-500">{guide.tips.join(' ')}</p>
            </div>
            <DialogFooter className="!pt-3">
                <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
                <Button onClick={onChoose}><Upload className="mr-2 h-4 w-4" />{multiple && next ? `Choose ${next.title}` : 'Choose file'}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>;
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

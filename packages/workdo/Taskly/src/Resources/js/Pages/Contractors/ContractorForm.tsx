import React, { useEffect } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { ContractorOption, ContractorType, ProjectContract } from './types';

interface Props {
    contract?: ProjectContract | null;
    defaultType: ContractorType;
    projects: Array<{ id: number; name: string }>;
    vendors: Array<{ id: number; company_name: string; vendor_code: string }>;
    mainContracts: ContractorOption[];
    onSuccess: () => void;
}

export default function ContractorForm({ contract, defaultType, projects, vendors, mainContracts, onSuccess }: Props) {
    const { t } = useTranslation();
    const auth = usePage<any>().props.auth;
    const editing = Boolean(contract);
    const { data, setData, post, put, processing, errors, clearErrors } = useForm({
        project_id: contract?.project_id?.toString() || '',
        vendor_id: contract?.vendor_id?.toString() || '',
        type: contract?.type || defaultType,
        parent_contract_id: contract?.parent_contract_id?.toString() || '',
        scope_of_work: contract?.scope_of_work || '',
        contract_value: contract?.contract_value?.toString() || '',
        work_start_date: contract?.work_start_date || '',
        completion_date: contract?.completion_date || '',
    });

    useEffect(() => {
        if (data.type === 'main' && data.parent_contract_id) {
            setData('parent_contract_id', '');
        }
    }, [data.type]);

    const availableParents = mainContracts.filter((item) => item.project_id.toString() === data.project_id && item.id !== contract?.id);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        clearErrors();
        const options = { preserveScroll: true, onSuccess };
        if (editing && contract) {
            put(route('project.contractors.update', contract.id), options);
        } else {
            post(route('project.contractors.store'), options);
        }
    };

    const error = (name: keyof typeof errors) => errors[name] ? <p className="mt-1 text-sm text-destructive">{errors[name]}</p> : null;

    return (
        <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{editing ? t('Edit Project Contractor') : t('Add Project Contractor')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-5">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <Label required>{t('Project')}</Label>
                        <Select value={data.project_id} onValueChange={(value) => {
                            setData((current) => ({ ...current, project_id: value, parent_contract_id: '' }));
                        }}>
                            <SelectTrigger><SelectValue placeholder={t('Select Project')} /></SelectTrigger>
                            <SelectContent searchable>
                                {projects.map((project) => <SelectItem key={project.id} value={project.id.toString()}>{project.name}</SelectItem>)}
                            </SelectContent>
                        </Select>
                        {error('project_id')}
                    </div>
                    <div>
                        <div className="flex items-center justify-between gap-2">
                            <Label required>{t('Vendor')}</Label>
                            {auth?.user?.permissions?.includes('create-vendors') && (
                                <button type="button" className="text-xs text-primary hover:underline" onClick={() => router.get(route('account.vendors.index'), { create: 1, return_to: 'project.contractors.index' })}>
                                    {t('Create Vendor')}
                                </button>
                            )}
                        </div>
                        <Select value={data.vendor_id} onValueChange={(value) => setData('vendor_id', value)}>
                            <SelectTrigger><SelectValue placeholder={t('Select Vendor')} /></SelectTrigger>
                            <SelectContent searchable>
                                {vendors.map((vendor) => (
                                    <SelectItem key={vendor.id} value={vendor.id.toString()}>
                                        {vendor.company_name} — {vendor.vendor_code}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {error('vendor_id')}
                    </div>
                    <div>
                        <Label required>{t('Contractor Type')}</Label>
                        <Select value={data.type} onValueChange={(value) => setData('type', value as ContractorType)}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="main">{t('Main Contractor')}</SelectItem>
                                <SelectItem value="subcontractor">{t('Subcontractor')}</SelectItem>
                            </SelectContent>
                        </Select>
                        {error('type')}
                    </div>
                    {data.type === 'subcontractor' && (
                        <div>
                            <Label>{t('Parent Main Contractor')}</Label>
                            <Select value={data.parent_contract_id || 'none'} onValueChange={(value) => setData('parent_contract_id', value === 'none' ? '' : value)}>
                                <SelectTrigger><SelectValue placeholder={t('Select Main Contractor')} /></SelectTrigger>
                                <SelectContent searchable>
                                    <SelectItem value="none">{t('Direct Subcontractor')}</SelectItem>
                                    {availableParents.map((parent) => (
                                        <SelectItem key={parent.id} value={parent.id.toString()}>
                                            {parent.vendor.company_name} — {parent.scope_of_work}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {error('parent_contract_id')}
                        </div>
                    )}
                    <div className="md:col-span-2">
                        <Label required>{t('Scope of Work')}</Label>
                        <Input value={data.scope_of_work} onChange={(event) => setData('scope_of_work', event.target.value)} placeholder={t('Enter scope of work')} />
                        {error('scope_of_work')}
                    </div>
                    <div>
                        <Label required>{t('Contract Value')}</Label>
                        <Input type="number" min="0" step="0.01" value={data.contract_value} onChange={(event) => setData('contract_value', event.target.value)} placeholder="0.00" />
                        {error('contract_value')}
                    </div>
                    <div />
                    <div>
                        <Label required>{t('Work Start Date')}</Label>
                        <DatePicker value={data.work_start_date} onChange={(value) => setData('work_start_date', value)} />
                        {error('work_start_date')}
                    </div>
                    <div>
                        <Label required>{t('Completion Date')}</Label>
                        <DatePicker value={data.completion_date} onChange={(value) => setData('completion_date', value)} />
                        {error('completion_date')}
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onSuccess}>{t('Cancel')}</Button>
                    <Button type="submit" disabled={processing}>{processing ? t('Saving...') : t('Save')}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    );
}

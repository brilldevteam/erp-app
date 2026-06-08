import React, { useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { toast } from 'sonner';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { InputError } from '@/components/ui/input-error';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, Settings, Zap } from 'lucide-react';
import ConditionValueField from '../../Components/ConditionValueField';
import ActionConfigField from '../../Components/ActionConfigField';
import { useFormFields } from '@/hooks/useFormFields';
import { MultiSelectEnhanced } from '@/components/ui/multi-select-enhanced';

interface Module {
    module: string;
    module_alias: string;
    submodule: string;
    fields: Array<{
        name: string;
        type: string;
        model_name?: string;
    }>;
}

interface Props {
    modules: Module[];
}

export default function Create() {
    const { t } = useTranslation();
    const { modules } = usePage<Props>().props;
    useFlashMessages();

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        module: '',
        submodule: '',
        conditions: [{ field: '', operator: '=', value: '' }],
        actions: { types: [], configs: {} }
    });

    const [selectedModuleFields, setSelectedModuleFields] = useState<any[]>([]);
    const [availableSubmodules, setAvailableSubmodules] = useState<string[]>([]);
    const [fieldValues, setFieldValues] = useState<Record<string, Array<{id: number | string, name: string}>>>({});

    const workflowActionTypeFields = useFormFields('workflowActionType', data, setData, errors);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Validate conditions
        const hasValidCondition = data.conditions.some(c => c.field && c.operator && c.value);
        if (!hasValidCondition) {
            toast.error(t('Please add at least one valid condition'));
            return;
        }

        // Validate actions
        if (!data.actions.types || data.actions.types.length === 0) {
            toast.error(t('Please select at least one action type'));
            return;
        }

        post(route('workflow.store'));
    };

    const handleModuleChange = (moduleValue: string) => {
        setData('module', moduleValue);
        // Get submodules for selected module
        const moduleSubmodules = modules
            .filter(m => m.module === moduleValue)
            .map(m => m.submodule);
        setAvailableSubmodules(moduleSubmodules);
        // Reset submodule and fields
        setData('submodule', '');
        setSelectedModuleFields([]);
        setData('conditions', [{ field: '', operator: '=', value: '' }]);
    };

    const handleSubmoduleChange = (submoduleValue: string) => {
        setData('submodule', submoduleValue);
        const moduleData = modules.find(m => m.module === data.module && m.submodule === submoduleValue);
        setSelectedModuleFields(moduleData?.fields || []);
        // Reset conditions when submodule changes
        setData('conditions', [{ field: '', operator: '=', value: '' }]);
    };

    // Get unique module names
    const uniqueModules = [...new Set(modules.map(m => m.module))];

    const addCondition = () => {
        setData('conditions', [...data.conditions, { field: '', operator: '=', value: '' }]);
    };

    const removeCondition = (index: number) => {
        setData('conditions', data.conditions.filter((_, i) => i !== index));
    };

    const updateCondition = (index: number, field: string, value: string) => {
        const newConditions = [...data.conditions];
        newConditions[index] = { ...newConditions[index], [field]: value };
        setData('conditions', newConditions);

        // Load field values when field is selected
        if (field === 'field' && value) {
            const selectedField = selectedModuleFields.find(f => f.name === value);
            loadFieldValues(value, selectedField?.model_name || 'default');
        }
    };

    const loadFieldValues = async (fieldName: string, modelName: string) => {
        try {
            const response = await fetch(route('workflow.field-values') + `?field=${fieldName}&model=${modelName}`);

            if (!response.ok) return;

            const data = await response.json();
            setFieldValues(prev => ({ ...prev, [fieldName]: data.values || [] }));
        } catch (error) {
            toast.error('Error loading field values: ' + error);
        }
    };

    const updateAction = (field: string, value: any) => {
        if (field === 'types') {
            const oldTypes = data.actions.types || [];
            const removedTypes = oldTypes.filter(t => !value.includes(t));
            const newConfigs = { ...data.actions.configs };
            removedTypes.forEach(type => {
                delete newConfigs[type];
            });
            value.forEach(type => {
                if (!newConfigs[type]) {
                    newConfigs[type] = {};
                }
            });
            setData('actions', { ...data.actions, types: value, configs: newConfigs });
        } else {
            setData('actions', { ...data.actions, [field]: value });
        }
    };

    const updateActionConfig = (actionType: string, configField: string, value: string) => {
        setData('actions', {
            ...data.actions,
            configs: {
                ...data.actions.configs,
                [actionType]: {
                    ...data.actions.configs[actionType],
                    [configField]: value
                }
            }
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Workflows'), url: route('workflow.index') },
                { label: t('Create Workflow') }
            ]}
            pageTitle={t('Create Workflow')}
        >
            <Head title={t('Create Workflow')} />

            <div>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Settings className="h-5 w-5" />
                                {t('Workflow Details')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <Label htmlFor="name" required>
                                        {t('Workflow Name')}
                                    </Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={t('Enter workflow name')}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div>
                                    <Label htmlFor="module" required>
                                        {t('Module')}
                                    </Label>
                                    <Select value={data.module} onValueChange={handleModuleChange}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select Module')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {uniqueModules.map((module) => {
                                                const moduleData = modules.find(m => m.module === module);
                                                return (
                                                    <SelectItem key={module} value={module}>
                                                        {moduleData?.module_alias || t(module)}
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.module} />
                                </div>

                                <div>
                                    <Label htmlFor="submodule" required>
                                        {t('Sub Module')}
                                    </Label>
                                    <Select
                                        value={data.submodule}
                                        onValueChange={handleSubmoduleChange}
                                        disabled={!data.module}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select Sub Module')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableSubmodules.map((submodule) => (
                                                <SelectItem key={submodule} value={submodule}>
                                                    {t(submodule)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.submodule} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Settings className="h-5 w-5" />
                                    {t('Conditions')}
                                </CardTitle>
                                <Button
                                    type="button"
                                    onClick={addCondition}
                                    variant="default"
                                    size="sm"
                                >
                                    <Plus className="h-4 w-4 mr-1" />
                                    {t('Add Condition')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {data.conditions.map((condition, index) => (
                                    <Card key={index} className="border border-gray-200">
                                        <CardContent className="p-4">
                                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                                <div>
                                                    <Label>{t('Field')}</Label>
                                                    <Select
                                                        value={condition.field}
                                                        onValueChange={(value) => updateCondition(index, 'field', value)}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder={t('Select Field')} />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {selectedModuleFields.map((field) => (
                                                                <SelectItem key={field.name} value={field.name}>
                                                                    {t(field.name.charAt(0).toUpperCase() + field.name.slice(1).replace('_', ' '))}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label>{t('Operator')}</Label>
                                                    <Select
                                                        value={condition.operator}
                                                        onValueChange={(value) => updateCondition(index, 'operator', value)}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="=">=</SelectItem>
                                                            <SelectItem value="!=">!=</SelectItem>
                                                            <SelectItem value=">">&gt;</SelectItem>
                                                            <SelectItem value="<">&lt;</SelectItem>
                                                            <SelectItem value=">=">&gt;=</SelectItem>
                                                            <SelectItem value="<=">&lt;=</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label>{t('Value')}</Label>
                                                    <ConditionValueField
                                                        selectedField={selectedModuleFields.find(f => f.name === condition.field)}
                                                        value={condition.value}
                                                        onChange={(value) => updateCondition(index, 'value', value)}
                                                        fieldValues={fieldValues[condition.field]}
                                                    />
                                                </div>

                                                <div className="flex items-end">
                                                    {data.conditions.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            onClick={() => removeCondition(index)}
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Zap className="h-5 w-5" />
                                {t('Actions')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Card className="border border-gray-200">
                                <CardContent className="p-4">
                                    <div className="space-y-4">
                                        <div>
                                            <Label>{t('Action Types')}</Label>
                                            <MultiSelectEnhanced
                                                options={workflowActionTypeFields.map(field => ({
                                                    value: field.value,
                                                    label: field.label
                                                }))}
                                                value={data.actions.types || []}
                                                onValueChange={(values) => updateAction('types', values)}
                                                placeholder={t('Select Action Types')}
                                            />
                                        </div>

                                        {data.actions.types && data.actions.types.map((actionType) => (
                                            <div key={actionType} className="border rounded-lg p-4 bg-gray-50">
                                                <h4 className="font-medium text-gray-900 mb-3">{actionType} {t('Configuration')}</h4>
                                                <ActionConfigField
                                                    actionType={actionType}
                                                    config={data.actions.configs?.[actionType] || {}}
                                                    onConfigChange={(field, value) => updateActionConfig(actionType, field, value)}
                                                />
                                                <div className="mt-4">
                                                    <Label className="text-sm font-medium text-gray-700">{t('Message')}</Label>
                                                    <Textarea
                                                        value={data.actions.configs?.[actionType]?.message || ''}
                                                        onChange={(e) => updateActionConfig(actionType, 'message', e.target.value)}
                                                        rows={3}
                                                        placeholder={t('Please enter the message...')}
                                                    />
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </CardContent>
                    </Card>

                    <div className="flex justify-between items-center">
                        <div className="text-sm text-muted-foreground">
                            {data.conditions.length} {t('conditions')}, {data.actions.types?.length || 0} {t('action types')}
                        </div>
                        <div className="flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.visit(route('workflow.index'))}
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                            >
                                {processing ? t('Creating...') : t('Create')}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

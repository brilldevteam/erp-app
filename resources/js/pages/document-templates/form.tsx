import { FormEvent, useMemo } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { InputError } from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import MediaPicker from '@/components/MediaPicker';
import DocumentTemplatePreview from '@/components/document-templates/document-template-preview';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { DocumentTemplate, DocumentTemplateConfig, TemplateSampleDocument } from '@/types/document-template';
import { ArrowLeft, Copy, Save, Star, Trash2 } from 'lucide-react';

interface Props {
    template: DocumentTemplate | null;
    defaultConfig: DocumentTemplateConfig;
    sampleDocument: TemplateSampleDocument;
    auth: {
        user: {
            permissions?: string[];
        };
    };
    [key: string]: any;
}

const itemColumns = ['item', 'description', 'quantity', 'rate', 'tax', 'total'];

export default function Form() {
    const { t } = useTranslation();
    useFlashMessages();
    const { template, defaultConfig, sampleDocument, auth } = usePage<Props>().props;
    const isEdit = Boolean(template);
    const permissions = auth.user.permissions || [];
    const canDelete = permissions.includes('manage-document-templates') || permissions.includes('delete-document-templates');
    const { data, setData, post, put, processing, errors } = useForm({
        name: template?.name || '',
        type: template?.type || 'quotation',
        status: template?.status || 'active',
        is_default: template?.is_default || false,
        primary_color: template?.primary_color || '#10b981',
        logo_url: template?.logo_url || '',
        config_json: template?.config_json || defaultConfig,
        terms: template?.terms || '',
        notes: template?.notes || '',
        bank_details: template?.bank_details || '',
        signature_url: template?.signature_url || '',
        signature_text: template?.signature_text || 'Authorized Signature',
    });

    const previewDocument = useMemo(() => ({
        ...sampleDocument,
        type: data.type as 'quotation' | 'invoice',
        template: data,
    }), [sampleDocument, data]);

    const setConfig = (path: string, value: any) => {
        const parts = path.split('.');
        const next: any = structuredClone(data.config_json);
        let current = next;
        parts.slice(0, -1).forEach((part) => {
            current[part] = current[part] || {};
            current = current[part];
        });
        current[parts[parts.length - 1]] = value;
        setData('config_json', next);
    };

    const toggleColumn = (column: string, checked: boolean) => {
        const columns = data.config_json.itemsTable.columns || [];
        const next = checked ? [...new Set([...columns, column])] : columns.filter((item) => item !== column);
        setConfig('itemsTable.columns', next.length ? next : ['item', 'total']);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const redirectAfterMessage = () => {
            window.setTimeout(() => {
                router.visit(route('document-templates.index'));
            }, 1200);
        };

        if (isEdit && template) {
            put(route('document-templates.update', template.id), { preserveScroll: true, onSuccess: redirectAfterMessage });
        } else {
            post(route('document-templates.store'), { preserveScroll: true, onSuccess: redirectAfterMessage });
        }
    };

    const deleteTemplate = () => {
        if (!template || template.is_default || !confirm(t('Delete this template?'))) {
            return;
        }

        router.delete(route('document-templates.destroy', template.id));
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Templates'), url: route('document-templates.index') }, { label: isEdit ? t('Edit Template') : t('Create Template') }]} pageTitle={isEdit ? t('Edit Template') : t('Create Template')}>
            <Head title={isEdit ? t('Edit Template') : t('Create Template')} />
            <form onSubmit={submit} className="grid gap-6 xl:grid-cols-[480px_minmax(0,1fr)]">
                <div className="space-y-6">
                    <div className="flex gap-2">
                        <Button variant="outline" asChild><Link href={route('document-templates.index')}><ArrowLeft className="mr-2 h-4 w-4" />{t('Back')}</Link></Button>
                        {isEdit && template && <Button type="button" variant="outline" onClick={() => router.post(route('document-templates.duplicate', template.id))}><Copy className="mr-2 h-4 w-4" />{t('Duplicate')}</Button>}
                        {isEdit && template && !template.is_default && data.status === 'active' && <Button type="button" variant="outline" onClick={() => router.post(route('document-templates.default', template.id))}><Star className="mr-2 h-4 w-4" />{t('Set Default')}</Button>}
                        {isEdit && template && canDelete && (
                            <Button
                                type="button"
                                variant="destructive"
                                disabled={template.is_default}
                                title={template.is_default ? t('Default templates cannot be deleted. Set another template as default first.') : undefined}
                                onClick={deleteTemplate}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />{t('Delete')}
                            </Button>
                        )}
                    </div>

                    <Card>
                        <CardHeader><CardTitle>{t('Template Details')}</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <Field label={t('Template Name')} error={errors.name}><Input value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                            <div className="grid grid-cols-2 gap-4">
                                <Field label={t('Type')} error={errors.type}>
                                    <Select value={data.type} onValueChange={(value) => setData('type', value as any)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent><SelectItem value="quotation">{t('Quotation')}</SelectItem><SelectItem value="invoice">{t('Invoice')}</SelectItem></SelectContent>
                                    </Select>
                                </Field>
                                <Field label={t('Status')} error={errors.status}>
                                    <Select value={data.status} onValueChange={(value) => setData('status', value as any)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent><SelectItem value="active">{t('Active')}</SelectItem><SelectItem value="inactive">{t('Inactive')}</SelectItem></SelectContent>
                                    </Select>
                                </Field>
                            </div>
                            <Label className="flex items-center gap-3"><Checkbox checked={Boolean(data.is_default)} onCheckedChange={(checked) => setData('is_default', checked === true)} />{t('Set as default for this type')}</Label>
                            <Field label={t('Primary Color')} error={errors.primary_color}><Input type="color" value={data.primary_color} onChange={(e) => setData('primary_color', e.target.value)} /></Field>
                            <Field label={t('Logo Option')} error={errors.logo_url}>
                                <MediaPicker value={data.logo_url} onChange={(value) => setData('logo_url', Array.isArray(value) ? value[0] || '' : value)} placeholder={t('Select template logo...')} />
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{t('Layout Settings')}</CardTitle></CardHeader>
                        <CardContent className="space-y-5">
                            <Section title={t('Header')}>
                                <Check label={t('Company Logo')} checked={data.config_json.header.showLogo} onChange={(value) => setConfig('header.showLogo', value)} />
                                <Check label={t('Company Name')} checked={data.config_json.header.showCompanyName} onChange={(value) => setConfig('header.showCompanyName', value)} />
                                <Check label={t('Company Address')} checked={data.config_json.header.showCompanyAddress} onChange={(value) => setConfig('header.showCompanyAddress', value)} />
                                <Check label={t('Contact Details')} checked={data.config_json.header.showContactDetails} onChange={(value) => setConfig('header.showContactDetails', value)} />
                                <Select value={data.config_json.header.alignment} onValueChange={(value) => setConfig('header.alignment', value)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="left">{t('Left')}</SelectItem><SelectItem value="center">{t('Center')}</SelectItem><SelectItem value="right">{t('Right')}</SelectItem></SelectContent>
                                </Select>
                            </Section>
                            <Section title={t('Customer Details')}>
                                <Check label={t('Billing Address')} checked={data.config_json.customerBlock.showBillingAddress} onChange={(value) => setConfig('customerBlock.showBillingAddress', value)} />
                                <Check label={t('Shipping Address')} checked={data.config_json.customerBlock.showShippingAddress} onChange={(value) => setConfig('customerBlock.showShippingAddress', value)} />
                                <Check label={t('Contact Person')} checked={data.config_json.customerBlock.showContactPerson} onChange={(value) => setConfig('customerBlock.showContactPerson', value)} />
                            </Section>
                            <Section title={t('Item Table Columns')}>
                                <div className="grid grid-cols-2 gap-2">
                                    {itemColumns.map((column) => <Check key={column} label={t(column)} checked={data.config_json.itemsTable.columns.includes(column)} onChange={(value) => toggleColumn(column, value)} />)}
                                </div>
                            </Section>
                            <Section title={t('Totals And Footer')}>
                                <Check label={t('Subtotal')} checked={data.config_json.totals.showSubtotal} onChange={(value) => setConfig('totals.showSubtotal', value)} />
                                <Check label={t('Discount')} checked={data.config_json.totals.showDiscount} onChange={(value) => setConfig('totals.showDiscount', value)} />
                                <Check label={t('Tax')} checked={data.config_json.totals.showTax} onChange={(value) => setConfig('totals.showTax', value)} />
                                <Check label={t('Grand Total')} checked={data.config_json.totals.showGrandTotal} onChange={(value) => setConfig('totals.showGrandTotal', value)} />
                                <Check label={t('Terms')} checked={data.config_json.footer.showTerms} onChange={(value) => setConfig('footer.showTerms', value)} />
                                <Check label={t('Notes')} checked={data.config_json.footer.showNotes} onChange={(value) => setConfig('footer.showNotes', value)} />
                                <Check label={t('Bank Details')} checked={data.config_json.footer.showBankDetails} onChange={(value) => setConfig('footer.showBankDetails', value)} />
                                <Check label={t('Signature')} checked={data.config_json.footer.showSignature} onChange={(value) => setConfig('footer.showSignature', value)} />
                            </Section>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{t('Sections')}</CardTitle></CardHeader>
                        <CardContent className="space-y-4">
                            <Field label={t('Terms and Conditions')}><Textarea rows={4} value={data.terms} onChange={(e) => setData('terms', e.target.value)} /></Field>
                            <Field label={t('Notes')}><Textarea rows={3} value={data.notes} onChange={(e) => setData('notes', e.target.value)} /></Field>
                            <Field label={t('Bank Details')}><Textarea rows={4} value={data.bank_details} onChange={(e) => setData('bank_details', e.target.value)} /></Field>
                            <Field label={t('Signature Image')} error={errors.signature_url}>
                                <MediaPicker value={data.signature_url} onChange={(value) => setData('signature_url', Array.isArray(value) ? value[0] || '' : value)} placeholder={t('Select signature image...')} />
                            </Field>
                            <Field label={t('Signature Text')}><Input value={data.signature_text} onChange={(e) => setData('signature_text', e.target.value)} /></Field>
                            <Field label={t('Footer Text')}><Input value={data.config_json.footer.footerText} onChange={(e) => setConfig('footer.footerText', e.target.value)} /></Field>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={processing} className="w-full"><Save className="mr-2 h-4 w-4" />{processing ? t('Saving...') : t('Save Template')}</Button>
                </div>

                <div className="xl:sticky xl:top-6 xl:self-start">
                    <Card>
                        <CardHeader><CardTitle>{t('Live Preview')}</CardTitle></CardHeader>
                        <CardContent className="overflow-auto bg-slate-100 p-4">
                            <DocumentTemplatePreview template={data as any} document={previewDocument} compact />
                        </CardContent>
                    </Card>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return <div><Label className="mb-2 block">{label}</Label>{children}{error && <InputError message={error} />}</div>;
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return <div className="space-y-3 rounded-lg border p-3"><div className="font-medium">{title}</div>{children}</div>;
}

function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return <Label className="flex items-center gap-3"><Checkbox checked={checked} onCheckedChange={(value) => onChange(value === true)} />{label}</Label>;
}

import { FormEvent, ReactNode } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import MediaPicker from '@/components/MediaPicker';
import { toast } from 'sonner';

type TemplateKey = 'classic' | 'modern' | 'minimal' | 'zoho';
type TemplateForm = Record<string, string | number | boolean> & {
    document_type: 'invoice' | 'quotation';
    profile_template: string;
};

interface Props {
    settings: Record<string, string | number | boolean>;
    templates: TemplateKey[];
    documentType: 'invoice' | 'quotation';
    templateProfiles: Record<TemplateKey, Record<string, string | number | boolean>>;
}

const toggles = [
    ['document_show_sku', 'SKU'],
    ['document_show_description', 'Description'],
    ['document_show_quantity', 'Quantity'],
    ['document_show_discount', 'Discount'],
    ['document_show_tax', 'Tax'],
    ['document_show_shipping', 'Shipping address'],
    ['document_show_signature', 'Signature'],
    ['document_show_qr', 'QR code'],
] as const;

export default function DocumentSettings({ settings, templates, documentType, templateProfiles }: Props) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<TemplateForm>({
        ...settings,
        document_type: documentType,
        profile_template: (documentType === 'invoice' ? settings.invoice_template : settings.quotation_template) as string,
    });
    const isInvoice = documentType === 'invoice';
    const templateField = isInvoice ? 'invoice_template' : 'quotation_template';
    const pageName = isInvoice ? t('Sales Invoice Template') : t('Quotation Template');
    const selectedFieldCount = toggles.filter(([key]) => Boolean(data[key])).length;
    const allFieldsSelected = selectedFieldCount === toggles.length;
    const fieldSelectionState = allFieldsSelected ? true : selectedFieldCount > 0 ? 'indeterminate' : false;

    const setAllFields = (selected: boolean) => {
        const updated = { ...data };
        toggles.forEach(([key]) => {
            updated[key] = selected;
        });
        setData(updated);
    };

    const selectTemplate = (template: TemplateKey) => {
        const profile = templateProfiles[template];
        setData({
            ...data,
            [templateField]: template,
            profile_template: template,
            document_accent_color: profile.accent_color,
            document_default_logo: profile.document_default_logo,
            document_signature_image: profile.signature_image,
            document_footer: profile.footer,
            document_payment_instructions: profile.payment_instructions,
            [isInvoice ? 'invoice_document_title' : 'quotation_document_title']: profile.document_title,
            document_show_sku: profile.show_sku,
            document_show_description: profile.show_description,
            document_show_quantity: profile.show_quantity,
            document_show_discount: profile.show_discount,
            document_show_tax: profile.show_tax,
            document_show_shipping: profile.show_shipping,
            document_show_signature: profile.show_signature,
            document_show_qr: profile.show_qr,
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put(route('documents.settings.update'), {
            preserveScroll: true,
            onSuccess: () => toast.success(t('Template saved successfully. Future documents will use these defaults.')),
            onError: () => toast.error(t('Template could not be saved. Please check the highlighted fields.')),
        });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: pageName }]} pageTitle={pageName}>
            <Head title={pageName} />
            <form onSubmit={submit} className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>{pageName}</CardTitle>
                        <CardDescription>{t('Choose the default layout by clicking one preview. Preview and PDF use this same template.')}</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        {templates.map((template) => (
                            <button
                                type="button"
                                key={template}
                                onClick={() => selectTemplate(template)}
                                className={cn('overflow-hidden rounded-lg border-2 text-left transition', data[templateField] === template ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:border-primary/50')}
                            >
                                <iframe title={`${template} template`} src={route('documents.settings.sample', { type: documentType, template })} className="h-72 w-full pointer-events-none bg-white" />
                                <div className="border-t bg-background px-4 py-3 font-medium capitalize">{t(template)}</div>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader><CardTitle>{t('Branding and defaults')}</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('Accent color')} error={errors.document_accent_color as string}><Input type="color" value={data.document_accent_color as string} onChange={(e) => setData('document_accent_color', e.target.value)} /></Field>
                            {isInvoice ? (
                                <Field label={t('Invoice title')} error={errors.invoice_document_title as string}><Input value={data.invoice_document_title as string} onChange={(e) => setData('invoice_document_title', e.target.value)} /></Field>
                            ) : (
                                <Field label={t('Quotation title')} error={errors.quotation_document_title as string}><Input value={data.quotation_document_title as string} onChange={(e) => setData('quotation_document_title', e.target.value)} /></Field>
                            )}
                            <Field label={t('Default document logo')} className="sm:col-span-2" error={errors.document_default_logo as string}>
                                <MediaPicker value={data.document_default_logo as string} onChange={(value) => setData('document_default_logo', Array.isArray(value) ? value[0] || '' : value)} placeholder={t('Select default invoice and quotation logo...')} />
                                <p className="mt-1 text-xs text-muted-foreground">{t('Future documents use this logo automatically. It does not change the dashboard company logo.')}</p>
                            </Field>
                            <Field label={t('Footer')} className="sm:col-span-2"><Textarea value={data.document_footer as string} onChange={(e) => setData('document_footer', e.target.value)} /></Field>
                            <Field label={t('Authorized signature image')} className="sm:col-span-2" error={errors.document_signature_image as string}>
                                <MediaPicker value={(data.document_signature_image as string) || ''} onChange={(value) => setData('document_signature_image', Array.isArray(value) ? value[0] || '' : value)} placeholder={t('Select the signature printed by this template...')} />
                                <p className="mt-1 text-xs text-muted-foreground">{t('Printed only when Signature is enabled below.')}</p>
                            </Field>
                            {isInvoice && <Field label={t('Invoice Bank / payment instructions')} className="sm:col-span-2"><Textarea value={data.document_payment_instructions as string} onChange={(e) => setData('document_payment_instructions', e.target.value)} /></Field>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>{t('Numbering and reminders')}</CardTitle></CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2">
                            {isInvoice ? <>
                                <Field label={t('Invoice prefix')} error={errors.invoice_number_prefix as string}><Input value={data.invoice_number_prefix as string} onChange={(e) => setData('invoice_number_prefix', e.target.value)} /></Field>
                                <Field label={t('Next invoice number')}><Input type="number" min={1} value={data.invoice_next_number as number} onChange={(e) => setData('invoice_next_number', Number(e.target.value))} /></Field>
                            </> : <>
                                <Field label={t('Quotation prefix')} error={errors.quotation_number_prefix as string}><Input value={data.quotation_number_prefix as string} onChange={(e) => setData('quotation_number_prefix', e.target.value)} /></Field>
                                <Field label={t('Next quotation number')}><Input type="number" min={1} value={data.quotation_next_number as number} onChange={(e) => setData('quotation_next_number', Number(e.target.value))} /></Field>
                            </>}
                            <Field label={t('Number padding')}><Input type="number" min={2} max={8} value={data.document_number_padding as number} onChange={(e) => setData('document_number_padding', Number(e.target.value))} /></Field>
                            <Field label={t('Reset frequency')}><select className="h-10 w-full rounded-md border bg-background px-3" value={data.document_number_reset as string} onChange={(e) => setData('document_number_reset', e.target.value)}><option value="never">{t('Never')}</option><option value="yearly">{t('Yearly')}</option><option value="monthly">{t('Monthly')}</option></select></Field>
                            {isInvoice && <Field label={t('Reminder days')} className="sm:col-span-2" error={errors.invoice_reminder_offsets as string}><Input value={data.invoice_reminder_offsets as string} onChange={(e) => setData('invoice_reminder_offsets', e.target.value)} placeholder="-3,0,3,7" /><p className="mt-1 text-xs text-muted-foreground">{t('Negative values are before the due date; positive values are after it.')}</p></Field>}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>{t('Visible document fields')}</CardTitle>
                        <Label className="flex cursor-pointer items-center gap-3">
                            <Checkbox checked={fieldSelectionState} onCheckedChange={(checked) => setAllFields(checked === true)} />
                            {t('Select all')}
                        </Label>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {toggles.map(([key, label]) => <Label key={key} className="flex items-center gap-3"><Checkbox checked={Boolean(data[key])} onCheckedChange={(checked) => setData(key, checked === true)} />{t(label)}</Label>)}
                    </CardContent>
                </Card>

                <div className="flex justify-end"><Button type="submit" disabled={processing}>{processing ? t('Saving...') : t('Save document settings')}</Button></div>
            </form>
        </AuthenticatedLayout>
    );
}

function Field({ label, error, className, children }: { label: string; error?: string; className?: string; children: ReactNode }) {
    return <div className={className}><Label className="mb-2 block">{label}</Label>{children}{error && <p className="mt-1 text-sm text-destructive">{error}</p>}</div>;
}

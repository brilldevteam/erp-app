import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Building2, Check, ChevronDown, ChevronLeft, ChevronRight, CircleDollarSign, FileText, LockKeyhole, Paperclip, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { PhoneInputComponent } from '@/components/ui/phone-input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { CustomerAddressFields } from '@/components/customer-address-fields';
import PreferredCurrencyField from '@/components/preferred-currency-field';
import { useFormFields } from '@/hooks/useFormFields';
import { Address, addressCountryCode } from '@/types/address';
import PartyDocuments from '../Parties/PartyDocuments';
import SavedPartyDocuments from '../Parties/SavedPartyDocuments';
import { Customer, CustomerFormData } from './types';

type Props = {
    mode: 'create' | 'edit';
    customer?: Customer;
    onSuccess: (userId?: number) => void;
    returnToCurrentPage?: boolean;
    returnTo?: 'quotation' | 'current';
};

const terms = ['Due on receipt', 'Net 7', 'Net 15', 'Net 30', 'Net 45', 'Net 60'];
const validEmail = (value: string) => /^\S+@\S+\.\S+$/.test(value);
const emptyAddress = (): Address => ({ name: '', country_code: '', country: '', address_line_1: '', address_line_2: '', city: '', state: '', zip_code: '' });

function addressFor(customer: Customer | undefined, kind: 'billing' | 'shipping') {
    const address = customer?.[`${kind}_address`] || emptyAddress();
    return { ...emptyAddress(), ...address, country_code: addressCountryCode(address) || address.country_code || '' };
}

function Section({ icon: Icon, title, description, children }: { icon: typeof Building2; title: string; description: string; children: React.ReactNode }) {
    return <section className="rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.03)]">
        <div className="flex gap-3 border-b border-slate-100 px-5 py-4 sm:px-6"><span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><Icon className="h-5 w-5" /></span><div><h3 className="font-semibold text-slate-900">{title}</h3><p className="mt-0.5 text-sm text-slate-500">{description}</p></div></div>
        <div className="space-y-5 p-5 sm:p-6">{children}</div>
    </section>;
}

function Optional({ title, summary, open, onToggle, children }: { title: string; summary: string; open: boolean; onToggle: () => void; children: React.ReactNode }) {
    return <section className="overflow-hidden rounded-xl border border-slate-200 bg-slate-50/40">
        <button type="button" onClick={onToggle} aria-expanded={open} className="flex w-full items-center justify-between gap-4 px-4 py-3.5 text-left hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary"><span><span className="block text-sm font-semibold text-slate-800">{title}</span><span className="mt-0.5 block text-xs text-slate-500">{summary}</span></span><ChevronDown className={`h-4 w-4 shrink-0 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`} /></button>
        {open && <div className="border-t border-slate-200 bg-white p-4 sm:p-5">{children}</div>}
    </section>;
}

export default function CustomerForm({ mode, customer, onSuccess, returnToCurrentPage = false, returnTo }: Props) {
    const { t } = useTranslation();
    const savedEmail = (customer?.contact_person_email || '').trim().toLowerCase();
    const portalWasEnabled = Boolean(customer && savedEmail && savedEmail === (customer.user?.email || '').trim().toLowerCase() && customer.user?.is_enable_login && !customer.user?.is_disable);
    const [step, setStep] = useState(1);
    const [taxOpen, setTaxOpen] = useState(Boolean(customer?.tax_number || customer?.cr_number));
    const [addressOpen, setAddressOpen] = useState(Boolean(customer?.billing_address?.address_line_1 || customer?.billing_address?.country));
    const [shippingOpen, setShippingOpen] = useState(Boolean(customer && !customer.same_as_billing));
    const [customTermsMode, setCustomTermsMode] = useState(Boolean(customer?.payment_terms && !terms.includes(customer.payment_terms)));
    const [uploading, setUploading] = useState(false);
    const { data, setData, post, put, transform, processing, errors, setError, clearErrors } = useForm<CustomerFormData>({
        company_name: customer?.company_name || '', contact_person_name: customer?.contact_person_name || '',
        contact_person_email: customer?.contact_person_email || '', contact_person_mobile: customer?.contact_person_mobile || '',
        tax_number: customer?.tax_number || '', cr_number: customer?.cr_number || '', payment_terms: customer?.payment_terms || 'Due on receipt',
        preferred_currency_code: customer?.user?.preferred_currency_code || customer?.preferred_currency_code || '',
        billing_address: addressFor(customer, 'billing'), shipping_address: addressFor(customer, 'shipping'),
        same_as_billing: customer?.same_as_billing ?? true, notes: customer?.notes || '', portal_access_enabled: portalWasEnabled,
        password: '', password_confirmation: '', attachments: [],
    });
    const setField = (key: string, value: unknown) => setData(key as keyof CustomerFormData, value as never);
    const formFields = useFormFields(mode === 'create' ? 'customerCreateFields' : 'customerEditFields', data, setField, errors, mode);
    const customFields = useFormFields('getCustomFields', { ...data, module: 'Account', sub_module: 'Customer', ...(customer ? { id: customer.id } : {}) }, setData, errors, mode, t);
    const emailIsValid = validEmail(data.contact_person_email);
    const customTerms = customTermsMode || Boolean(data.payment_terms && !terms.includes(data.payment_terms));
    const stepLabels = mode === 'create' ? [t('Contact'), t('Billing'), t('Review & access')] : [t('Profile'), t('Billing'), t('Access & files')];
    const summary = useMemo(() => [
        [t('Customer'), data.company_name || t('Not entered')], [t('Primary contact'), data.contact_person_name || t('Not entered')],
        [t('Email'), data.contact_person_email || t('Not provided')], [t('Payment terms'), data.payment_terms || t('Not set')],
        [t('Portal access'), data.portal_access_enabled ? t('Enabled') : t('Not enabled')],
    ], [data.company_name, data.contact_person_email, data.contact_person_name, data.payment_terms, data.portal_access_enabled, t]);

    const goNext = () => {
        clearErrors('company_name', 'contact_person_name', 'contact_person_email');
        if (step === 1) {
            let invalid = false;
            if (!data.company_name.trim()) { setError('company_name', t('Enter the company name.')); invalid = true; }
            if (!data.contact_person_name.trim()) { setError('contact_person_name', t('Enter the primary contact name.')); invalid = true; }
            if (data.contact_person_email && !emailIsValid) { setError('contact_person_email', t('Enter a valid email address.')); invalid = true; }
            if (invalid) return;
        }
        setStep(value => Math.min(3, value + 1));
    };
    const errorStep = (serverErrors: Record<string, string>) => {
        const keys = Object.keys(serverErrors);
        if (keys.some(key => ['company_name', 'contact_person_name', 'contact_person_email', 'contact_person_mobile'].includes(key))) return 1;
        if (keys.some(key => key.startsWith('billing_address') || key.startsWith('shipping_address') || ['tax_number', 'cr_number', 'payment_terms', 'preferred_currency_code'].includes(key))) return 2;
        return 3;
    };
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (mode === 'create') {
            post(route('account.customers.store', (returnToCurrentPage || returnTo) ? { return_to: returnTo || 'quotation' } : undefined), { forceFormData: true, onError: result => setStep(errorStep(result)), onSuccess: page => onSuccess((page.props.flash as { createdCustomerUserId?: number })?.createdCustomerUserId) });
            return;
        }
        if (!customer) return;
        const files = data.attachments;
        transform(({ attachments, ...formData }) => formData);
        put(route('account.customers.update', customer.id), { preserveScroll: true, onError: result => setStep(errorStep(result)), onSuccess: () => {
            if (!files.length) { onSuccess(); return; }
            setUploading(true);
            router.post(route('account.customers.attachments.store', customer.id), { attachments: files }, { forceFormData: true, preserveScroll: true, onSuccess: () => onSuccess(), onFinish: () => setUploading(false) });
        } });
    };
    const setCompany = (name: string) => {
        const previous = data.company_name;
        setData('company_name', name);
        if (!data.billing_address.name || data.billing_address.name === previous) setData('billing_address', { ...data.billing_address, name });
    };

    return <DialogContent className="h-[100dvh] max-h-[100dvh] w-screen max-w-none rounded-none sm:h-auto sm:max-h-[92vh] sm:w-[calc(100%-2rem)] sm:max-w-5xl sm:rounded-2xl">
        <DialogHeader className="pr-14"><DialogTitle>{mode === 'create' ? t('Create customer') : t('Edit customer')}</DialogTitle><DialogDescription>{mode === 'create' ? t('Start with the essentials. Optional details can be completed now or later.') : t('Update this customer by section without losing your place.')}</DialogDescription></DialogHeader>
        <form onSubmit={submit} noValidate autoComplete="off" className="!p-0">
            <nav className="border-b bg-white px-4 py-3 sm:px-6" aria-label={t('Customer form progress')}><ol className="grid grid-cols-3 gap-2">{stepLabels.map((label, index) => { const number = index + 1; const active = step === number; const done = step > number; return <li key={label}><button type="button" onClick={() => setStep(number)} aria-current={active ? 'step' : undefined} className={`flex w-full items-center gap-2 rounded-xl px-2 py-2 text-left sm:px-3 ${active ? 'bg-primary/10 text-primary' : 'text-slate-500 hover:bg-slate-50'}`}><span className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${active ? 'border-primary bg-primary text-white' : done ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-300 bg-white'}`}>{done ? <Check className="h-3.5 w-3.5" /> : number}</span><span className="hidden truncate text-sm font-medium sm:block">{label}</span></button></li>; })}</ol></nav>
            <div className="max-h-[calc(100dvh-13rem)] overflow-y-auto bg-slate-50/70 p-4 sm:max-h-[calc(92vh-13rem)] sm:p-6">
                {step === 1 && <div className="mx-auto max-w-3xl"><Section icon={Building2} title={t('Who is this customer?')} description={t('Only the company and primary contact are required to get started.')}><div className="grid gap-5 sm:grid-cols-2">
                    <div className="sm:col-span-2"><Label htmlFor="company_name" required>{t('Company name')}</Label><Input id="company_name" autoFocus value={data.company_name} onChange={event => setCompany(event.target.value)} placeholder={t('e.g., Acme Trading WLL')} required error={errors.company_name} /><InputError message={errors.company_name} /></div>
                    <div><Label htmlFor="contact_person_name" required>{t('Primary contact')}</Label><Input id="contact_person_name" value={data.contact_person_name} onChange={event => setData('contact_person_name', event.target.value)} placeholder={t('Person we should contact')} required error={errors.contact_person_name} /><InputError message={errors.contact_person_name} /></div>
                    <PhoneInputComponent label={t('Mobile number')} value={data.contact_person_mobile || ''} onChange={value => setData('contact_person_mobile', value)} placeholder="+974 0000 0000" error={errors.contact_person_mobile} />
                    <div className="sm:col-span-2"><Label htmlFor="contact_person_email">{t('Email address')}</Label><Input id="contact_person_email" name="customer_contact_email" type="email" autoComplete="off" value={data.contact_person_email} onChange={event => { const email = event.target.value; setData('contact_person_email', email); if (!validEmail(email)) setData('portal_access_enabled', false); }} placeholder={t('Used for documents and optional portal access')} error={errors.contact_person_email} /><p className="mt-1.5 text-xs text-slate-500">{t('Optional unless you enable customer portal access.')}</p><InputError message={errors.contact_person_email} /></div>
                </div></Section></div>}
                {step === 2 && <div className="mx-auto max-w-3xl"><Section icon={CircleDollarSign} title={t('Billing preferences')} description={t('These defaults will be used when creating customer documents.')}><div className="grid gap-5 sm:grid-cols-2">
                    <div><Label htmlFor="payment_terms">{t('Payment terms')}</Label><select id="payment_terms" value={customTerms ? 'Custom' : data.payment_terms} onChange={event => { const custom = event.target.value === 'Custom'; setCustomTermsMode(custom); setData('payment_terms', custom ? '' : event.target.value); }} className="mt-1 flex h-10 w-full rounded-lg border border-input bg-white px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/10">{terms.map(term => <option key={term} value={term}>{t(term)}</option>)}<option value="Custom">{t('Custom terms')}</option></select>{customTerms && <Input className="mt-2" value={data.payment_terms || ''} onChange={event => setData('payment_terms', event.target.value)} placeholder={t('Enter custom payment terms')} />}<InputError message={errors.payment_terms} /></div>
                    <PreferredCurrencyField value={data.preferred_currency_code} onChange={value => setData('preferred_currency_code', value)} error={errors.preferred_currency_code} />
                </div>
                    <Optional title={t('Tax and registration details')} summary={t('CR number and tax number')} open={taxOpen} onToggle={() => setTaxOpen(value => !value)}><div className="grid gap-5 sm:grid-cols-2"><div><Label htmlFor="cr_number">{t('CR number')}</Label><Input id="cr_number" value={data.cr_number || ''} onChange={event => setData('cr_number', event.target.value)} placeholder={t('Commercial registration number')} /><InputError message={errors.cr_number} /></div><div><Label htmlFor="tax_number">{t('Tax number')}</Label><Input id="tax_number" value={data.tax_number || ''} onChange={event => setData('tax_number', event.target.value)} placeholder={t('Tax identification number')} /><InputError message={errors.tax_number} /></div></div></Optional>
                    <Optional title={t('Billing address')} summary={t('Add only when needed for invoices')} open={addressOpen} onToggle={() => setAddressOpen(value => !value)}><CustomerAddressFields kind="billing" address={data.billing_address} onChange={value => setData('billing_address', value)} errors={errors} /></Optional>
                    <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4"><div className="flex items-start gap-3"><Checkbox id="same_as_billing" checked={data.same_as_billing} onCheckedChange={checked => { const same = Boolean(checked); setData('same_as_billing', same); if (same) setData('shipping_address', { ...data.billing_address }); else setShippingOpen(true); }} /><div><Label htmlFor="same_as_billing">{t('Shipping address is the same')}</Label><p className="mt-1 text-xs text-slate-500">{t('Turn this off only when deliveries go somewhere else.')}</p></div></div></div>
                    {!data.same_as_billing && <Optional title={t('Different shipping address')} summary={t('Where physical deliveries should be sent')} open={shippingOpen} onToggle={() => setShippingOpen(value => !value)}><CustomerAddressFields kind="shipping" address={data.shipping_address} onChange={value => setData('shipping_address', value)} errors={errors} /></Optional>}
                    {formFields.length > 0 && <div className="grid gap-5 sm:grid-cols-2">{formFields.map(field => <div key={field.id}>{field.component}</div>)}</div>}
                </Section></div>}
                {step === 3 && <div className="mx-auto grid max-w-4xl gap-5 lg:grid-cols-[1.2fr_.8fr]"><div className="space-y-5">
                    <Section icon={LockKeyhole} title={t('Portal access')} description={t('Optional. Let this customer sign in to the ERP portal.')}><div className={`rounded-xl border p-4 ${data.portal_access_enabled ? 'border-primary/30 bg-primary/5' : 'border-slate-200 bg-slate-50/50'}`}><div className="flex items-start justify-between gap-4"><div><Label htmlFor="portal_access_enabled" className="text-base">{t('Customer portal')}</Label><p className="mt-1 text-sm text-slate-500">{emailIsValid ? t('Access will be linked to {{email}}.', { email: data.contact_person_email }) : t('Add a valid email on the Contact step before enabling access.')}</p></div><Switch id="portal_access_enabled" checked={data.portal_access_enabled} disabled={!emailIsValid} onCheckedChange={checked => setData('portal_access_enabled', checked)} /></div>{data.portal_access_enabled && <div className="mt-4 grid gap-4 border-t border-primary/15 pt-4 sm:grid-cols-2"><div><Label htmlFor="password" required={!portalWasEnabled}>{t(portalWasEnabled ? 'New password (optional)' : 'Password')}</Label><Input id="password" name="customer_new_password" type="password" autoComplete="new-password" value={data.password} onChange={event => setData('password', event.target.value)} error={errors.password} /><InputError message={errors.password} /></div><div><Label htmlFor="password_confirmation" required={!portalWasEnabled}>{t('Confirm password')}</Label><Input id="password_confirmation" name="customer_new_password_confirmation" type="password" autoComplete="new-password" value={data.password_confirmation} onChange={event => setData('password_confirmation', event.target.value)} /><InputError message={errors.password_confirmation} /></div></div>}</div></Section>
                    <Section icon={FileText} title={t('Notes and files')} description={t('Keep useful context with the customer record.')}><div><Label htmlFor="customer_notes">{t('Internal notes')}</Label><Textarea id="customer_notes" value={data.notes || ''} onChange={event => setData('notes', event.target.value)} placeholder={t('Add anything your team should know')} rows={3} /><InputError message={errors.notes} /></div>{mode === 'edit' && customer && <SavedPartyDocuments partyType="customers" partyId={customer.id} attachments={customer.attachments || []} canRemove />}<div className="rounded-xl border border-dashed border-slate-300 p-4"><div className="mb-3 flex items-center gap-2 text-sm font-medium text-slate-700"><Paperclip className="h-4 w-4" />{t('Attachments')}</div><PartyDocuments files={data.attachments} onChange={files => setData('attachments', files)} errors={errors} existingCount={customer?.attachments?.length || 0} disabled={processing || uploading} /></div>{customFields.length > 0 && <div className="grid gap-5 sm:grid-cols-2">{customFields.map(field => <div key={field.id}>{field.component}</div>)}</div>}</Section>
                </div><aside className="h-fit rounded-2xl border border-slate-200 bg-white p-5 lg:sticky lg:top-0"><div className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><ShieldCheck className="h-5 w-5" /></span><div><h3 className="font-semibold text-slate-900">{t('Ready to save')}</h3><p className="text-xs text-slate-500">{t('Review the important details')}</p></div></div><dl className="mt-5 divide-y divide-slate-100">{summary.map(([label, value]) => <div key={label} className="py-3"><dt className="text-xs text-slate-500">{label}</dt><dd className="mt-1 break-words text-sm font-medium text-slate-800">{value}</dd></div>)}</dl></aside>{Object.keys(errors).length > 0 && <p role="alert" className="text-sm font-medium text-destructive lg:col-span-2">{t('Please correct the highlighted fields before saving.')}</p>}</div>}
            </div>
            <footer className="sticky bottom-0 flex items-center justify-between gap-3 border-t bg-white px-4 py-4 sm:px-6"><Button type="button" variant="ghost" onClick={() => onSuccess()}>{t('Cancel')}</Button><div className="flex items-center gap-2">{step > 1 && <Button type="button" variant="outline" onClick={() => setStep(value => value - 1)}><ChevronLeft className="mr-1.5 h-4 w-4" />{t('Back')}</Button>}{step < 3 ? <Button type="button" onClick={goNext}>{t('Continue')}<ChevronRight className="ml-1.5 h-4 w-4" /></Button> : <Button type="submit" disabled={processing || uploading}>{processing || uploading ? t('Saving...') : mode === 'create' ? t('Create customer') : t('Save changes')}</Button>}</div></footer>
        </form>
    </DialogContent>;
}

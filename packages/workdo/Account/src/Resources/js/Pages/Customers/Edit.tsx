import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Checkbox } from "@/components/ui/checkbox";
import InputError from "@/components/ui/input-error";
import { PhoneInputComponent } from "@/components/ui/phone-input";
import { Customer, CustomerFormData } from './types';
import { useFormFields } from '@/hooks/useFormFields';
import { CustomerAddressFields } from '@/components/customer-address-fields';
import { addressCountryCode } from '@/types/address';
interface EditCustomerProps {
    customer: Customer;
    onSuccess: () => void;
}

export default function Edit({ customer, onSuccess }: EditCustomerProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<CustomerFormData>({
        ...customer,
        billing_address: { ...customer.billing_address, country_code: addressCountryCode(customer.billing_address) || customer.billing_address.country_code },
        shipping_address: { ...customer.shipping_address, country_code: addressCountryCode(customer.shipping_address) || customer.shipping_address.country_code },
    });

    const formFields = useFormFields('customerEditFields', data, setData, errors, 'edit');

    // Custom fields hook
    const customFields = useFormFields('getCustomFields', { ...data, module: 'Account', sub_module: 'Customer', id: customer.id }, setData, errors, 'edit', t);
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('account.customers.update', customer.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>{t('Edit Customer')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="company_name">{t('Company Name')}</Label>
                    <Input
                        id="company_name"
                        value={data.company_name}
                        onChange={(e) => setData('company_name', e.target.value)}
                        placeholder={t('Enter company name')}
                        required
                    />
                    <InputError message={errors.company_name} />
                </div>
                <div>
                    <Label htmlFor="contact_person_name">{t('Contact Person')}</Label>
                    <Input
                        id="contact_person_name"
                        value={data.contact_person_name}
                        onChange={(e) => setData('contact_person_name', e.target.value)}
                        placeholder={t('Enter contact person name')}
                        required
                    />
                    <InputError message={errors.contact_person_name} />
                </div>
                <div>
                    <Label htmlFor="contact_person_email">{t('Email')}</Label>
                    <Input
                        id="contact_person_email"
                        type="email"
                        value={data.contact_person_email}
                        onChange={(e) => setData('contact_person_email', e.target.value)}
                        placeholder={t('Enter email address')}
                        required
                    />
                    <InputError message={errors.contact_person_email} />
                </div>
                <div>
                    <PhoneInputComponent
                        label={t('Mobile Number')}
                        value={data.contact_person_mobile}
                        onChange={(value) => setData('contact_person_mobile', value)}
                        placeholder="+1234567890"
                        error={errors.contact_person_mobile}
                    />
                </div>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="tax_number">{t('Tax Number')}</Label>
                        <Input
                            id="tax_number"
                            value={data.tax_number}
                            onChange={(e) => setData('tax_number', e.target.value)}
                            placeholder={t('Enter tax number')}
                        />
                        <InputError message={errors.tax_number} />
                    </div>
                    <div>
                        <Label htmlFor="payment_terms">{t('Payment Terms')}</Label>
                        <Input
                            id="payment_terms"
                            value={data.payment_terms}
                            onChange={(e) => setData('payment_terms', e.target.value)}
                            placeholder={t('e.g., Net 30')}
                        />
                        <InputError message={errors.payment_terms} />
                    </div>
                </div>
                <CustomerAddressFields
                    kind="billing"
                    address={data.billing_address}
                    onChange={(address) => setData('billing_address', address)}
                    errors={errors}
                />
                {formFields.length > 0 && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        {formFields.map((field) => <div key={field.id}>{field.component}</div>)}
                    </div>
                )}
                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="same_as_billing"
                        checked={data.same_as_billing}
                        onCheckedChange={(checked) => {
                            setData('same_as_billing', !!checked);
                            if (checked) {
                                setData('shipping_address', {...data.billing_address});
                            }
                        }}
                    />
                    <Label htmlFor="same_as_billing">{t('Shipping address same as billing')}</Label>
                </div>

                {!data.same_as_billing && (
                    <div className="space-y-4 border-t pt-4">
                        <h3 className="text-lg font-medium">{t('Shipping Address')}</h3>
                        <CustomerAddressFields
                            kind="shipping"
                            address={data.shipping_address}
                            onChange={(address) => setData('shipping_address', address)}
                            errors={errors}
                        />
                    </div>
                )}

                <div>
                    <Label htmlFor="edit_notes">{t('Notes')}</Label>
                    <Textarea
                        id="edit_notes"
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder={t('Enter notes')}
                        rows={3}
                    />
                    <InputError message={errors.notes} />
                </div>

                {/* Custom Fields */}
                {customFields.length > 0 && (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-1 gap-4">
                            {customFields.map((field) => (
                                <div key={field.id}>
                                    {field.component}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Updating...') : t('Update')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}

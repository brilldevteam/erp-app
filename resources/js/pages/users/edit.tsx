import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import InputError from "@/components/ui/input-error";
import { PhoneInputComponent } from "@/components/ui/phone-input";
import { EditUserProps, EditUserFormData } from './types';
import PackageSelector from './package-selector';

const isPlaceholderEmail = (email?: string | null) => {
    const value = (email ?? '').toLowerCase();
    return value === '' || value.endsWith('@import.local') || value.startsWith('zoho.customer.');
};

export default function Edit({ user, onSuccess, roles = {}, plans = [] }: EditUserProps) {
    const { t } = useTranslation();
    const initialEmail = isPlaceholderEmail(user.email) ? '' : (user.email ?? '');
    const { data, setData, put, processing, errors } = useForm<EditUserFormData>({
        name: user.name,
        email: initialEmail,
        mobile_no: user.mobile_no,
        is_enable_login: initialEmail !== '' && user.is_enable_login,
        plan_id: user.active_plan?.toString() ?? '',
        plan_duration: 'Month',
        plan_changed: false,
    });
    const hasEmail = data.email.trim() !== '';

    const handleEmailChange = (value: string) => {
        const willHaveEmail = value.trim() !== '';
        const hadEmail = data.email.trim() !== '';
        setData((current) => ({
            ...current,
            email: value,
            is_enable_login: willHaveEmail ? (hadEmail ? current.is_enable_login : true) : false,
        }));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('users.update', user.id), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{t('Edit User')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="edit_name">{t('Name')}</Label>
                    <Input
                        id="edit_name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t('Enter full name')}
                        required
                    />
                    <InputError message={errors.name} />
                </div>
                <div>
                    <Label htmlFor="edit_email">{t('Email')}</Label>
                    <Input
                        id="edit_email"
                        type="email"
                        value={data.email}
                        onChange={(e) => handleEmailChange(e.target.value)}
                        placeholder={t('Enter email address (optional)')}
                    />
                    <InputError message={errors.email} />
                </div>
                <div>
                    <PhoneInputComponent
                        label={t('Mobile Number')}
                        value={data.mobile_no}
                        onChange={(value) => setData('mobile_no', value)}
                        placeholder="+1234567890"
                        error={errors.mobile_no}
                    />
                </div>

                <div>
                    <Label htmlFor="edit_is_enable_login">{t('Login Status')}</Label>
                    <Select
                        value={data.is_enable_login ? "1" : "0"}
                        onValueChange={(value) => setData('is_enable_login', value === "1")}
                        disabled={!hasEmail}
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">{t('Enabled')}</SelectItem>
                            <SelectItem value="0">{t('Disabled')}</SelectItem>
                        </SelectContent>
                    </Select>
                    {!hasEmail && (
                        <p className="text-xs text-gray-500 mt-1">{t('Add an email to enable login access.')}</p>
                    )}
                    <InputError message={errors.is_enable_login} />
                </div>
                {user.type === 'company' && plans.length > 0 && (
                    <div className="space-y-4 border-t pt-4">
                        <PackageSelector
                            plans={plans}
                            planId={data.plan_id}
                            planDuration={data.plan_duration}
                            onPlanChange={(planId) => setData((current) => ({
                                ...current,
                                plan_id: planId,
                                plan_changed: true,
                            }))}
                            onDurationChange={(duration) => setData((current) => ({
                                ...current,
                                plan_duration: duration,
                                plan_changed: true,
                            }))}
                            planError={errors.plan_id}
                            durationError={errors.plan_duration}
                        />
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

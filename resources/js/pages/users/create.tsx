import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useForm, usePage, router } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import InputError from "@/components/ui/input-error";
import { PhoneInputComponent } from "@/components/ui/phone-input";
import { CreateUserProps, CreateUserFormData } from './types';

export default function Create({ onSuccess, roles = {} }: CreateUserProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const { data, setData, post, processing, errors } = useForm<CreateUserFormData>({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        mobile_no: '',
        type: '',
        is_enable_login: false,
    });

    const isSuperAdmin = auth.user?.type === 'superadmin';
    const hasEmail = data.email.trim() !== '';

    const handleEmailChange = (value: string) => {
        const willHaveEmail = value.trim() !== '';
        const hadEmail = data.email.trim() !== '';
        setData((current) => ({
            ...current,
            email: value,
            is_enable_login: willHaveEmail ? (hadEmail ? current.is_enable_login : true) : false,
            password: willHaveEmail ? current.password : '',
            password_confirmation: willHaveEmail ? current.password_confirmation : '',
        }));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('users.store'), {
            onSuccess: () => {
                onSuccess();
            }
        });
    };

    return (
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{t('Create User')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <Label htmlFor="name">{t('Name')}</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder={t('Enter full name')}
                        required
                    />
                    <InputError message={errors.name} />
                </div>
                <div>
                    <Label htmlFor="email">{t('Email')}</Label>
                    <Input
                        id="email"
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
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <Label htmlFor="password" required={data.is_enable_login}>{t('Password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder={hasEmail ? t('Enter password') : t('Add an email first')}
                            required={data.is_enable_login}
                            disabled={!hasEmail}
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div>
                        <Label htmlFor="password_confirmation" required={data.is_enable_login}>{t('Confirm Password')}</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            placeholder={hasEmail ? t('Confirm password') : t('Add an email first')}
                            required={data.is_enable_login}
                            disabled={!hasEmail}
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </div>
                <div className={`grid ${isSuperAdmin ? 'grid-cols-1' : 'grid-cols-2'} gap-4`}>
                    {!isSuperAdmin && (
                        <div>
                            <Label htmlFor="type" required>{t('Role')}</Label>
                            <Select value={data.type} onValueChange={(value) => setData('type', value)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(roles).map(([id, label]) => (
                                        <SelectItem key={id} value={id}>
                                            {label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {Object.keys(roles).length === 0 && auth.user?.permissions?.includes('create-roles') && (
                                <p className="text-xs text-gray-500 mb-1">
                                    {t('Create role here.')} <button onClick={() => router.get(route('roles.create'))} className="text-blue-600 hover:underline">{t('Create role')}</button>
                                </p>
                            )}
                            <InputError message={errors.type} />
                        </div>
                    )}
                    <div>
                        <Label htmlFor="is_enable_login">{t('Login Status')}</Label>
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
                </div>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? t('Creating...') : t('Create')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}

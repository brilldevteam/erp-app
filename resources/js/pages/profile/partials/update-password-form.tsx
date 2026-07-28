import { useRef, FormEventHandler } from "react";
import { useForm } from "@inertiajs/react";
import { useTranslation } from 'react-i18next';
import { Transition } from "@headlessui/react";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { InputError } from "@/components/ui/input-error";
import { Button } from "@/components/ui/button";
import { usePage } from "@inertiajs/react";
import { PageProps } from "@/types";
import { Checkbox } from "@/components/ui/checkbox";

export default function UpdatePasswordForm({
    className = "",
}: {
    className?: string;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
        logout_other_devices: false,
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route("password.update"), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset("password", "password_confirmation");
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset("current_password");
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <form onSubmit={updatePassword} className="mt-6 space-y-6">
                {auth.impersonating && (
                    <div className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {t('Leave Login As User to change password.')}
                    </div>
                )}

                <div>
                    <Label htmlFor="current_password">{t('Current Password')}</Label>

                    <Input
                        id="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData("current_password", e.target.value)
                        }
                        type="password"
                        className="mt-1 block w-full"
                        placeholder={t('Enter current password')}
                        autoComplete="current-password"
                        required
                    />

                    <InputError
                        message={errors.current_password}
                        className="mt-2"
                    />
                </div>

                <div>
                    <Label htmlFor="password">{t('New Password')}</Label>

                    <Input
                        id="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData("password", e.target.value)}
                        type="password"
                        className="mt-1 block w-full"
                        placeholder={t('Enter new password')}
                        autoComplete="new-password"
                        required
                    />

                    <InputError message={errors.password} className="mt-2" />
                    <p className="mt-2 text-xs text-gray-500">
                        {t('Use at least 8 characters with uppercase, lowercase, number, and symbol.')}
                    </p>
                </div>

                <div>
                    <Label htmlFor="password_confirmation">
                        {t('Confirm Password')}
                    </Label>

                    <Input
                        id="password_confirmation"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData("password_confirmation", e.target.value)
                        }
                        required
                        type="password"
                        className="mt-1 block w-full"
                        placeholder={t('Confirm new password')}
                        autoComplete="new-password"
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <Label className="flex items-center gap-3 text-sm font-normal">
                    <Checkbox
                        checked={data.logout_other_devices}
                        onCheckedChange={(checked) => setData('logout_other_devices', checked === true)}
                        disabled={processing || auth.impersonating}
                    />
                    {t('Log out from other devices after changing password')}
                </Label>

                {auth.user?.permissions?.includes('change-password-profile') && (
                    <div className="flex items-center justify-end gap-4">
                        <Button disabled={processing || auth.impersonating}>{t('Save Changes')}</Button>
                    </div>
                )}
            </form>
        </section>
    );
}

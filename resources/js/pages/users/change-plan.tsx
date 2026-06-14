import { Button } from '@/components/ui/button';
import { DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import PackageSelector from './package-selector';
import { ChangePlanProps, EditUserFormData } from './types';

export default function ChangePlan({ user, plans, onSuccess }: ChangePlanProps) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm<EditUserFormData>({
        name: user.name,
        email: user.email,
        mobile_no: user.mobile_no,
        is_enable_login: user.is_enable_login,
        plan_id: user.active_plan?.toString() ?? '',
        plan_duration: 'Month',
        plan_changed: false,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        put(route('users.update', user.id), { onSuccess });
    };

    return (
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{t('Change Plan')} - {user.name}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-5">
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
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onSuccess}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={processing || !data.plan_id || !data.plan_changed}>
                        {processing ? t('Updating...') : t('Change Plan')}
                    </Button>
                </div>
            </form>
        </DialogContent>
    );
}

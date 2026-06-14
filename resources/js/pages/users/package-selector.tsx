import { Label } from '@/components/ui/label';
import InputError from '@/components/ui/input-error';
import { formatAdminCurrency, formatStorage } from '@/utils/helpers';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { UserPlan } from './types';

interface PackageSelectorProps {
    plans: UserPlan[];
    planId: string;
    planDuration: 'Month' | 'Year';
    onPlanChange: (planId: string) => void;
    onDurationChange: (duration: 'Month' | 'Year') => void;
    planError?: string;
    durationError?: string;
}

export default function PackageSelector({
    plans,
    planId,
    planDuration,
    onPlanChange,
    onDurationChange,
    planError,
    durationError,
}: PackageSelectorProps) {
    const { t } = useTranslation();
    const selectedPlan = plans.find((plan) => plan.id.toString() === planId);

    return (
        <div className="space-y-4">
            <div>
                <Label>{t('Package')}</Label>
                <p className="mt-1 text-sm text-muted-foreground">{t('Select a package')}</p>
                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                    {plans.map((plan) => {
                        const isSelected = planId === plan.id.toString();
                        const price = planDuration === 'Year'
                            ? plan.package_price_yearly
                            : plan.package_price_monthly;

                        return (
                            <button
                                key={plan.id}
                                type="button"
                                aria-pressed={isSelected}
                                onClick={() => onPlanChange(plan.id.toString())}
                                className={`relative rounded-xl border p-4 text-left transition-all ${
                                    isSelected
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                        : 'border-border bg-background hover:border-primary/50 hover:bg-muted/30'
                                }`}
                            >
                                {isSelected && (
                                    <span className="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                        <Check className="h-4 w-4" />
                                    </span>
                                )}
                                <div className="pr-8">
                                    <h4 className="font-semibold text-foreground">{plan.name}</h4>
                                    {plan.description && (
                                        <p className="mt-1 line-clamp-2 text-xs text-muted-foreground">
                                            {plan.description}
                                        </p>
                                    )}
                                </div>
                                <div className="mt-3 text-xl font-bold text-primary">
                                    {plan.free_plan
                                        ? t('Free')
                                        : `${formatAdminCurrency(price)}/${planDuration === 'Year' ? t('yr') : t('mo')}`}
                                </div>
                                <div className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-muted-foreground">
                                    <span>
                                        {plan.number_of_users === -1
                                            ? t('Unlimited users')
                                            : `${plan.number_of_users} ${t('users')}`}
                                    </span>
                                    <span>{formatStorage(plan.storage_limit)} {t('storage')}</span>
                                    <span>{plan.modules?.length ?? 0} {t('modules')}</span>
                                    {plan.trial && (
                                        <span className="text-green-600">
                                            {plan.trial_days}{t('d trial')}
                                        </span>
                                    )}
                                </div>
                            </button>
                        );
                    })}
                </div>
                <InputError message={planError} />
            </div>

            {selectedPlan && !selectedPlan.free_plan && (
                <div>
                    <Label>{t('Billing Period')}</Label>
                    <div className="mt-2 grid grid-cols-2 gap-3">
                        {(['Month', 'Year'] as const).map((period) => {
                            const isSelected = planDuration === period;
                            const price = period === 'Year'
                                ? selectedPlan.package_price_yearly
                                : selectedPlan.package_price_monthly;

                            return (
                                <button
                                    key={period}
                                    type="button"
                                    aria-pressed={isSelected}
                                    onClick={() => onDurationChange(period)}
                                    className={`rounded-lg border px-4 py-3 text-left transition-all ${
                                        isSelected
                                            ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                            : 'border-border hover:border-primary/50'
                                    }`}
                                >
                                    <span className="block font-medium">
                                        {period === 'Month' ? t('Monthly') : t('Yearly')}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {formatAdminCurrency(price)}
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    <InputError message={durationError} />
                </div>
            )}
        </div>
    );
}

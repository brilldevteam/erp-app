import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import { RefreshCw, Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';

interface SuperAdminRecurringInvoiceBillSettingsProps {
  userSettings?: Record<string, string>;
  auth?: any;
}

export default function SuperAdminRecurringInvoiceBillSettings({ userSettings = {}, auth }: SuperAdminRecurringInvoiceBillSettingsProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);
  const canEdit = auth?.user?.permissions?.includes('manage-recurring-invoice-bill');

  const [settings, setSettings] = useState({
    recurring_sales_purchase_invoices: userSettings?.recurring_sales_purchase_invoices === 'on'
  });

  useEffect(() => {
    setSettings({
      recurring_sales_purchase_invoices: userSettings?.recurring_sales_purchase_invoices === 'on'
    });
  }, [userSettings]);

  const handleSwitchChange = (checked: boolean) => {
    setSettings(prev => ({
      ...prev,
      recurring_sales_purchase_invoices: checked
    }));
  };

  const saveSettings = () => {
    setIsLoading(true);

    router.post(route('recurring-invoice-bill.superadmin.settings.store'), {
      recurring_sales_purchase_invoices: settings.recurring_sales_purchase_invoices ? 'on' : 'off'
    }, {
      preserveScroll: true,
      onSuccess: (page) => {
        setIsLoading(false);
        const successMessage = (page.props.flash as any)?.success;
        const errorMessage = (page.props.flash as any)?.error;

        if (successMessage) {
          toast.success(successMessage);
        } else if (errorMessage) {
          toast.error(errorMessage);
        }
      },
      onError: (errors) => {
        setIsLoading(false);
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to save settings');
        toast.error(errorMessage);
      }
    });
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="flex items-center gap-2 text-lg">
            <RefreshCw className="h-5 w-5" />
            {t('Recurring Sales & Purchase Invoice Settings')}
          </CardTitle>
          <p className="text-sm text-muted-foreground mt-1">
            {t('Configure global settings for recurring sales and purchase invoices')}
          </p>
        </div>
        {canEdit && (
          <Button onClick={saveSettings} disabled={isLoading} size="sm">
            <Save className="h-4 w-4 mr-2" />
            {isLoading ? t('Saving...') : t('Save Changes')}
          </Button>
        )}
      </CardHeader>
      <CardContent>
        <div className="space-y-6">
          <div className="flex items-center justify-between p-3 border rounded-lg">
            <div>
              <Label htmlFor="recurring_sales_purchase_invoices" className="text-base font-medium">
                {t('Enable Recurring Sales & Purchase Invoices')}
              </Label>
              <p className="text-sm text-muted-foreground mt-1">
                {t('Allow automatic generation of recurring sales and purchase invoices globally')}
              </p>
            </div>
            <Switch
              id="recurring_sales_purchase_invoices"
              checked={settings.recurring_sales_purchase_invoices}
              onCheckedChange={handleSwitchChange}
              disabled={!canEdit}
            />
          </div>

          <div className="space-y-4">
            <div>
              <Label className="text-base font-medium">{t('Note')}</Label>
              <p className="text-sm text-muted-foreground mt-1">
                {t('With the recurring sales invoices & purchase invoices button enabled in settings, easily customize the duplication frequency using the custom button. Choose the desired interval for sales invoice & purchase invoice duplication or set it to infinity for seamless management of recurring sales invoice & purchase invoice cycles.')}
              </p>
            </div>

            <div>
              <Label className="text-base font-medium">{t('Recurring Sales Invoice & Purchase Invoice Cronjob Instruction')}</Label>
              <div className="space-y-2 text-sm text-muted-foreground mt-2">
                <p>{t('1. If you would like to create automatically Recurring Sales Invoice and Purchase Invoice you need set a cron job for that which one run like every day.')}</p>
                <div className="bg-muted p-3 rounded text-xs font-mono">
                  {`0 0 * * * domain && php artisan recurring:sales-purchase-invoices >/dev/null 2>&1`}
                </div>
                <p>{t('2. Example url as')}</p>
                <div className="bg-muted p-3 rounded text-xs font-mono">
                  /usr/local/bin/ea-php82 /home/project/public_html/dash-demo.workdo.io/artisan recurring:sales-purchase-invoices
                </div>
              </div>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

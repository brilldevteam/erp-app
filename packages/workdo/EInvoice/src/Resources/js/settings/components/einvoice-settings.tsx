import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import { FileText, Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { router } from '@inertiajs/react';

interface EInvoiceSettingsProps {
  userSettings?: Record<string, string>;
  auth?: any;
}

export default function EInvoiceSettings({ userSettings = {}, auth }: EInvoiceSettingsProps) {
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false);
  const canEdit = auth?.user?.permissions?.includes('edit-einvoice-settings');

  const [settings, setSettings] = useState({
    electronic_address: userSettings?.electronic_address || '',
    company_id: userSettings?.company_id || '',
    electronic_address_schema: userSettings?.electronic_address_schema || '',
    company_id_schema: userSettings?.company_id_schema || ''
  });

  useEffect(() => {
    setSettings({
      electronic_address: userSettings?.electronic_address || '',
      company_id: userSettings?.company_id || '',
      electronic_address_schema: userSettings?.electronic_address_schema || '',
      company_id_schema: userSettings?.company_id_schema || ''
    });
  }, [userSettings]);

  const handleChange = (field: string, value: string) => {
    setSettings(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const saveSettings = () => {
    setIsLoading(true);

    router.post(route('einvoice.settings.store'), {
      settings
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
        const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to save EInvoice settings');
        toast.error(errorMessage);
      }
    });
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div className="order-1 rtl:order-2">
          <CardTitle className="flex items-center gap-2 text-lg">
            <FileText className="h-5 w-5" />
            {t('E-Invoice Settings')}
          </CardTitle>
          <p className="text-sm text-muted-foreground mt-1">
            {t('Configure electronic invoice settings')}
          </p>
        </div>
        {canEdit && (
          <Button className="order-2 rtl:order-1" onClick={saveSettings} disabled={isLoading} size="sm">
            <Save className="h-4 w-4 mr-2" />
            {isLoading ? t('Saving...') : t('Save Changes')}
          </Button>
        )}
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div className="space-y-3">
            <Label htmlFor="electronic_address">{t('Electronic Address')}</Label>
            <Input
              id="electronic_address"
              value={settings.electronic_address}
              onChange={(e) => handleChange('electronic_address', e.target.value)}
              placeholder={t('Enter electronic address')}
              disabled={!canEdit}
            />
          </div>

          <div className="space-y-3">
            <Label htmlFor="company_id">{t('Company ID')}</Label>
            <Input
              id="company_id"
              value={settings.company_id}
              onChange={(e) => handleChange('company_id', e.target.value)}
              placeholder={t('Enter company ID')}
              disabled={!canEdit}
            />
          </div>

          <div className="space-y-3">
            <Label htmlFor="electronic_address_schema">{t('Electronic Address Schema')}</Label>
            <Input
              id="electronic_address_schema"
              value={settings.electronic_address_schema}
              onChange={(e) => handleChange('electronic_address_schema', e.target.value)}
              placeholder={t('Enter electronic address schema')}
              disabled={!canEdit}
            />
          </div>

          <div className="space-y-3">
            <Label htmlFor="company_id_schema">{t('Company ID Schema')}</Label>
            <Input
              id="company_id_schema"
              value={settings.company_id_schema}
              onChange={(e) => handleChange('company_id_schema', e.target.value)}
              placeholder={t('Enter company ID schema')}
              disabled={!canEdit}
            />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
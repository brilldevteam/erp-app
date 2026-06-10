import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Eye, EyeOff, KeyRound, Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

interface SocialAuthSettings {
  social_login_enabled: boolean;
  google_login_enabled: boolean;
  google_client_id: string;
  google_client_secret_configured: boolean;
  google_callback_url: string;
  microsoft_login_enabled: boolean;
  microsoft_client_id: string;
  microsoft_client_secret_configured: boolean;
  microsoft_tenant_id: string;
  microsoft_callback_url: string;
}

interface Props {
  socialAuthSettings?: SocialAuthSettings;
  auth?: any;
}

const emptySettings: SocialAuthSettings = {
  social_login_enabled: false,
  google_login_enabled: false,
  google_client_id: '',
  google_client_secret_configured: false,
  google_callback_url: '',
  microsoft_login_enabled: false,
  microsoft_client_id: '',
  microsoft_client_secret_configured: false,
  microsoft_tenant_id: 'common',
  microsoft_callback_url: '',
};

export default function SocialLoginSettings({ socialAuthSettings, auth }: Props) {
  const { t } = useTranslation();
  const canEdit = auth?.user?.permissions?.includes('edit-social-login-settings');
  const [settings, setSettings] = useState({ ...emptySettings, ...socialAuthSettings });
  const [googleSecret, setGoogleSecret] = useState('');
  const [microsoftSecret, setMicrosoftSecret] = useState('');
  const [clearGoogleSecret, setClearGoogleSecret] = useState(false);
  const [clearMicrosoftSecret, setClearMicrosoftSecret] = useState(false);
  const [showGoogleSecret, setShowGoogleSecret] = useState(false);
  const [showMicrosoftSecret, setShowMicrosoftSecret] = useState(false);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setSettings({ ...emptySettings, ...socialAuthSettings });
    setGoogleSecret('');
    setMicrosoftSecret('');
    setClearGoogleSecret(false);
    setClearMicrosoftSecret(false);
  }, [socialAuthSettings]);

  const update = <K extends keyof SocialAuthSettings>(key: K, value: SocialAuthSettings[K]) => {
    setSettings((current) => ({ ...current, [key]: value }));
  };

  const save = () => {
    setSaving(true);
    router.post(route('settings.social-login.update'), {
      settings: {
        social_login_enabled: settings.social_login_enabled,
        google_login_enabled: settings.google_login_enabled,
        google_client_id: settings.google_client_id,
        google_client_secret: googleSecret,
        clear_google_client_secret: clearGoogleSecret,
        microsoft_login_enabled: settings.microsoft_login_enabled,
        microsoft_client_id: settings.microsoft_client_id,
        microsoft_client_secret: microsoftSecret,
        clear_microsoft_client_secret: clearMicrosoftSecret,
        microsoft_tenant_id: settings.microsoft_tenant_id,
      },
    }, {
      preserveScroll: true,
      onSuccess: (page) => {
        const flash = page.props.flash as any;
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
      },
      onError: (errors) => {
        toast.error(Object.values(errors).join(', ') || t('Failed to save social login settings'));
      },
      onFinish: () => setSaving(false),
    });
  };

  const providerCard = (
    provider: 'google' | 'microsoft',
    title: string,
    enabled: boolean,
    clientId: string,
    secretConfigured: boolean,
    callbackUrl: string,
  ) => {
    const isGoogle = provider === 'google';
    const secret = isGoogle ? googleSecret : microsoftSecret;
    const setSecret = isGoogle ? setGoogleSecret : setMicrosoftSecret;
    const clearSecret = isGoogle ? clearGoogleSecret : clearMicrosoftSecret;
    const setClearSecret = isGoogle ? setClearGoogleSecret : setClearMicrosoftSecret;
    const showSecret = isGoogle ? showGoogleSecret : showMicrosoftSecret;
    const setShowSecret = isGoogle ? setShowGoogleSecret : setShowMicrosoftSecret;

    return (
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between gap-4">
            <div>
              <CardTitle className="text-base">{title}</CardTitle>
              <p className="mt-1 text-sm text-muted-foreground">
                {secretConfigured && clientId
                  ? t('Provider credentials are configured')
                  : t('Provider credentials are incomplete')}
              </p>
            </div>
            <Switch
              checked={enabled}
              onCheckedChange={(checked) => {
                if (provider === 'google') update('google_login_enabled', checked);
                else update('microsoft_login_enabled', checked);
              }}
              disabled={!canEdit}
              aria-label={t(`Enable ${title}`)}
            />
          </div>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="space-y-2">
            <Label htmlFor={`${provider}-client-id`}>{t('Client ID')}</Label>
            <Input
              id={`${provider}-client-id`}
              value={clientId}
              onChange={(event) => {
                if (provider === 'google') update('google_client_id', event.target.value);
                else update('microsoft_client_id', event.target.value);
              }}
              disabled={!canEdit}
              autoComplete="off"
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor={`${provider}-client-secret`}>{t('Client Secret')}</Label>
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Input
                  id={`${provider}-client-secret`}
                  type={showSecret ? 'text' : 'password'}
                  value={secret}
                  onChange={(event) => {
                    setSecret(event.target.value);
                    setClearSecret(false);
                  }}
                  disabled={!canEdit || clearSecret}
                  placeholder={secretConfigured ? t('Configured - leave blank to keep') : t('Enter client secret')}
                  autoComplete="new-password"
                  className="pr-10"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="absolute right-0 top-0 h-full px-3"
                  onClick={() => setShowSecret(!showSecret)}
                  disabled={!canEdit || clearSecret}
                  aria-label={showSecret ? t('Hide secret') : t('Show secret')}
                >
                  {showSecret ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </Button>
              </div>
              <Button
                type="button"
                variant={clearSecret ? 'destructive' : 'outline'}
                onClick={() => {
                  setClearSecret(!clearSecret);
                  setSecret('');
                }}
                disabled={!canEdit || (!secretConfigured && !clearSecret)}
              >
                <Trash2 className="mr-2 h-4 w-4" />
                {clearSecret ? t('Will clear') : t('Clear')}
              </Button>
            </div>
          </div>

          {provider === 'microsoft' && (
            <div className="space-y-2">
              <Label htmlFor="microsoft-tenant-id">{t('Tenant ID')}</Label>
              <Input
                id="microsoft-tenant-id"
                value={settings.microsoft_tenant_id}
                onChange={(event) => update('microsoft_tenant_id', event.target.value)}
                disabled={!canEdit}
                placeholder="common"
              />
              <p className="text-xs text-muted-foreground">
                {t('Use common for both personal and organizational Microsoft accounts.')}
              </p>
            </div>
          )}

          <div className="space-y-2">
            <Label>{t('Callback URL')}</Label>
            <Input value={callbackUrl} readOnly />
            <p className="text-xs text-muted-foreground">
              {t('Add this exact URL to the provider application configuration.')}
            </p>
          </div>
        </CardContent>
      </Card>
    );
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="flex items-center gap-2 text-lg">
            <KeyRound className="h-5 w-5" />
            {t('Social Login Settings')}
          </CardTitle>
          <p className="mt-1 text-sm text-muted-foreground">
            {t('Configure Google and Microsoft authentication for login and signup.')}
          </p>
        </div>
        {canEdit && (
          <Button onClick={save} disabled={saving} size="sm">
            <Save className="mr-2 h-4 w-4" />
            {saving ? t('Saving...') : t('Save Changes')}
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="flex items-center justify-between rounded-lg border p-4">
          <div>
            <Label htmlFor="social-login-enabled" className="text-base">{t('Enable Social Login')}</Label>
            <p className="text-sm text-muted-foreground">
              {t('Master switch for all social login and signup providers.')}
            </p>
          </div>
          <Switch
            id="social-login-enabled"
            checked={settings.social_login_enabled}
            onCheckedChange={(checked) => update('social_login_enabled', checked)}
            disabled={!canEdit}
          />
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          {providerCard(
            'google',
            'Google',
            settings.google_login_enabled,
            settings.google_client_id,
            settings.google_client_secret_configured,
            settings.google_callback_url,
          )}
          {providerCard(
            'microsoft',
            'Microsoft',
            settings.microsoft_login_enabled,
            settings.microsoft_client_id,
            settings.microsoft_client_secret_configured,
            settings.microsoft_callback_url,
          )}
        </div>
      </CardContent>
    </Card>
  );
}

import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

interface Props {
  intent: 'login' | 'signup';
  termsAccepted?: boolean;
  termsRequired?: boolean;
}

export default function SocialAuthButtons({
  intent,
  termsAccepted = false,
  termsRequired = false,
}: Props) {
  const { t } = useTranslation();
  const { socialAuth } = usePage().props as any;
  const [loadingProvider, setLoadingProvider] = useState<string | null>(null);
  const providers = [
    { key: 'google', label: 'Google', enabled: socialAuth?.google?.enabled },
    { key: 'microsoft', label: 'Microsoft', enabled: socialAuth?.microsoft?.enabled },
  ].filter((provider) => provider.enabled);

  if (!socialAuth?.enabled || providers.length === 0) return null;

  const start = (provider: string) => {
    if (intent === 'signup' && termsRequired && !termsAccepted) return;
    setLoadingProvider(provider);
    window.location.href = route('social.redirect', {
      provider,
      intent,
      terms_accepted: termsAccepted ? 1 : 0,
    });
  };

  return (
    <div className="space-y-3">
      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <span className="w-full border-t" />
        </div>
        <div className="relative flex justify-center text-xs uppercase">
          <span className="bg-background px-2 text-muted-foreground">{t('Or continue with')}</span>
        </div>
      </div>
      <div className={providers.length > 1 ? 'grid gap-3 sm:grid-cols-2' : 'grid gap-3'}>
        {providers.map((provider) => (
          <Button
            key={provider.key}
            type="button"
            variant="outline"
            onClick={() => start(provider.key)}
            disabled={loadingProvider !== null || (intent === 'signup' && termsRequired && !termsAccepted)}
            aria-label={t(`Continue with ${provider.label}`)}
          >
            {provider.key === 'google' ? <GoogleIcon /> : <MicrosoftIcon />}
            <span className="ml-2">
              {loadingProvider === provider.key ? t('Redirecting...') : provider.label}
            </span>
          </Button>
        ))}
      </div>
    </div>
  );
}

function GoogleIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
      <path fill="#4285F4" d="M21.6 12.2c0-.7-.1-1.4-.2-2H12v3.9h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.3 3-7.4Z" />
      <path fill="#34A853" d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1a5.8 5.8 0 0 1-5.5-4H3.2v2.6A10 10 0 0 0 12 22Z" />
      <path fill="#FBBC05" d="M6.5 14.1a6 6 0 0 1 0-4.2V7.3H3.2a10 10 0 0 0 0 9.4l3.3-2.6Z" />
      <path fill="#EA4335" d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.9-2.8A9.7 9.7 0 0 0 3.2 7.3l3.3 2.6a5.8 5.8 0 0 1 5.5-4Z" />
    </svg>
  );
}

function MicrosoftIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-4 w-4" aria-hidden="true">
      <path fill="#F25022" d="M2 2h9.5v9.5H2z" />
      <path fill="#7FBA00" d="M12.5 2H22v9.5h-9.5z" />
      <path fill="#00A4EF" d="M2 12.5h9.5V22H2z" />
      <path fill="#FFB900" d="M12.5 12.5H22V22h-9.5z" />
    </svg>
  );
}

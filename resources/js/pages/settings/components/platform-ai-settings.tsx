import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Bot, Save, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Props { userSettings?: Record<string, any>; auth?: any; }
interface Provider { name: string; models: string[]; }

export default function PlatformAISettings({ userSettings = {}, auth }: Props) {
  const { t } = useTranslation();
  const canEdit = auth?.user?.permissions?.includes('edit-platform-ai-settings');
  const [providers, setProviders] = useState<Record<string, Provider>>({});
  const [saving, setSaving] = useState(false);
  const [settings, setSettings] = useState({
    ai_agent_provider: userSettings.ai_agent_provider || 'openai',
    ai_agent_model: userSettings.ai_agent_model || '',
    ai_agent_api_key: '',
    clear_ai_agent_api_key: false,
    ai_provider: userSettings.ai_provider || 'openai',
    ai_model: userSettings.ai_model || '',
    ai_api_key: '',
    clear_ai_api_key: false,
  });

  useEffect(() => {
    fetch(route('settings.ai-agent.providers')).then((response) => response.json()).then((data) => setProviders(data.providers || {}));
  }, []);

  const update = (key: string, value: string | boolean) => setSettings((current) => ({ ...current, [key]: value }));
  const save = () => {
    setSaving(true);
    router.post(route('settings.platform-ai.update'), { settings }, {
      preserveScroll: true,
      onSuccess: () => toast.success(t('AI settings saved successfully.')),
      onError: (errors) => toast.error(Object.values(errors).join(', ')),
      onFinish: () => setSaving(false),
    });
  };

  const section = (title: string, prefix: 'ai_agent' | 'ai') => {
    const providerKey = `${prefix}_provider` as keyof typeof settings;
    const modelKey = `${prefix}_model` as keyof typeof settings;
    const secretKey = `${prefix}_api_key` as keyof typeof settings;
    const clearKey = `clear_${prefix}_api_key` as keyof typeof settings;
    const provider = String(settings[providerKey]);
    const configured = Boolean(userSettings[`${prefix}_api_key_configured`]);
    return <div className="space-y-4 rounded-md border p-4">
      <h3 className="font-medium">{t(title)}</h3>
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2"><Label>{t('Provider')}</Label><Select value={provider} onValueChange={(value) => { update(providerKey, value); update(modelKey, ''); }} disabled={!canEdit}><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>{Object.entries(providers).map(([key, item]) => <SelectItem key={key} value={key}>{item.name}</SelectItem>)}</SelectContent></Select></div>
        <div className="space-y-2"><Label>{t('Model')}</Label><Select value={String(settings[modelKey])} onValueChange={(value) => update(modelKey, value)} disabled={!canEdit}><SelectTrigger><SelectValue placeholder={t('Select model')} /></SelectTrigger><SelectContent>{(providers[provider]?.models || []).map((model) => <SelectItem key={model} value={model}>{model}</SelectItem>)}</SelectContent></Select></div>
      </div>
      <div className="space-y-2"><Label>{t('API Key')}</Label><div className="flex gap-2"><Input type="password" value={String(settings[secretKey])} onChange={(event) => { update(secretKey, event.target.value); update(clearKey, false); }} placeholder={configured ? t('Configured - leave blank to keep') : t('Enter API key')} disabled={!canEdit || Boolean(settings[clearKey])} /><Button type="button" variant={settings[clearKey] ? 'destructive' : 'outline'} size="icon" title={t('Clear stored API key')} onClick={() => { update(secretKey, ''); update(clearKey, !settings[clearKey]); }} disabled={!canEdit || !configured}><Trash2 className="h-4 w-4" /></Button></div></div>
    </div>;
  };

  return <Card><CardHeader className="flex flex-row items-center justify-between"><div><CardTitle className="flex items-center gap-2 text-lg"><Bot className="h-5 w-5" />{t('AI Settings')}</CardTitle><p className="mt-1 text-sm text-muted-foreground">{t('Manage the central AI services available to companies.')}</p></div>{canEdit && <Button size="sm" onClick={save} disabled={saving}><Save className="mr-2 h-4 w-4" />{t('Save Changes')}</Button>}</CardHeader><CardContent className="space-y-4">{section('AI Agent', 'ai_agent')}{section('AI Assistant', 'ai')}</CardContent></Card>;
}

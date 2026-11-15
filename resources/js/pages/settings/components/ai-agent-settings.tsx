import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { Bot, Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';

interface Props { userSettings?: Record<string, string>; auth?: any; }

export default function AIAgentSettings({ userSettings = {}, auth }: Props) {
  const { t } = useTranslation();
  const canEdit = auth?.user?.permissions?.includes('edit-ai-agent-settings');
  const initial = userSettings.ai_agent_enabled !== '0' && userSettings.ai_agent_enabled !== 'false';
  const [enabled, setEnabled] = useState(initial);
  const [saving, setSaving] = useState(false);

  useEffect(() => setEnabled(userSettings.ai_agent_enabled !== '0' && userSettings.ai_agent_enabled !== 'false'), [userSettings.ai_agent_enabled]);

  const save = () => {
    setSaving(true);
    router.post(route('settings.ai-agent.update'), { settings: { ai_agent_enabled: enabled } }, {
      preserveScroll: true,
      onSuccess: () => toast.success(t('AI Agent settings saved successfully.')),
      onError: (errors) => toast.error(Object.values(errors).join(', ')),
      onFinish: () => setSaving(false),
    });
  };

  return <Card>
    <CardHeader className="flex flex-row items-center justify-between">
      <div><CardTitle className="flex items-center gap-2 text-lg"><Bot className="h-5 w-5" />{t('AI Agent')}</CardTitle><p className="mt-1 text-sm text-muted-foreground">{t('Allow this company to use the centrally managed AI Agent.')}</p></div>
      {canEdit && <Button size="sm" onClick={save} disabled={saving}><Save className="mr-2 h-4 w-4" />{t('Save Changes')}</Button>}
    </CardHeader>
    <CardContent className="flex items-center justify-between rounded-md border p-4"><span>{t('Enable AI Agent')}</span><Switch checked={enabled} onCheckedChange={setEnabled} disabled={!canEdit} /></CardContent>
  </Card>;
}

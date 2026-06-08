import { useState, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { toast } from 'sonner';
import { Save } from 'lucide-react';
import SystemSetupSidebar from '../SystemSetupSidebar';

interface PrivacyPolicyProps {
    settings: {
        content: string;
        enabled: boolean;
    };
    auth: any;
}

export default function PrivacyPolicy() {
    const { t } = useTranslation();
    const { settings, auth } = usePage<PrivacyPolicyProps>().props;
    const [isLoading, setIsLoading] = useState(false);
    const canEdit = auth?.user?.permissions?.includes('manage-appointment-settings');

    const [formSettings, setFormSettings] = useState({
        content: settings?.content || '',
        enabled: settings?.enabled || false
    });

    const [errors, setErrors] = useState<{[key: string]: string}>({});

    useEffect(() => {
        if (settings) {
            setFormSettings({
                content: settings?.content || '',
                enabled: settings?.enabled || false
            });
        }
    }, [settings]);

    const handleContentChange = (value: string) => {
        setFormSettings(prev => ({ ...prev, content: value }));
    };

    const handleEnabledChange = (checked: boolean) => {
        setFormSettings(prev => ({ ...prev, enabled: checked }));
    };

    const validateForm = () => {
        const newErrors: {[key: string]: string} = {};

        if (!formSettings.content.trim()) {
            newErrors.content = t('Privacy policy content is required');
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const saveSettings = () => {
        if (!validateForm()) {
            return;
        }

        setIsLoading(true);

        router.post(route('appointment.settings.privacy.update'), {
            settings: formSettings
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
                toast.success(t('Privacy Policy settings saved successfully'));
            },
            onError: (errors) => {
                setIsLoading(false);
                const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to save Privacy Policy settings');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Appointment'), url: route('appointment.index') },
                { label: t('System Setup') },
                { label: t('Privacy Policy') }
            ]}
            pageTitle={t('System Setup')}
        >
            <Head title={t('Privacy Policy')} />

            <div className="flex flex-col md:flex-row gap-8">
                <div className="md:w-64 flex-shrink-0">
                    <SystemSetupSidebar activeItem="privacy-policy" />
                </div>

                <div className="flex-1">
                    <Card className="shadow-sm">
                        <CardContent className="p-6">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium">{t('Privacy Policy')}</h3>
                            </div>

                            <div className="space-y-6">
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="enabled"
                                        checked={formSettings.enabled}
                                        onCheckedChange={handleEnabledChange}
                                        disabled={!canEdit}
                                    />
                                    <Label htmlFor="enabled">{t('Enable Privacy Policy')}</Label>
                                </div>

                                <div className="space-y-3">
                                    <Label htmlFor="content">{t('Privacy Policy Content')}</Label>
                                    <div className={errors.content ? 'border border-red-500 rounded-md' : ''}>
                                        <RichTextEditor
                                            content={formSettings.content}
                                            onChange={handleContentChange}
                                            placeholder={t('Enter privacy policy content...')}
                                            disabled={!canEdit}
                                        />
                                    </div>
                                    {errors.content && <p className="text-sm text-red-500">{errors.content}</p>}
                                </div>
                            </div>

                            {canEdit && (
                                <div className="flex justify-end pt-6 border-t">
                                    <Button onClick={saveSettings} disabled={isLoading}>
                                        <Save className="h-4 w-4 mr-2" />
                                        {isLoading ? t('Saving...') : t('Save Changes')}
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
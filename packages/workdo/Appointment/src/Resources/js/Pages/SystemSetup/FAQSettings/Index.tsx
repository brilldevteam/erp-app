import { useState, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import { Repeater } from '@/components/ui/repeater';
import { toast } from 'sonner';
import { Save } from 'lucide-react';
import SystemSetupSidebar from '../SystemSetupSidebar';

interface FAQQuestion {
    title: string;
    description: string;
}

interface FAQSettingsProps {
    settings: {
        faq_title: string;
        faq_description: string;
        faq_questions: FAQQuestion[];
    };
    auth: any;
}

export default function FAQSettings() {
    const { t } = useTranslation();
    const { settings, auth } = usePage<FAQSettingsProps>().props;
    const [isLoading, setIsLoading] = useState(false);
    const canEdit = auth?.user?.permissions?.includes('manage-appointment-settings');

    const [formSettings, setFormSettings] = useState({
        faq_title: settings?.faq_title || '',
        faq_description: settings?.faq_description || '',
        faq_questions: (settings?.faq_questions || [{ title: '', description: '' }]).map((q, index) => ({
            id: `faq_${index}`,
            title: q.title,
            description: q.description
        }))
    });

    const [errors, setErrors] = useState<{[key: string]: string}>({});

    useEffect(() => {
        if (settings) {
            setFormSettings({
                faq_title: settings?.faq_title || '',
                faq_description: settings?.faq_description || '',
                faq_questions: (settings?.faq_questions || [{ title: '', description: '' }]).map((q, index) => ({
                    id: `faq_${index}`,
                    title: q.title,
                    description: q.description
                }))
            });
        }
    }, [settings]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setFormSettings(prev => ({ ...prev, [name]: value }));
    };

    const handleQuestionChange = (questions: any[]) => {
        setFormSettings(prev => ({
            ...prev,
            faq_questions: questions
        }));
    };

    const validateForm = () => {
        const newErrors: {[key: string]: string} = {};

        if (!formSettings.faq_title.trim()) {
            newErrors.faq_title = t('FAQ title is required');
        }
        if (!formSettings.faq_description.trim()) {
            newErrors.faq_description = t('FAQ description is required');
        }

        formSettings.faq_questions.forEach((question, index) => {
            if (!question.title?.trim()) {
                newErrors[`question_title_${index}`] = t('Question title is required');
            }
            if (!question.description?.trim()) {
                newErrors[`question_description_${index}`] = t('Question description is required');
            }
        });

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const saveSettings = () => {
        if (!validateForm()) {
            return;
        }

        setIsLoading(true);

        router.post(route('appointment.settings.faq.update'), {
            settings: {
                ...formSettings,
                faq_questions: formSettings.faq_questions.map(q => ({
                    title: q.title,
                    description: q.description
                }))
            }
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsLoading(false);
                toast.success(t('FAQ settings saved successfully'));
            },
            onError: (errors) => {
                setIsLoading(false);
                const errorMessage = errors.error || Object.values(errors).join(', ') || t('Failed to save FAQ settings');
                toast.error(errorMessage);
            }
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Appointment'), url: route('appointment.index') },
                { label: t('System Setup') },
                { label: t('FAQ Settings') }
            ]}
            pageTitle={t('System Setup')}
        >
            <Head title={t('FAQ Settings')} />

            <div className="flex flex-col md:flex-row gap-8">
                <div className="md:w-64 flex-shrink-0">
                    <SystemSetupSidebar activeItem="faq-settings" />
                </div>

                <div className="flex-1">
                    <Card className="shadow-sm">
                        <CardContent className="p-6">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium">{t('FAQ Settings')}</h3>
                            </div>

                            <div className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="space-y-3">
                                        <Label htmlFor="faq_title" required>{t('FAQ Title')} </Label>
                                        <Input
                                            id="faq_title"
                                            name="faq_title"
                                            value={formSettings.faq_title}
                                            onChange={handleInputChange}
                                            placeholder={t('Enter FAQ title')}
                                            disabled={!canEdit}
                                            className={errors.faq_title ? 'border-red-500' : ''}
                                        />
                                        {errors.faq_title && <p className="text-sm text-red-500">{errors.faq_title}</p>}
                                    </div>
                                    <div className="space-y-3">
                                        <Label htmlFor="faq_description" required>{t('FAQ Description')} </Label>
                                        <Input
                                            id="faq_description"
                                            name="faq_description"
                                            value={formSettings.faq_description}
                                            onChange={handleInputChange}
                                            placeholder={t('Enter FAQ description')}
                                            disabled={!canEdit}
                                            className={errors.faq_description ? 'border-red-500' : ''}
                                        />
                                        {errors.faq_description && <p className="text-sm text-red-500">{errors.faq_description}</p>}
                                    </div>
                                </div>

                                <div className="space-y-4">
                                    <Label className="text-base font-medium">{t('FAQ Questions')}</Label>
                                    <Repeater
                                        fields={[
                                            {
                                                name: 'title',
                                                label: t('Question Title'),
                                                type: 'text',
                                                placeholder: t('Enter question title'),
                                                required: true
                                            },
                                            {
                                                name: 'description',
                                                label: t('Question Description'),
                                                type: 'textarea',
                                                placeholder: t('Enter question description'),
                                                required: true,
                                                rows: 4
                                            }
                                        ]}
                                        value={formSettings.faq_questions}
                                        onChange={handleQuestionChange}
                                        addButtonText={t('Add Question')}
                                        deleteTooltipText={t('Delete')}
                                        minItems={1}
                                        showDefault={true}
                                        className="space-y-4"
                                    />
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

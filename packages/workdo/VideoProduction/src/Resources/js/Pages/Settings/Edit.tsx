import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { useTranslation } from 'react-i18next';
import SettingsForm from './SettingsForm';

export default function Edit({ settings }: any) {
    const { t } = useTranslation();
    useFlashMessages();

    return <AuthenticatedLayout breadcrumbs={[{ label: t('Project') }, { label: t('Video Production') }, { label: t('Settings') }]} pageTitle={t('Video Production Settings')}>
        <Head title={t('Video Production Settings')} />
        <SettingsForm settings={settings} />
    </AuthenticatedLayout>;
}

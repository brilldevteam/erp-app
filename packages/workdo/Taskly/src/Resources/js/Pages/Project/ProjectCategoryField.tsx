import { useTranslation } from 'react-i18next';
import InputError from '@/components/ui/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ProjectCategory } from './types';

interface ProjectCategoryFieldProps {
    value: ProjectCategory;
    onChange: (value: ProjectCategory) => void;
    error?: string;
}

const categoryHelp: Record<ProjectCategory, string> = {
    general: 'Standard project with the essential planning fields.',
    production: 'Media project with Shooting Log, Deliverables, and production settings.',
    property: 'Property project with country, plot, property, and map information.',
};

export function ProjectCategoryField({ value, onChange, error }: ProjectCategoryFieldProps) {
    const { t } = useTranslation();

    return (
        <div className="space-y-1.5 rounded-lg border bg-gray-50/60 p-4">
            <Label htmlFor="project_category" required>{t('Project Category')}</Label>
            <Select value={value} onValueChange={(nextValue) => onChange(nextValue as ProjectCategory)}>
                <SelectTrigger id="project_category" className="bg-white">
                    <SelectValue placeholder={t('Select project category')} />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="general">{t('General')}</SelectItem>
                    <SelectItem value="production">{t('Production / Media')}</SelectItem>
                    <SelectItem value="property">{t('Property / Construction')}</SelectItem>
                </SelectContent>
            </Select>
            <p className="text-xs text-gray-500">{t(categoryHelp[value])}</p>
            <InputError message={error} />
        </div>
    );
}

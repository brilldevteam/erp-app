import { Head, Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { DocumentTemplate } from '@/types/document-template';
import { Copy, Edit, FileText, Plus, Star, Trash2 } from 'lucide-react';

interface Props {
    templates: {
        data: DocumentTemplate[];
    };
    filters: {
        type?: string;
    };
    auth: {
        user: {
            permissions?: string[];
        };
    };
    [key: string]: any;
}

export default function Index() {
    const { t } = useTranslation();
    const { templates, filters, auth } = usePage<Props>().props;
    const permissions = auth.user.permissions || [];
    const canManage = permissions.includes('manage-document-templates') || permissions.includes('edit-document-templates');
    const canCreate = permissions.includes('create-document-templates');
    const canDelete = permissions.includes('manage-document-templates') || permissions.includes('delete-document-templates');
    const canSetDefault = permissions.includes('set-default-document-templates');

    const changeType = (value: string) => {
        router.get(route('document-templates.index'), value === 'all' ? {} : { type: value }, { preserveState: true });
    };

    return (
        <AuthenticatedLayout breadcrumbs={[{ label: t('Templates') }]} pageTitle={t('Templates')}>
            <Head title={t('Templates')} />
            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">{t('Templates')}</h1>
                        <p className="text-sm text-muted-foreground">{t('Manage quotation and invoice document templates for this company.')}</p>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={route('document-templates.create')}><Plus className="mr-2 h-4 w-4" />{t('Create Template')}</Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle className="flex items-center gap-2"><FileText className="h-5 w-5" />{t('All Templates')}</CardTitle>
                        <div className="w-full sm:w-56">
                            <Select value={filters.type || 'all'} onValueChange={changeType}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All Types')}</SelectItem>
                                    <SelectItem value="quotation">{t('Quotation')}</SelectItem>
                                    <SelectItem value="invoice">{t('Invoice')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {templates.data.map((template) => (
                                <div key={template.id} className="rounded-xl border bg-card p-4 shadow-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="font-semibold">{template.name}</div>
                                            <div className="mt-1 flex flex-wrap gap-2">
                                                <Badge variant="outline" className="capitalize">{t(template.type)}</Badge>
                                                <Badge variant={template.status === 'active' ? 'default' : 'secondary'} className="capitalize">{t(template.status)}</Badge>
                                                {template.is_default && <Badge className="bg-amber-500 text-white">{t('Default')}</Badge>}
                                            </div>
                                        </div>
                                        <div className="h-8 w-8 rounded-full border" style={{ backgroundColor: template.primary_color }} />
                                    </div>
                                    <div className="mt-5 flex flex-wrap gap-2">
                                        {canManage && <Button size="sm" variant="outline" asChild><Link href={route('document-templates.edit', template.id)}><Edit className="mr-1 h-4 w-4" />{t('Edit')}</Link></Button>}
                                        {canCreate && <Button size="sm" variant="outline" onClick={() => router.post(route('document-templates.duplicate', template.id))}><Copy className="mr-1 h-4 w-4" />{t('Duplicate')}</Button>}
                                        {canSetDefault && !template.is_default && template.status === 'active' && <Button size="sm" variant="outline" onClick={() => router.post(route('document-templates.default', template.id))}><Star className="mr-1 h-4 w-4" />{t('Set Default')}</Button>}
                                        {canDelete && (
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                disabled={template.is_default}
                                                title={template.is_default ? t('Default templates cannot be deleted. Set another template as default first.') : undefined}
                                                onClick={() => !template.is_default && confirm(t('Delete this template?')) && router.delete(route('document-templates.destroy', template.id))}
                                            >
                                                <Trash2 className="mr-1 h-4 w-4" />{t('Delete')}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {templates.data.length === 0 && <div className="rounded-lg border border-dashed p-10 text-center text-muted-foreground">{t('No templates found.')}</div>}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

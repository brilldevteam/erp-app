import { useState, useEffect } from 'react';
import { Head, usePage, router, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ChevronDown } from 'lucide-react';
import { FileText, Calendar, User, DollarSign, Edit, X, Paperclip, MessageSquare, StickyNote, Eye, Copy, Save } from 'lucide-react';
import { formatCurrency, formatDate, formatDateTime } from '@/utils/helpers';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import AttachmentsTab from './components/AttachmentsTab';
import CommentsTab from './components/CommentsTab';
import NotesTab from './components/NotesTab';
import ConvertToContractModal from './components/ConvertToContractModal';
import DuplicateModal from './components/DuplicateModal';
import ContractTemplatePDFExport from './components/ContractTemplatePDFExport';


const getContractStatusColor = (status: any) => {
    const statusValue = status?.toString().toLowerCase();
    switch (statusValue) {
        case 'pending':
        case 'draft':
            return 'bg-yellow-100 text-yellow-800';
        case 'accepted':
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'declined':
            return 'bg-red-100 text-red-800';
        case 'closed':
        case 'archived':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-blue-100 text-blue-800';
    }
};

const getContractStatusText = (status: any, t: (key: string) => string) => {
    const statusValue = status?.toString().toLowerCase();
    switch (statusValue) {
        case 'pending':
            return t('Pending');
        case 'accepted':
            return t('Accepted');
        case 'declined':
            return t('Declined');
        case 'closed':
            return t('Closed');
        case 'draft':
            return t('Draft');
        case 'active':
            return t('Active');
        case 'archived':
            return t('Archived');
        default:
            return t('Pending');
    }
};
interface ContractTemplate {
    id: number;
    subject: string;
    template_number: string;
    contract_type: {
        id: number;
        name: string;
    };
    user: {
        id: number;
        name: string;
    };
    value: number;
    start_date: string;
    end_date: string;
    description: string;
    status: string;
    created_at: string;
    comments: any[];
    notes: any[];
    attachments: any[];
}

interface Props {
    template: ContractTemplate;
    contractTypes: Record<number, string>;
    users: Record<number, string>;
    auth: any;
}

export default function Show({ template, contractTypes, users, auth }: Props) {
    const { t } = useTranslation();

    useFlashMessages();

    const [activeTab, setActiveTab] = useState('details');
    const [deleteConfig, setDeleteConfig] = useState<{ type: string, id: number, route: string, message: string } | null>(null);
    const [showConvertModal, setShowConvertModal] = useState(false);
    const [showDuplicateModal, setShowDuplicateModal] = useState(false);
    const [isEditingDescription, setIsEditingDescription] = useState(false);

    const { data, setData, patch, processing } = useForm({
        subject: template.subject,
        user_id: template.user?.id || '',
        value: template.value || '',
        type_id: template.contract_type?.id || '',
        start_date: template.start_date || '',
        end_date: template.end_date || '',
        description: template.description || '',
        status: template.status || 'draft'
    });

    const handleDeleteConfirm = () => {
        if (deleteConfig) {
            router.delete(route(deleteConfig.route, deleteConfig.id), {
                onSuccess: () => router.reload()
            });
            setDeleteConfig(null);
        }
    };

    const handlePreview = () => {
        window.open(route('contract-templates.preview', template.id), '_blank');
    };



    const handleSaveDescription = () => {
        patch(route('contract-templates.update', template.id), {
            onSuccess: () => {
                setIsEditingDescription(false);
                router.reload();
            }
        });
    };

    const handleCancelEdit = () => {
        setData({
            subject: template.subject,
            user_id: template.user?.id || '',
            value: template.value || '',
            type_id: template.contract_type?.id || '',
            start_date: template.start_date || '',
            end_date: template.end_date || '',
            description: template.description || '',
            status: template.status || 'draft'
        });
        setIsEditingDescription(false);
    };

    // Permission checks for tabs
    const canViewAttachments = auth.user?.permissions?.includes('manage-any-contract-template-attachments') || auth.user?.permissions?.includes('manage-own-contract-template-attachments');
    const canViewComments = auth.user?.permissions?.includes('manage-any-contract-template-comments') || auth.user?.permissions?.includes('manage-own-contract-template-comments');
    const canViewNotes = auth.user?.permissions?.includes('manage-any-contract-template-notes') || auth.user?.permissions?.includes('manage-own-contract-template-notes');

    // Calculate visible tabs count for grid layout
    const visibleTabsCount = 1 + (canViewAttachments ? 1 : 0) + (canViewComments ? 1 : 0) + (canViewNotes ? 1 : 0);

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Contract'), url: route('contract.index') },
                { label: t('Contract Templates'), url: route('contract-templates.index') },
                { label: template.template_number }
            ]}
            pageTitle={t('Contract Templates Details')}
            pageActions={
                <TooltipProvider>
                    <div className="flex gap-2">
                        {auth.user?.permissions?.includes('preview-contract-templates') && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={handlePreview}
                                    >
                                        <FileText className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Preview')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}

                        <ContractTemplatePDFExport template={template} />

                        {auth.user?.permissions?.includes('duplicate-contract-templates') && (
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setShowDuplicateModal(true);
                                        }}
                                    >
                                        <Copy className="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>{t('Duplicate')}</p>
                                </TooltipContent>
                            </Tooltip>
                        )}


                    </div>
                </TooltipProvider>
            }
        >
            <Head title={`${t('Contract Templates Details')} - ${template.subject}`} />

            <div className="space-y-6">
                <Card className="border-0 shadow-lg bg-gradient-to-r from-white to-gray-50">
                    <CardHeader className="pb-8">
                        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                            <div className="flex-1 space-y-4">
                                <CardTitle className="flex items-center gap-4 text-2xl">
                                    <div className="p-3 bg-primary/15 rounded-xl shadow-sm">
                                        <FileText className="h-6 w-6 text-primary" />
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="font-bold text-gray-900">{template.subject}</span>
                                        <span className="text-sm font-normal text-muted-foreground mt-1">
                                            {template.template_number}
                                        </span>
                                    </div>
                                </CardTitle>

                                <div className="flex items-center gap-4">
                                    {auth.user?.permissions?.includes('change-status-contract-templates') && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="outline" size="sm" className="flex items-center gap-2 text-sm font-medium hover:bg-gray-50 border-gray-200">
                                                    <div className={`w-2.5 h-2.5 rounded-full ${template.status === 'draft' ? 'bg-yellow-500' :
                                                            template.status === 'active' ? 'bg-green-500' :
                                                                template.status === 'archived' ? 'bg-gray-500' :
                                                                    'bg-blue-500'
                                                        }`} />
                                                    {getContractStatusText(template.status, t)}
                                                    <ChevronDown className="h-3 w-3" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="start" className="w-40 bg-white border shadow-lg">
                                                <DropdownMenuItem
                                                    onClick={() => router.patch(route('contract-templates.update-status', template.id), { status: 'draft' })}
                                                    className="flex items-center gap-2 cursor-pointer hover:bg-yellow-50 focus:bg-yellow-50"
                                                >
                                                    <div className="w-2 h-2 rounded-full bg-yellow-600" />
                                                    {t('Draft')}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() => router.patch(route('contract-templates.update-status', template.id), { status: 'active' })}
                                                    className="flex items-center gap-2 cursor-pointer hover:bg-green-50 focus:bg-green-50"
                                                >
                                                    <div className="w-2 h-2 rounded-full bg-green-600" />
                                                    {t('Active')}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem
                                                    onClick={() => router.patch(route('contract-templates.update-status', template.id), { status: 'archived' })}
                                                    className="flex items-center gap-2 cursor-pointer hover:bg-gray-50 focus:bg-gray-50"
                                                >
                                                    <div className="w-2 h-2 rounded-full bg-gray-600" />
                                                    {t('Archived')}
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}

                                    {template.user?.name && (
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <User className="h-4 w-4" />
                                            <span>{template.user.name}</span>
                                        </div>
                                    )}

                                    {template.created_at && (
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <Calendar className="h-4 w-4" />
                                            <span>{formatDate(template.created_at)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="flex flex-col items-end space-y-3">
                                <div className="text-right">
                                    <p className="text-sm text-muted-foreground mb-1">{t('Template Value')}</p>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {template.value ? formatCurrency(template.value) : (
                                            <span className="text-lg text-muted-foreground font-normal">{t('Not Set')}</span>
                                        )}
                                    </p>
                                </div>

                                {template.contract_type?.name && (
                                    <div className="bg-primary/10 px-3 py-1.5 rounded-full">
                                        <span className="text-sm font-medium text-primary">{template.contract_type.name}</span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className={`grid w-full grid-cols-${visibleTabsCount}`}>
                        <TabsTrigger value="details" className="flex items-center gap-2">
                            <FileText className="h-4 w-4" />
                            {t('Details')}
                        </TabsTrigger>
                        {canViewAttachments && (
                            <TabsTrigger value="attachments" className="flex items-center gap-2">
                                <Paperclip className="h-4 w-4" />
                                {t('Attachments')} ({template.attachments?.length || 0})
                            </TabsTrigger>
                        )}
                        {canViewComments && (
                            <TabsTrigger value="comments" className="flex items-center gap-2">
                                <MessageSquare className="h-4 w-4" />
                                {t('Comments')} ({template.comments?.length || 0})
                            </TabsTrigger>
                        )}
                        {canViewNotes && (
                            <TabsTrigger value="notes" className="flex items-center gap-2">
                                <StickyNote className="h-4 w-4" />
                                {t('Notes')} ({template.notes?.length || 0})
                            </TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="details">
                        <Card className="border border-gray-200/60 shadow-xl bg-white backdrop-blur-sm rounded-2xl overflow-hidden relative">
                            <div className="absolute inset-0 bg-gradient-to-br from-blue-50/30 via-white to-purple-50/20 pointer-events-none"></div>
                            <div className="relative z-10">
                                <CardHeader>
                                    <CardTitle>{t('Template Information')}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <FileText className="h-4 w-4" />
                                                {t('Template Number')}
                                            </div>
                                            <p className="font-medium">{template.template_number || t('Not Generated')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <User className="h-4 w-4" />
                                                {t('Assigned To')}
                                            </div>
                                            <p className="font-medium">{template.user?.name || t('Not Assigned')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <FileText className="h-4 w-4" />
                                                {t('Template Type')}
                                            </div>
                                            <p className="font-medium">{template.contract_type?.name || t('Not Set')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <DollarSign className="h-4 w-4" />
                                                {t('Template Value')}
                                            </div>
                                            <p className="font-medium text-lg">{template.value ? formatCurrency(template.value) : t('Not Set')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Calendar className="h-4 w-4" />
                                                {t('Start Date')}
                                            </div>
                                            <p className="font-medium">{template.start_date ? formatDate(template.start_date) : t('Not Set')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Calendar className="h-4 w-4" />
                                                {t('End Date')}
                                            </div>
                                            <p className="font-medium">{template.end_date ? formatDate(template.end_date) : t('Not Set')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Calendar className="h-4 w-4" />
                                                {t('Duration')}
                                            </div>
                                            <p className="font-medium">
                                                {template.start_date && template.end_date ?
                                                    `${Math.ceil((new Date(template.end_date).getTime() - new Date(template.start_date).getTime()) / (1000 * 60 * 60 * 24))} ${t('days')}`
                                                    : t('Not Calculated')
                                                }
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Calendar className="h-4 w-4" />
                                                {t('Created Date')}
                                            </div>
                                            <p className="font-medium">{template.created_at ? formatDateTime(template.created_at) : t('Not Available')}</p>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                                {t('Status')}
                                            </div>
                                            <span className={`px-3 py-1 rounded-full text-sm font-medium ${getContractStatusColor(template.status)}`}>
                                                {getContractStatusText(template.status, t)}
                                            </span>
                                        </div>

                                        <div className="mt-6 pt-6 col-span-full">
                                            <div className="flex items-center justify-between mb-3">
                                                <h3 className="text-sm font-medium text-muted-foreground flex items-center gap-2">
                                                    <FileText className="h-4 w-4" />
                                                    {t('Description')}
                                                </h3>
                                                {auth.user?.permissions?.includes('edit-contract-templates') && (
                                                    <div className="flex gap-2">
                                                        {isEditingDescription ? (
                                                            <>
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button size="sm" onClick={handleSaveDescription} disabled={processing}>
                                                                            <Save className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        <p>{t('Save')}</p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Button size="sm" variant="outline" onClick={handleCancelEdit}>
                                                                            <X className="h-4 w-4" />
                                                                        </Button>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        <p>{t('Cancel')}</p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            </>
                                                        ) : (
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button size="sm" variant="outline" onClick={() => setIsEditingDescription(true)}>
                                                                        <Edit className="h-4 w-4" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    <p>{t('Edit')}</p>
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                            {isEditingDescription ? (
                                                <RichTextEditor
                                                    key={`editor-${template.id}-${isEditingDescription}`}
                                                    content={data.description}
                                                    onChange={(value) => setData('description', value)}
                                                    placeholder={t('Enter description...')}
                                                />
                                            ) : (
                                                <div className="bg-gray-50 rounded-lg p-4 min-h-[100px]">
                                                    {template.description ? (
                                                        <div className="text-sm text-gray-700 prose prose-sm max-w-none" dangerouslySetInnerHTML={{ __html: template.description }} />
                                                    ) : (
                                                        <p className="text-sm text-gray-500 italic">{t('No description provided')}</p>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </div>
                        </Card>
                    </TabsContent>

                    {canViewAttachments && (
                        <TabsContent value="attachments">
                            <AttachmentsTab template={template} setDeleteConfig={setDeleteConfig} />
                        </TabsContent>
                    )}

                    {canViewComments && (
                        <TabsContent value="comments">
                            <CommentsTab template={template} setDeleteConfig={setDeleteConfig} />
                        </TabsContent>
                    )}

                    {canViewNotes && (
                        <TabsContent value="notes">
                            <NotesTab template={template} setDeleteConfig={setDeleteConfig} />
                        </TabsContent>
                    )}
                </Tabs>
            </div>

            <ConfirmationDialog
                open={!!deleteConfig}
                onOpenChange={() => setDeleteConfig(null)}
                title={t(`Delete ${deleteConfig?.type || ''}`)}
                message={deleteConfig?.message || ''}
                confirmText={t('Delete')}
                onConfirm={handleDeleteConfirm}
                variant="destructive"
            />

            <ConvertToContractModal
                template={template}
                contractTypes={contractTypes}
                users={users}
                open={showConvertModal}
                onClose={() => setShowConvertModal(false)}
            />

            <DuplicateModal
                template={template}
                contractTypes={contractTypes}
                users={users}
                open={showDuplicateModal}
                onClose={() => setShowDuplicateModal(false)}
            />
        </AuthenticatedLayout>
    );
}
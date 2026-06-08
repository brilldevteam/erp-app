import React, { useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Printer } from 'lucide-react';
import { PageProps } from '@/types';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import ContractTemplatePDFExport from './components/ContractTemplatePDFExport';

interface Props extends PageProps {
    template: any;
}

export default function Preview({ auth, template }: Props) {
    const { t } = useTranslation();
    const printRef = useRef<HTMLDivElement>(null);

    const handlePrint = () => {
        window.print();
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('Contract'), url: route('contract.index') },
                { label: t('Contract Templates'), url: route('contract-templates.index') },
                { label: template.template_number, url: route('contract-templates.show', template.id) },
                { label: t('Preview') }
            ]}
            pageTitle={t('Contract Template Preview - ') + template.template_number}
            pageActions={
                <TooltipProvider>
                    <div className="flex gap-2 print:hidden">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="outline" size="sm" onClick={handlePrint}>
                                    <Printer className="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>{t('Print')}</p>
                            </TooltipContent>
                        </Tooltip>

                        <ContractTemplatePDFExport template={template} />
                    </div>
                </TooltipProvider>
            }
        >
            <Head title={`${t('Template Preview')} - ${template.subject}`} />

            <div className="space-y-6">
                <Card ref={printRef} className="max-w-4xl mx-auto bg-white">
                    <CardHeader className="text-center border-b print:border-b-2">
                        <CardTitle className="text-3xl font-bold">{template.subject}</CardTitle>
                        <p className="text-lg text-muted-foreground">Template #{template.template_number}</p>
                    </CardHeader>

                    <CardContent className="p-8 space-y-8">
                        <div className="grid grid-cols-2 gap-8">
                            <div>
                                <h3 className="text-lg font-semibold mb-4">{t('Template Information')}</h3>
                                <div className="space-y-3">
                                    <div>
                                        <span className="font-medium">{t('Subject')}:</span>
                                        <p className="mt-1">{template.subject}</p>
                                    </div>
                                    <div>
                                        <span className="font-medium">{t('Template Number')}:</span>
                                        <p className="mt-1">{template.template_number}</p>
                                    </div>
                                    <div>
                                        <span className="font-medium">{t('Contract Type')}:</span>
                                        <p className="mt-1">{template.contract_type?.name || '-'}</p>
                                    </div>
                                    <div>
                                        <span className="font-medium">{t('Status')}:</span>
                                        <p className="mt-1">{t(template.status.charAt(0).toUpperCase() + template.status.slice(1))}</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-4">{t('Contract Details')}</h3>
                                <div className="space-y-3">
                                    {template.user && (
                                        <div>
                                            <span className="font-medium">{t('Assigned To')}:</span>
                                            <p className="mt-1">{template.user.name}</p>
                                        </div>
                                    )}
                                    {template.value && (
                                        <div>
                                            <span className="font-medium">{t('Template Value')}:</span>
                                            <p className="mt-1">${template.value.toLocaleString()}</p>
                                        </div>
                                    )}
                                    {template.start_date && (
                                        <div>
                                            <span className="font-medium">{t('Start Date')}:</span>
                                            <p className="mt-1">{new Date(template.start_date).toLocaleDateString()}</p>
                                        </div>
                                    )}
                                    {template.end_date && (
                                        <div>
                                            <span className="font-medium">{t('End Date')}:</span>
                                            <p className="mt-1">{new Date(template.end_date).toLocaleDateString()}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {template.description && (
                            <div>
                                <h3 className="text-lg font-semibold mb-4">{t('Description')}</h3>
                                <div className="prose max-w-none">
                                    <div dangerouslySetInnerHTML={{ __html: template.description }} />
                                </div>
                            </div>
                        )}



                        <div className="text-center text-sm text-muted-foreground border-t pt-4">
                            <p>{t('Generated on')} {new Date().toLocaleDateString()}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <style jsx>{`
                @media print {
                    .print\\:hidden {
                        display: none !important;
                    }
                    .print\\:border-b-2 {
                        border-bottom-width: 2px !important;
                    }
                    body * {
                        visibility: hidden;
                    }
                    .max-w-4xl, .max-w-4xl * {
                        visibility: visible;
                    }
                    .max-w-4xl {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100% !important;
                        max-width: none !important;
                        margin: 0 !important;
                    }
                }
            `}</style>
        </AuthenticatedLayout>
    );
}
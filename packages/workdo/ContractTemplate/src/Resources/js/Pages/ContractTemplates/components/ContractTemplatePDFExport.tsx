import { Button } from "@/components/ui/button";
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { formatCurrency, formatDate, formatDateTime } from '@/utils/helpers';

interface ContractTemplate {
    id: number;
    subject: string;
    template_number: string;
    contract_type?: {
        name: string;
    };
    user?: {
        name: string;
    };
    value?: number;
    start_date?: string;
    end_date?: string;
    description?: string;
    status: string;
    created_at: string;
}

interface ContractTemplatePDFExportProps {
    template: ContractTemplate;
    variant?: "outline" | "default";
    size?: "sm" | "default";
}

export default function ContractTemplatePDFExport({ template, variant = "outline", size = "sm" }: ContractTemplatePDFExportProps) {
    const { t } = useTranslation();

    const handleDownloadPDF = () => {
        const element = document.createElement('div');
        element.innerHTML = `
            <div style="padding: 40px; font-family: Arial, sans-serif;">
                <div style="border-bottom: 2px solid #e5e7eb; padding-bottom: 24px; margin-bottom: 32px; text-align: center;">
                    <h1 style="font-size: 24px; font-weight: bold; color: #111827; margin-bottom: 8px;">${template.subject}</h1>
                    <p style="color: #6b7280;">Template #${template.template_number}</p>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 32px;">
                    <div>
                        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px;">${t('Template Information')}</h3>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Subject')}</label>
                            <p style="font-weight: 500; color: #111827;">${template.subject}</p>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Template Number')}</label>
                            <p style="font-weight: 500; color: #111827;">${template.template_number}</p>
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Contract Type')}</label>
                            <p style="font-weight: 500; color: #111827;">${template.contract_type?.name || '-'}</p>
                        </div>
                        <div>
                            <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Status')}</label>
                            <p style="font-weight: 500; color: #111827;">${t(template.status.charAt(0).toUpperCase() + template.status.slice(1))}</p>
                        </div>
                    </div>
                    <div>
                        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px;">${t('Template Details')}</h3>
                        ${template.user ? `
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Assigned To')}</label>
                                <p style="font-weight: 500; color: #111827;">${template.user.name}</p>
                            </div>
                        ` : ''}
                        ${template.value ? `
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Template Value')}</label>
                                <p style="font-size: 18px; font-weight: 600; color: #111827;">${formatCurrency(template.value)}</p>
                            </div>
                        ` : ''}
                        ${template.start_date ? `
                            <div style="margin-bottom: 12px;">
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('Start Date')}</label>
                                <p style="font-weight: 500; color: #111827;">${formatDate(template.start_date)}</p>
                            </div>
                        ` : ''}
                        ${template.end_date ? `
                            <div>
                                <label style="font-size: 14px; font-weight: 500; color: #6b7280;">${t('End Date')}</label>
                                <p style="font-weight: 500; color: #111827;">${formatDate(template.end_date)}</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
                ${template.description ? `
                    <div style="margin-bottom: 32px;">
                        <h3 style="font-size: 18px; font-weight: 600; color: #111827; margin-bottom: 16px;">${t('Description')}</h3>
                        <div style="border-left: 4px solid #d1d5db; padding-left: 16px;">
                            <div style="color: #374151; line-height: 1.6;">${template.description}</div>
                        </div>
                    </div>
                ` : ''}
                <div style="text-align: center; font-size: 12px; color: #9ca3af; padding-top: 32px; margin-top: 32px; border-top: 1px solid #e5e7eb;">
                    <p>${t('Generated on')} ${new Date().toLocaleDateString()}</p>
                </div>
            </div>
        `;
        
        const opt = {
            margin: 0.5,
            filename: `contract-template-${template.template_number}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        
        import('html2pdf.js').then(html2pdf => {
            html2pdf.default().set(opt).from(element).save();
        });
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant={variant}
                    size={size}
                    onClick={handleDownloadPDF}
                >
                    <Download className="h-4 w-4" />
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                <p>{t('Download PDF')}</p>
            </TooltipContent>
        </Tooltip>
    );
}
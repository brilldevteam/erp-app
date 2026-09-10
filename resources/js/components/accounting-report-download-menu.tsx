import { ChevronDown, Download, FileSpreadsheet, Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';

interface AccountingReportDownloadMenuProps {
    onPdf: () => void;
    onExcel: () => void;
}

export function AccountingReportDownloadMenu({ onPdf, onExcel }: AccountingReportDownloadMenuProps) {
    const { t } = useTranslation();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="min-w-[118px] gap-2">
                    <Download className="h-4 w-4" />
                    {t('Download')}
                    <ChevronDown className="ml-auto h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-[190px]">
                <DropdownMenuItem onSelect={onPdf}>
                    <Printer className="h-4 w-4" />
                    {t('Download as PDF')}
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={onExcel}>
                    <FileSpreadsheet className="h-4 w-4" />
                    {t('Download as Excel')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

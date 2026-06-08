import { Button } from '@/components/ui/button';
import { Replace } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import DuplicateModal from '../Pages/ContractTemplates/components/DuplicateModal';

export const contractActionBtn = (contract?: any) => {
    const { t } = useTranslation();
    const { auth, contractTypes, contracttypes } = usePage().props as any;
    const [convertModalOpen, setConvertModalOpen] = useState(false);
    
    if (auth.user?.permissions?.includes('convert-contract-to-template')) {
        return [{
            id: 'convert-to-template',
            order: 1,
            component: (
                <>
                    <Tooltip delayDuration={0}>
                        <TooltipTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setConvertModalOpen(true)}
                                className="h-8 w-8 p-0 text-blue-600 hover:text-blue-700"
                            >
                                <Replace className="h-4 w-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>{t('Convert to Template')}</p>
                        </TooltipContent>
                    </Tooltip>
                    <DuplicateModal
                        template={contract}
                        contractTypes={contracttypes || contractTypes || {}}
                        actionType="convertToTemplate"
                        open={convertModalOpen}
                        onClose={() => setConvertModalOpen(false)}
                    />
                </>
            )
        }];
    }
    else {
        return [];
    }
};
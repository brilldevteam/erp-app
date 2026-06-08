import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { FileText, User, Calendar, DollarSign, MessageSquare, CheckCircle, XCircle, Clock } from 'lucide-react';
import { PettyCashRequest } from './types';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatDateTime, formatCurrency } from '@/utils/helpers';

interface ViewProps {
    pettycashrequest: PettyCashRequest;
}

export default function View({ pettycashrequest }: ViewProps) {
    const { t } = useTranslation();
    const statusOptions: any = {
        "0": t('Pending'),
        "1": t('Approved'),
        "2": t('Rejected')
    };

    return (
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <FileText className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Petty Cash Request Details')}</DialogTitle>
                        <p className="text-sm text-muted-foreground">{pettycashrequest.request_number}</p>
                    </div>
                </div>
            </DialogHeader>

            <div className="overflow-y-auto flex-1 p-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Basic Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('Request Information')}</h3>

                        <div className="flex items-center gap-3">
                            <User className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Requested By')}</p>
                                <p className="font-medium">{pettycashrequest.user?.name || '-'}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <FileText className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Category')}</p>
                                <p className="font-medium">{pettycashrequest.category?.name || '-'}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <DollarSign className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Requested Amount')}</p>
                                <p className="font-medium text-lg">{formatCurrency(pettycashrequest.requested_amount)}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Calendar className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Request Date')}</p>
                                <p className="font-medium">{formatDate(pettycashrequest.created_at)}</p>
                            </div>
                        </div>
                    </div>

                    {/* Approval/Rejection Information */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('Approval Information')}</h3>

                        {pettycashrequest.status === '1' && (
                            <>
                                <div className="flex items-center gap-3">
                                    <CheckCircle className="h-4 w-4 text-green-500" />
                                    <div>
                                        <p className="text-sm text-gray-500">{t('Approved By')}</p>
                                        <p className="font-medium">{pettycashrequest.approver?.name || '-'}</p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <Calendar className="h-4 w-4 text-green-500" />
                                    <div>
                                        <p className="text-sm text-gray-500">{t('Approved At')}</p>
                                        <p className="font-medium">{pettycashrequest.approved_at ? formatDateTime(pettycashrequest.approved_at) : '-'}</p>
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <DollarSign className="h-4 w-4 text-green-500" />
                                    <div>
                                        <p className="text-sm text-gray-500">{t('Approved Amount')}</p>
                                        <p className="font-medium text-lg text-green-600">{pettycashrequest.approved_amount ? formatCurrency(pettycashrequest.approved_amount) : '-'}</p>
                                    </div>
                                </div>
                            </>
                        )}

                        {pettycashrequest.status === '2' && (
                            <div className="flex items-start gap-3">
                                <XCircle className="h-4 w-4 text-red-500 mt-1" />
                                <div className="flex-1">
                                    <p className="text-sm text-gray-500">{t('Rejection Reason')}</p>
                                    <p className="font-medium text-red-600">{pettycashrequest.rejection_reason || '-'}</p>
                                </div>
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            {pettycashrequest.status === '0' && <Clock className="h-4 w-4 text-yellow-500" />}
                            {pettycashrequest.status === '1' && <CheckCircle className="h-4 w-4 text-green-500" />}
                            {pettycashrequest.status === '2' && <XCircle className="h-4 w-4 text-red-500" />}
                            <div>
                                <p className="text-sm text-gray-500 mb-2">{t('Status')}</p>
                                <p className={`font-medium ${
                                    pettycashrequest.status === '0' ? 'text-yellow-600' :
                                    pettycashrequest.status === '1' ? 'text-green-600' :
                                    pettycashrequest.status === '2' ? 'text-red-600' :
                                    'text-gray-600'
                                }`}>
                                    {statusOptions[pettycashrequest.status] || pettycashrequest.status}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Remarks Section */}
                {pettycashrequest.remarks && (
                    <div className="mt-6 pt-6 border-t">
                        <div className="flex items-start gap-3">
                            <MessageSquare className="h-4 w-4 text-gray-500 mt-1" />
                            <div className="flex-1">
                                <p className="text-sm text-gray-500 mb-2">{t('Remarks')}</p>
                                <p className="font-medium bg-gray-50 p-3 rounded-lg">{pettycashrequest.remarks}</p>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </DialogContent>
    );
}

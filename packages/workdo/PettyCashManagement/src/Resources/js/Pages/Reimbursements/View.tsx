import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { Receipt, CheckCircle, XCircle, User, Calendar, DollarSign } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { formatDate, formatTime, formatDateTime, formatCurrency, getImagePath } from '@/utils/helpers';
import { Reimbursement } from './types';

interface ViewProps {
    reimbursement: Reimbursement;
}

export default function View({ reimbursement }: ViewProps) {
    const { t } = useTranslation();

    const getStatusBadge = (status: string) => {
        const statusMap: any = {
            '0': { label: t('Pending'), variant: 'secondary' },
            '1': { label: t('Approved'), variant: 'default' },
            '2': { label: t('Rejected'), variant: 'destructive' },
        };
        const statusInfo = statusMap[status] || { label: t('Unknown'), variant: 'secondary' };
        return <Badge variant={statusInfo.variant as any}>{statusInfo.label}</Badge>;
    };

    return (
        <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <Receipt className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Reimbursement Details')}</DialogTitle>
                        <p className="text-sm text-muted-foreground">{reimbursement.reimbursement_number}</p>
                    </div>
                </div>
            </DialogHeader>

            <div className="p-6 space-y-6">
                {/* Basic Information */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">{t('Basic Information')}</h3>

                        <div className="flex items-center gap-3">
                            <User className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('User')}</p>
                                <p className="font-medium">{reimbursement.user?.name || '-'}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Receipt className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Category')}</p>
                                <p className="font-medium">{reimbursement.category?.name || '-'}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <DollarSign className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Amount')}</p>
                                <p className="font-medium text-lg">{reimbursement.amount ? formatCurrency(reimbursement.amount) : '-'}</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Calendar className="h-4 w-4 text-gray-500" />
                            <div>
                                <p className="text-sm text-gray-500">{t('Request Date')}</p>
                                <p className="font-medium">{reimbursement.created_at ? formatDate(reimbursement.created_at) : '-'}</p>
                            </div>
                        </div>

                        <div>
                            <p className="text-sm text-gray-500 mb-2">{t('Status')}</p>
                            <span className={`px-2 py-1 rounded-full text-sm ${
                                reimbursement.status === '0' ? 'bg-yellow-100 text-yellow-800' :
                                reimbursement.status === '1' ? 'bg-green-100 text-green-800' :
                                reimbursement.status === '2' ? 'bg-red-100 text-red-800' :
                                'bg-gray-100 text-gray-800'
                            }`}>
                                {(() => {
                                    const statusOptions: any = {
                                        "0": t('Pending'),
                                        "1": t('Approved'),
                                        "2": t('Rejected')
                                    };
                                    return statusOptions[reimbursement.status] || t('Unknown');
                                })()}
                            </span>
                        </div>
                    </div>

                    {/* Receipt Image */}
                    <div className="space-y-4">
                        <h3 className="text-lg font-semibold border-b pb-2">{t('Receipt')}</h3>
                        {reimbursement.receipt_path ? (
                            <div className="border rounded-lg p-4">
                                <img
                                    src={getImagePath(reimbursement.receipt_path)}
                                    alt="Receipt"
                                    className="w-full max-h-64 object-contain rounded"
                                />
                            </div>
                        ) : (
                            <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                <Receipt className="h-12 w-12 text-gray-400 mx-auto mb-2" />
                                <p className="text-gray-500">{t('No receipt uploaded')}</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Description */}
                <div className="space-y-4">
                    <h3 className="text-lg font-semibold border-b pb-2">{t('Description')}</h3>
                    <div className="bg-gray-50 rounded-lg p-4">
                        <p className="text-sm whitespace-pre-wrap">{reimbursement.description || t('No description provided')}</p>
                    </div>
                </div>

                {/* Approval Information */}
                <div className="space-y-4">
                    <h3 className="text-lg font-semibold border-b pb-2">{t('Approval Information')}</h3>

                    {reimbursement.status === '1' && (
                        <div className="space-y-4">
                            <div className="flex items-center gap-3">
                                <CheckCircle className="h-4 w-4 text-green-500" />
                                <div>
                                    <p className="text-sm text-gray-500">{t('Approved By')}</p>
                                    <p className="font-medium">{reimbursement.approver?.name || '-'}</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <CheckCircle className="h-4 w-4 text-green-500" />
                                <div>
                                    <p className="text-sm text-gray-500">{t('Approved At')}</p>
                                    <p className="font-medium">{reimbursement.approved_date ? formatDateTime(reimbursement.approved_date) : '-'}</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <CheckCircle className="h-4 w-4 text-green-500" />
                                <div>
                                    <p className="text-sm text-gray-500">{t('Approved Amount')}</p>
                                    <p className="font-medium text-lg text-green-600">{reimbursement.approved_amount ? formatCurrency(reimbursement.approved_amount) : '-'}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {reimbursement.status === '2' && (
                        <div className="flex items-start gap-3">
                            <XCircle className="h-4 w-4 text-red-500 mt-1" />
                            <div className="flex-1">
                                <p className="text-sm text-gray-500">{t('Rejection Reason')}</p>
                                <p className="font-medium text-red-600">{reimbursement.rejection_reason || '-'}</p>
                            </div>
                        </div>
                    )}

                    {reimbursement.status === '0' && (
                        <div className="text-center py-8">
                            <p className="text-gray-500">{t('Pending Approval')}</p>
                        </div>
                    )}
                </div>
            </div>
        </DialogContent>
    );
}

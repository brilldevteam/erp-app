import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { DollarSign } from 'lucide-react';
import { PettyCashExpense } from './types';
import { formatDate, formatCurrency } from '@/utils/helpers';

interface ViewProps {
    pettycashexpense: PettyCashExpense;
}

export default function View({ pettycashexpense }: ViewProps) {
    const { t } = useTranslation();

    return (
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <DollarSign className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Expense Details')}</DialogTitle>
                    </div>
                </div>
            </DialogHeader>

            <div className="overflow-y-auto flex-1 p-4 space-y-6">
                {/* Basic Information */}
                <div className="bg-gray-50 rounded-lg p-4">
                    <h3 className="text-lg font-semibold mb-4">{t('Basic Information')}</h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Petty Cash Number')}</label>
                            <p className="mt-1 text-sm text-gray-900">{pettycashexpense.petty_cash?.pettycash_number || '-'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Request/Reimbursement Number')}</label>
                            <p className="mt-1 text-sm text-gray-900">{pettycashexpense.request?.request_number || pettycashexpense.reimbursement?.reimbursement_number || '-'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('User')}</label>
                            <p className="mt-1 text-sm text-gray-900">{pettycashexpense.request?.user?.name || pettycashexpense.reimbursement?.user?.name || '-'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Category')}</label>
                            <p className="mt-1 text-sm text-gray-900">{pettycashexpense.request?.category?.name || pettycashexpense.reimbursement?.category?.name || '-'}</p>
                        </div>
                    </div>
                </div>

                {/* Expense Details */}
                <div className="bg-gray-50 rounded-lg p-4">
                    <h3 className="text-lg font-semibold mb-4">{t('Expense Details')}</h3>
                    <div className="grid grid-cols-3 gap-4">
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Type')}</label>
                            <p className="mt-1 text-sm text-gray-900">
                                {(() => {
                                    const options: any = {
                                        "reimbursement": t('Reimbursement'),
                                        "pettycash": t('Petty Cash')
                                    };
                                    return options[pettycashexpense.type] || '-';
                                })()}
                            </p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Amount')}</label>
                            <p className="mt-1 text-sm text-gray-900 font-semibold">{formatCurrency(pettycashexpense.amount) || '-'}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Status')}</label>
                            <p className="mt-1 text-sm text-gray-900">
                                {(() => {
                                    const statusMap: any = {
                                        "0": t('Pending'),
                                        "1": t('Approved'),
                                        "2": t('Rejected')
                                    };
                                    return statusMap[pettycashexpense.status] || '-';
                                })()}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Approval Information */}
                <div className="bg-gray-50 rounded-lg p-4">
                    <h3 className="text-lg font-semibold mb-4">{t('Approval Information')}</h3>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Approved At')}</label>
                                <p className="mt-1 text-sm text-gray-900">{pettycashexpense.approved_at ? formatDate(pettycashexpense.approved_at) : '-'}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Approved By')}</label>
                                <p className="mt-1 text-sm text-gray-900">{pettycashexpense.approver?.name || '-'}</p>
                            </div>
                        </div>
                        {pettycashexpense.remarks && (
                            <div>
                                <label className="text-sm font-medium text-gray-600">{t('Remarks')}</label>
                                <p className="mt-1 text-sm text-gray-900">{pettycashexpense.remarks}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </DialogContent>
    );
}

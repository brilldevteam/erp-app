import { DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { useTranslation } from 'react-i18next';
import { DollarSign, Eye } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { DataTable } from "@/components/ui/data-table";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip";
import { formatDate, formatCurrency } from '@/utils/helpers';
import { PettyCash, PettyCashExpense } from './types';
interface ViewProps {
    pettycash: PettyCash;
}



export default function View({ pettycash }: ViewProps) {
    const { t } = useTranslation();
    const expenses = pettycash.expenses || [];

    const expenseColumns = [
        {
            key: 'request_reimbursement_number',
            header: t('Request/Reimbursement Number'),
            sortable: false,
            render: (value: string, row: PettyCashExpense) => {
                return row.request?.request_number || row.reimbursement?.reimbursement_number || '-';
            }
        },
        {
            key: 'user',
            header: t('User'),
            sortable: false,
            render: (value: string, row: PettyCashExpense) => row.request?.user?.name || row.reimbursement?.user?.name || '-'
        },
        {
            key: 'category',
            header: t('Category'),
            sortable: false,
            render: (value: string, row: PettyCashExpense) => row.request?.category?.name || row.reimbursement?.category?.name || '-'
        },
        {
            key: 'type',
            header: t('Type'),
            sortable: false,
            render: (value: string) => {
                const options: any = {
                    "pettycash": t('Petty Cash'),
                    "reimbursement": t('Reimbursement')
                };
                return options[value] || value || '-';
            }
        },
        {
            key: 'amount',
            header: t('Amount'),
            sortable: false,
            render: (value: string) => formatCurrency(value) || '-'
        },
        {
            key: 'approved_at',
            header: t('Approved At'),
            sortable: false,
            render: (value: string) => value ? formatDate(value) : '-'
        },
        {
            key: 'approved_by',
            header: t('Approved By'),
            sortable: false,
            render: (value: string, row: PettyCashExpense) => row.approver?.name || '-'
        }
    ];

    return (
        <DialogContent className="max-w-6xl max-h-[90vh] overflow-y-auto">
            <DialogHeader className="pb-4 border-b">
                <div className="flex items-center gap-3">
                    <div className="p-2 bg-primary/10 rounded-lg">
                        <DollarSign className="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <DialogTitle className="text-xl font-semibold">{t('Petty Cash Expenses')}</DialogTitle>
                        <p className="text-sm text-muted-foreground">{pettycash.pettycash_number}</p>
                    </div>
                </div>
            </DialogHeader>

            <div className="p-6 space-y-6">
                {/* Petty Cash Summary */}
                <div className="bg-gray-50 rounded-lg p-4">
                    <h3 className="text-lg font-semibold mb-4">{t('Petty Cash Summary')}</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Date')}</label>
                            <p className="text-sm font-medium">{formatDate(pettycash.date)}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Total Balance')}</label>
                            <p className="text-sm font-medium">{formatCurrency(pettycash.total_balance)}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Total Expense')}</label>
                            <p className="text-sm font-medium text-red-600">{formatCurrency(pettycash.total_expense)}</p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-gray-600">{t('Closing Balance')}</label>
                            <p className="text-sm font-medium text-green-600">{formatCurrency(pettycash.closing_balance)}</p>
                        </div>
                    </div>
                </div>

                {/* Expenses Table */}
                <div>
                    <h3 className="text-lg font-semibold mb-4">{t('Expense Details')} ({expenses.length} {t('items')})</h3>
                    {expenses.length > 0 ? (
                        <div className="border rounded-lg">
                            <DataTable
                                data={expenses}
                                columns={expenseColumns}
                                className="rounded-lg"
                            />
                        </div>
                    ) : (
                        <div className="text-center py-8 bg-gray-50 rounded-lg">
                            <DollarSign className="h-12 w-12 text-gray-400 mx-auto mb-2" />
                            <p className="text-gray-500">{t('No expenses found for this petty cash')}</p>
                        </div>
                    )}
                </div>
            </div>
        </DialogContent>
    );
}

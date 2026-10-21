import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useFlashMessages } from '@/hooks/useFlashMessages';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from '@/components/ui/label';
import { FileText, Search } from "lucide-react";
import { AccountingReportDownloadMenu } from '@/components/accounting-report-download-menu';
import { DatePicker } from '@/components/ui/date-picker';
import { formatDate, formatCurrency } from '@/utils/helpers';
import NoRecordsFound from '@/components/no-records-found';
import { TrialBalanceData, formatAmount, formatBalance, groupBySection } from './trialBalanceRows';

interface TrialBalanceProps {
    trialBalance: TrialBalanceData;
    auth: {
        user: {
            permissions: string[];
        };
    };
}

export default function Index() {
    const { t } = useTranslation();
    const { trialBalance, auth } = usePage<TrialBalanceProps>().props;
    const urlParams = new URLSearchParams(window.location.search);

    const [fromDate, setFromDate] = useState(urlParams.get('from_date') || trialBalance.from_date);
    const [toDate, setToDate] = useState(urlParams.get('to_date') || trialBalance.to_date);

    useFlashMessages();

    const handleGenerate = () => {
        if (!fromDate || !toDate) return;
        router.get(route('double-entry.trial-balance.index'), {
            from_date: fromDate,
            to_date: toDate
        }, {
            preserveState: true,
            replace: true
        });
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                {label: t('Double Entry')},
                {label: t('Trial Balance')}
            ]}
            pageTitle={t('Trial Balance')}
        >
            <Head title={t('Trial Balance')} />

            <div className="max-w-7xl mx-auto space-y-6">
                <Card className="shadow-lg border-0 bg-gradient-to-r from-white to-gray-50">
                    <CardHeader className="pb-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="w-10 h-10 bg-blue-50 rounded-lg border flex items-center justify-center">
                                    <FileText className="w-5 h-5 text-blue-600" />
                                </div>
                                <div>
                                    <CardTitle className="text-xl">{t('Trial Balance')}</CardTitle>
                                    <p className="text-sm text-gray-600">
                                        {formatDate(trialBalance.from_date)} - {formatDate(trialBalance.to_date)}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-end gap-3">
                                <div>
                                    <Label className="text-xs">{t('From Date')}</Label>
                                    <DatePicker
                                        value={fromDate}
                                        onChange={(value) => setFromDate(value)}
                                        placeholder={t('Select from date')}
                                    />
                                </div>
                                <div>
                                    <Label className="text-xs">{t('To Date')}</Label>
                                    <DatePicker
                                        value={toDate}
                                        onChange={(value) => setToDate(value)}
                                        placeholder={t('Select to date')}
                                    />
                                </div>
                                <Button onClick={handleGenerate} disabled={!fromDate || !toDate} size="sm">
                                    <Search className="h-4 w-4 mr-2" />
                                    {t('Generate')}
                                </Button>
                                {auth.user?.permissions?.includes('print-trial-balance') && (
                                    <AccountingReportDownloadMenu
                                        onPdf={() => window.open(route('double-entry.trial-balance.print') + `?from_date=${fromDate}&to_date=${toDate}&download=pdf`, '_blank')}
                                        onExcel={() => { window.location.href = route('double-entry.trial-balance.excel') + `?from_date=${fromDate}&to_date=${toDate}`; }}
                                    />
                                )}
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="text-center p-6 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border border-green-200 shadow-sm hover:shadow-md transition-shadow">
                                <h4 className="font-semibold text-green-700 mb-2">{t('Total Debit')}</h4>
                                <p className="text-3xl font-bold text-green-900 tabular-nums">
                                    {formatCurrency(trialBalance.total_debit)}
                                </p>
                            </div>
                            <div className="text-center p-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border border-blue-200 shadow-sm hover:shadow-md transition-shadow">
                                <h4 className="font-semibold text-blue-700 mb-2">{t('Total Credit')}</h4>
                                <p className="text-3xl font-bold text-blue-900 tabular-nums">
                                    {formatCurrency(trialBalance.total_credit)}
                                </p>
                            </div>
                        </div>

                        {!trialBalance.is_balanced && (
                            <div className="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                                <p className="text-red-800 font-medium">
                                    {t('Warning: Trial balance is not balanced!')}
                                </p>
                            </div>
                        )}

                        {trialBalance.diagnostics && (!trialBalance.diagnostics.has_accounts || !trialBalance.diagnostics.has_period_journals) && (
                            <div className="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-900">
                                {!trialBalance.diagnostics.has_accounts
                                    ? t('No active chart of accounts found for this company.')
                                    : t('No posted journal entries found for the selected date range. Post transactions or run the accounting repair command to rebuild missing journal entries.')}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card className="shadow-lg border-0">
                    <CardContent className="p-8">
                        {trialBalance.accounts && trialBalance.accounts.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-sm">
                                    <thead>
                                        <tr className="border-b-2 border-gray-300 text-left font-bold">
                                            <th className="py-2 pr-4">{t('Account')}</th>
                                            <th className="py-2 pr-4">{t('Account Code')}</th>
                                            <th className="py-2 pl-4 text-right">{t('Opening Balance')}</th>
                                            <th className="py-2 pl-4 text-right">{t('Net Debit')}</th>
                                            <th className="py-2 pl-4 text-right">{t('Net Credit')}</th>
                                            <th className="py-2 pl-4 text-right">{t('Closing Balance')}</th>
                                        </tr>
                                    </thead>
                                    {groupBySection(trialBalance.accounts).map((group) => (
                                        <tbody key={group.section}>
                                            <tr>
                                                <td colSpan={6} className="pt-4 pb-1 font-semibold text-gray-900">{t(group.label)}</td>
                                            </tr>
                                            {group.accounts.map((account) => (
                                                <tr key={account.id ?? account.account_code} className="border-b border-gray-100">
                                                    <td className="py-1.5 pr-4 pl-3 font-medium">{account.account_name}</td>
                                                    <td className="py-1.5 pr-4 text-green-600">{account.account_code}</td>
                                                    <td className="py-1.5 pl-4 text-right tabular-nums whitespace-nowrap">{formatBalance(account.opening_balance)}</td>
                                                    <td className="py-1.5 pl-4 text-right tabular-nums whitespace-nowrap">{formatAmount(account.net_debit)}</td>
                                                    <td className="py-1.5 pl-4 text-right tabular-nums whitespace-nowrap">{formatAmount(account.net_credit)}</td>
                                                    <td className="py-1.5 pl-4 text-right font-semibold tabular-nums whitespace-nowrap">{formatBalance(account.closing_balance)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    ))}
                                    <tfoot>
                                        <tr className="border-t-2 border-gray-400 font-bold">
                                            <td colSpan={3} className="pt-3">{t('TOTAL')}</td>
                                            <td className="pt-3 pl-4 text-right tabular-nums whitespace-nowrap">{formatCurrency(trialBalance.totals.net_debit)}</td>
                                            <td className="pt-3 pl-4 text-right tabular-nums whitespace-nowrap">{formatCurrency(trialBalance.totals.net_credit)}</td>
                                            <td className="pt-3" />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        ) : (
                            <NoRecordsFound
                                icon={FileText}
                                title={t('No accounts found')}
                                description={t('No account transactions found for the selected date range.')}
                                className="h-auto py-12"
                            />
                        )}
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

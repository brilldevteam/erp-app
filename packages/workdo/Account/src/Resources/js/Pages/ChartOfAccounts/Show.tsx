import { useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/ui/date-picker';
import { AccountingReportDownloadMenu } from '@/components/accounting-report-download-menu';
import { CreditCard, TrendingUp, TrendingDown, Calendar, FileText, Hash, Building2, Search } from "lucide-react";
import { formatCurrency, formatDate } from '@/utils/helpers';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

interface ChartOfAccount {
    id: number;
    account_code: string;
    account_name: string;
    level: number;
    normal_balance: string;
    opening_balance: number;
    current_balance: number;
    is_active: boolean;
    description?: string;
    account_type?: { name: string };
    parent_account?: { account_name: string };
}

interface LedgerTransaction {
    id: number;
    date: string;
    journal_number: string;
    reference: string | null;
    description: string;
    debit: number;
    credit: number;
    /** Running balance, signed: positive = debit, negative = credit. */
    balance: number;
}

interface AccountLedger {
    from_date: string;
    to_date: string;
    opening_balance: number;
    transactions: LedgerTransaction[];
    total_debit: number;
    total_credit: number;
    closing_balance: number;
}

interface ShowProps {
    chartofaccount: ChartOfAccount;
    ledger: AccountLedger;
    balanceToday: number;
    today: string;
    auth: { user: { permissions?: string[] } };
}

/** A signed balance shown the Zoho way, e.g. "10,606.40 Cr". */
const formatBalance = (value: number) =>
    Math.abs(value) < 0.005 ? formatCurrency(0) : `${formatCurrency(Math.abs(value))} ${value > 0 ? 'Dr' : 'Cr'}`;

export default function Show() {
    const { t } = useTranslation();
    const { chartofaccount, ledger, balanceToday, today, auth } = usePage<ShowProps>().props;
    const [fromDate, setFromDate] = useState(ledger.from_date);
    const [toDate, setToDate] = useState(ledger.to_date);
    const canDownload = auth.user?.permissions?.includes('print-general-ledger');

    const generate = () => {
        if (!fromDate || !toDate || fromDate > toDate) return;
        router.get(route('account.chart-of-accounts.show', chartofaccount.id), { from_date: fromDate, to_date: toDate }, { preserveScroll: true });
    };

    const download = (format: 'pdf' | 'excel') => {
        const params = new URLSearchParams({ from_date: ledger.from_date, to_date: ledger.to_date, format });
        window.open(route('account.chart-of-accounts.transactions', chartofaccount.id) + '?' + params.toString(), '_blank', 'noopener,noreferrer');
    };

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                {label: t('Accounting'), url:route('account.index')},
                {label: t('Chart Of Accounts'), url: route('account.chart-of-accounts.index')},
                {label: t('View')}
            ]}
            pageTitle={t('View Chart Of Account')}
        >
            <Head title={t('View Chart Of Account')} />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Account Summary Cards */}
                <div className="lg:col-span-2">
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-blue-100 rounded-lg">
                                        <CreditCard className="h-6 w-6 text-blue-600" />
                                    </div>
                                    <div>
                                        <CardTitle className="text-lg font-medium">{chartofaccount.account_name}</CardTitle>
                                        <p className="text-sm text-gray-500 flex items-center mt-1">
                                            <Hash className="h-4 w-4 mr-1" />
                                            {chartofaccount.account_code}
                                        </p>
                                    </div>
                                </div>
                                <Badge variant={chartofaccount.is_active ? 'outline' : 'outline'} className={chartofaccount.is_active ? 'bg-green-100 text-green-800 border-green-200' : 'bg-red-100 text-red-800 border-red-200'}>
                                    {chartofaccount.is_active ? t('Active') : t('Inactive')}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <Building2 className="h-5 w-5 text-gray-600" />
                                    <div>
                                        <p className="font-semibold">{t('Account Type')}</p>
                                        <p className="text-sm font-medium text-gray-600">{chartofaccount.account_type?.name || '-'}</p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <FileText className="h-5 w-5 text-gray-600" />
                                    <div>
                                        <p className="font-semibold">{t('Parent Account')}</p>
                                        <p className="text-sm font-medium text-gray-600">{chartofaccount.parent_account?.account_name || t('None')}</p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <div className={`h-5 w-5 rounded-full flex items-center justify-center text-xs font-bold ${
                                        chartofaccount.normal_balance === 'debit' ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'
                                    }`}>
                                        {chartofaccount.normal_balance === 'debit' ? 'DR' : 'CR'}
                                    </div>
                                    <div>
                                        <p className="font-semibold">{t('Normal Balance')}</p>
                                        <p className="text-sm font-medium text-gray-600">{chartofaccount.normal_balance === 'debit' ? t('Debit') : t('Credit')}</p>
                                    </div>
                                </div>
                                <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                    <div className="h-5 w-5 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-xs font-bold">
                                        {chartofaccount.level}
                                    </div>
                                    <div>
                                        <p className="font-semibold">{t('Level')}</p>
                                        <p className="text-sm font-medium text-gray-600">{t('Level')} {chartofaccount.level}</p>
                                    </div>
                                </div>
                            </div>
                            {chartofaccount.description && (
                                <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                                    <p className="font-semibold text-blue-900 mb-1">{t('Description')}</p>
                                    <p className="text-sm text-blue-800">{chartofaccount.description}</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Balance Cards */}
                <div className="space-y-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{t('Opening Balance')}</p>
                                    <p className="text-2xl font-bold text-gray-900">{formatBalance(ledger.opening_balance)}</p>
                                    <p className="text-xs text-gray-500">{t('As on')} {formatDate(ledger.from_date)}</p>
                                </div>
                                <div className="p-3 bg-blue-100 rounded-full">
                                    <TrendingUp className="h-6 w-6 text-blue-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{t('Closing Balance')}</p>
                                    <p className="text-2xl font-bold text-green-600">{formatBalance(ledger.closing_balance)}</p>
                                    <p className="text-xs text-gray-500">{t('As on')} {formatDate(ledger.to_date)}</p>
                                </div>
                                <div className="p-3 rounded-full bg-green-100">
                                    {ledger.closing_balance >= 0 ?
                                        <TrendingUp className="h-6 w-6 text-green-600" /> :
                                        <TrendingDown className="h-6 w-6 text-red-600" />
                                    }
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600">{t('Balance Today')}</p>
                                    <p className="text-2xl font-bold text-blue-600">{formatBalance(balanceToday)}</p>
                                    <p className="text-xs text-gray-500">{t('As on')} {formatDate(today)}</p>
                                </div>
                                <div className="p-3 rounded-full bg-blue-100">
                                    {balanceToday >= 0 ?
                                        <TrendingUp className="h-6 w-6 text-blue-600" /> :
                                        <TrendingDown className="h-6 w-6 text-orange-600" />
                                    }
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Card className="mt-6">
                <CardHeader className="pb-3">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="flex items-center space-x-2">
                            <Calendar className="h-4 w-4 text-gray-600" />
                            <div>
                                <h3 className="text-lg font-medium">{t('Account Transactions')}</h3>
                                <p className="text-sm text-gray-500">{formatDate(ledger.from_date)} - {formatDate(ledger.to_date)}</p>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <Label className="text-xs">{t('From Date')}</Label>
                                <DatePicker value={fromDate} onChange={(value) => setFromDate(value)} placeholder={t('Select from date')} />
                            </div>
                            <div>
                                <Label className="text-xs">{t('To Date')}</Label>
                                <DatePicker value={toDate} onChange={(value) => setToDate(value)} placeholder={t('Select to date')} />
                            </div>
                            <Button onClick={generate} disabled={!fromDate || !toDate || fromDate > toDate} size="sm">
                                <Search className="h-4 w-4 mr-2" />
                                {t('Generate')}
                            </Button>
                            {canDownload && <AccountingReportDownloadMenu onPdf={() => download('pdf')} onExcel={() => download('excel')} />}
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-gray-50">
                                    <TableHead className="font-semibold">{t('Date')}</TableHead>
                                    <TableHead className="font-semibold">{t('Journal Number')}</TableHead>
                                    <TableHead className="font-semibold">{t('Reference')}</TableHead>
                                    <TableHead className="font-semibold">{t('Description')}</TableHead>
                                    <TableHead className="text-right font-semibold">{t('Debit')}</TableHead>
                                    <TableHead className="text-right font-semibold">{t('Credit')}</TableHead>
                                    <TableHead className="text-right font-semibold">{t('Balance')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow className="bg-blue-50/50 font-medium">
                                    <TableCell className="whitespace-nowrap">{t('As on')} {formatDate(ledger.from_date)}</TableCell>
                                    <TableCell colSpan={5}>{t('Opening Balance')}</TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">{formatBalance(ledger.opening_balance)}</TableCell>
                                </TableRow>
                                {ledger.transactions.map((item) => (
                                    <TableRow key={item.id} className="hover:bg-gray-50">
                                        <TableCell className="whitespace-nowrap">{formatDate(item.date)}</TableCell>
                                        <TableCell><span className="font-mono text-sm text-blue-700">{item.journal_number}</span></TableCell>
                                        <TableCell className="max-w-[12rem] truncate" title={item.reference ?? ''}>{item.reference || '-'}</TableCell>
                                        <TableCell className="max-w-xs truncate" title={item.description}>{item.description}</TableCell>
                                        <TableCell className="text-right tabular-nums">{item.debit > 0 ? formatCurrency(item.debit) : '-'}</TableCell>
                                        <TableCell className="text-right tabular-nums">{item.credit > 0 ? formatCurrency(item.credit) : '-'}</TableCell>
                                        <TableCell className="text-right whitespace-nowrap tabular-nums">{formatBalance(item.balance)}</TableCell>
                                    </TableRow>
                                ))}
                                {ledger.transactions.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="py-8 text-center text-gray-500">{t('No transactions in this period')}</TableCell>
                                    </TableRow>
                                )}
                                <TableRow className="font-semibold border-t-2">
                                    <TableCell colSpan={4}>{t('Total Debits and Credits')} ({formatDate(ledger.from_date)} - {formatDate(ledger.to_date)})</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatCurrency(ledger.total_debit)}</TableCell>
                                    <TableCell className="text-right tabular-nums">{formatCurrency(ledger.total_credit)}</TableCell>
                                    <TableCell />
                                </TableRow>
                                <TableRow className="bg-blue-50/50 font-semibold">
                                    <TableCell className="whitespace-nowrap">{t('As on')} {formatDate(ledger.to_date)}</TableCell>
                                    <TableCell colSpan={5}>{t('Closing Balance')}</TableCell>
                                    <TableCell className="text-right whitespace-nowrap tabular-nums">{formatBalance(ledger.closing_balance)}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </AuthenticatedLayout>
    );
}

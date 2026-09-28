import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DollarSign, TrendingUp, TrendingDown, Receipt, Landmark, ReceiptText, WalletCards, Banknote } from 'lucide-react';
import { formatCurrency } from '@/utils/helpers';

interface StaffProps {
    stats: {
        total_clients: number;
        total_vendors: number;
        monthly_revenue: number;
        monthly_expense: number;
        accounts_receivable: number;
        receivables_due: number;
        accounts_payable: number;
        payables_due: number;
        cash_balance: number;
        bank_balance: number;
    };
    recentActivities: Array<{
        type: string;
        title: string;
        amount: number;
        date: string;
    }>;
}

export default function StaffDashboard({ stats, recentActivities }: StaffProps) {
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout
            breadcrumbs={[{label: t('Account')}, {label: t('Dashboard')}]}
            pageTitle={t('Dashboard')}
            pageTitleClass="text-lg"
        >
            <Head title={t('Dashboard')} />

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <Card className="border-orange-200 bg-orange-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-orange-700">{t('Accounts Receivable')}</CardTitle>
                        <div className="rounded-full bg-orange-100 p-2 shadow-[inset_2px_2px_5px_rgba(194,65,12,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]"><ReceiptText className="h-8 w-8 text-orange-600" /></div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-orange-700">{formatCurrency(stats.accounts_receivable || 0)}</div>
                        <p className="text-xs text-orange-600 mt-1">{formatCurrency(stats.receivables_due || 0)} {t('overdue')}</p>
                    </CardContent>
                </Card>

                <Card className="border-blue-200 bg-blue-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-blue-700">{t('Accounts Payable')}</CardTitle>
                        <div className="rounded-full bg-blue-100 p-2 shadow-[inset_2px_2px_5px_rgba(37,99,235,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]"><Landmark className="h-8 w-8 text-blue-600" /></div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-blue-700">{formatCurrency(stats.accounts_payable || 0)}</div>
                        <p className="text-xs text-blue-600 mt-1">{formatCurrency(stats.payables_due || 0)} {t('overdue')}</p>
                    </CardContent>
                </Card>

                <Card className="border-emerald-200 bg-emerald-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-emerald-700">{t('Cash Balance')}</CardTitle>
                        <div className="rounded-full bg-emerald-100 p-2 shadow-[inset_2px_2px_5px_rgba(5,150,105,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]"><Banknote className="h-8 w-8 text-emerald-600" /></div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-emerald-700">{formatCurrency(stats.cash_balance || 0)}</div>
                        <p className="text-xs text-emerald-600 mt-1">{t('Cash and petty cash')}</p>
                    </CardContent>
                </Card>

                <Card className="border-rose-200 bg-rose-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium text-rose-700">{t('Bank Balance')}</CardTitle>
                        <div className="rounded-full bg-rose-100 p-2 shadow-[inset_2px_2px_5px_rgba(225,29,72,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]"><WalletCards className="h-8 w-8 text-rose-600" /></div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-2xl font-bold text-rose-700">{formatCurrency(stats.bank_balance || 0)}</div>
                        <p className="text-xs text-rose-600 mt-1">{t('Active checking and savings')}</p>
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card className="border-gray-200 bg-white shadow-[7px_7px_16px_rgba(15,23,42,0.10),-7px_-7px_16px_rgba(255,255,255,0.95)]">
                    <CardHeader>
                        <CardTitle className="text-base">{t('Monthly Summary')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-green-100 rounded-full">
                                        <TrendingUp className="h-4 w-4 text-green-600" />
                                    </div>
                                    <div>
                                        <p className="font-medium">{t('Revenue')}</p>
                                        <p className="text-sm text-muted-foreground">{t('Current month')}</p>
                                    </div>
                                </div>
                                <div className="text-lg font-bold text-green-600">
                                    {formatCurrency(stats.monthly_revenue)}
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-red-100 rounded-full">
                                        <TrendingDown className="h-4 w-4 text-red-600" />
                                    </div>
                                    <div>
                                        <p className="font-medium">{t('Expense')}</p>
                                        <p className="text-sm text-muted-foreground">{t('Current month')}</p>
                                    </div>
                                </div>
                                <div className="text-lg font-bold text-red-600">
                                    {formatCurrency(stats.monthly_expense)}
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                                <div className="flex items-center space-x-3">
                                    <div className="p-2 bg-blue-100 rounded-full">
                                        <DollarSign className="h-4 w-4 text-blue-600" />
                                    </div>
                                    <div>
                                        <p className="font-medium">{t('Net Profit')}</p>
                                        <p className="text-sm text-muted-foreground">{t('Current month')}</p>
                                    </div>
                                </div>
                                <div className={`text-lg font-bold ${(stats.monthly_revenue - stats.monthly_expense) >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                    {formatCurrency(stats.monthly_revenue - stats.monthly_expense)}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-gray-200 bg-white shadow-[7px_7px_16px_rgba(15,23,42,0.10),-7px_-7px_16px_rgba(255,255,255,0.95)]">
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-base">{t('Recent Activities')}</CardTitle>
                        <Receipt className="h-5 w-5 text-gray-500" />
                    </CardHeader>
                    <CardContent>
                        <div className="max-h-96 overflow-y-auto space-y-3">
                            {recentActivities.length > 0 ? (
                                recentActivities.map((activity, index) => (
                                    <div key={index} className="flex justify-between items-center p-3 rounded-lg border">
                                        <div className="flex items-center space-x-3">
                                            <div className={`p-2 rounded-full ${activity.type === 'Revenue' ? 'bg-green-100' : 'bg-red-100'}`}>
                                                {activity.type === 'Revenue' ?
                                                    <TrendingUp className="h-4 w-4 text-green-600" /> :
                                                    <TrendingDown className="h-4 w-4 text-red-600" />
                                                }
                                            </div>
                                            <div>
                                                <p className="font-medium text-sm">{activity.title}</p>
                                                <p className="text-xs text-gray-600">{activity.type}</p>
                                                <p className="text-xs text-gray-500">{new Date(activity.date).toLocaleDateString()}</p>
                                            </div>
                                        </div>
                                        <div className={`font-bold ${activity.type === 'Revenue' ? 'text-green-600' : 'text-red-600'}`}>
                                            {activity.type === 'Revenue' ? '+' : '-'}{formatCurrency(activity.amount)}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-8 text-gray-500">
                                    <DollarSign className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                    <p>{t('No recent activities')}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

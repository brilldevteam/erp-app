import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { LineChart } from '@/components/charts';
import { Landmark, ReceiptText, WalletCards, Banknote } from 'lucide-react';
import { formatDate,formatCurrency} from '@/utils/helpers';

interface AccountProps {
    message: string;
    stats?: {
        total_items: number;
        active_items: number;
        inactive_items: number;
        total_clients: number;
        total_vendors: number;
        total_customer_payment: number;
        total_vendor_payment: number;
        accounts_receivable: number;
        receivables_due: number;
        accounts_payable: number;
        payables_due: number;
        cash_balance: number;
        bank_balance: number;
    };
    monthlyVendorPayments?: Array<{ month: string; vendor_payments: number }>;
    monthlyCustomerPayments?: Array<{ month: string; customer_payments: number }>;
    recentRevenues?: Array<{ id: number; title: string; description: string; amount: number; date: string }>;
    recentExpenses?: Array<{ id: number; title: string; description: string; amount: number; date: string }>;
    recent_items?: Array<{
        id: number;
        name: string;
        created_at: string;
    }>;
}

export default function AccountIndex({ message, stats, monthlyVendorPayments, monthlyCustomerPayments, recentRevenues, recentExpenses, recent_items }: AccountProps) {
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout
            breadcrumbs={[{label: t('Account')}]}
            pageTitle={t('Account Dashboard')}
            pageTitleClass="text-lg"
        >
            <Head title={t('Account')} />

            {stats && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <Card className="border-orange-200 bg-orange-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-orange-700">{t('Accounts Receivable')}</CardTitle>
                                <div className="rounded-full bg-orange-100 p-2 shadow-[inset_2px_2px_5px_rgba(194,65,12,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]">
                                    <ReceiptText className="h-8 w-8 text-orange-600" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-orange-700">{formatCurrency(stats.accounts_receivable || 0)}</div>
                                <p className="text-xs text-orange-600 mt-1">{formatCurrency(stats.receivables_due || 0)} {t('overdue')}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-blue-200 bg-blue-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-blue-700">{t('Accounts Payable')}</CardTitle>
                                <div className="rounded-full bg-blue-100 p-2 shadow-[inset_2px_2px_5px_rgba(37,99,235,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]">
                                    <Landmark className="h-8 w-8 text-blue-600" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-blue-700">{formatCurrency(stats.accounts_payable || 0)}</div>
                                <p className="text-xs text-blue-600 mt-1">{formatCurrency(stats.payables_due || 0)} {t('overdue')}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-emerald-200 bg-emerald-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-emerald-700">{t('Cash Balance')}</CardTitle>
                                <div className="rounded-full bg-emerald-100 p-2 shadow-[inset_2px_2px_5px_rgba(5,150,105,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]">
                                    <Banknote className="h-8 w-8 text-emerald-600" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-emerald-700">{formatCurrency(stats.cash_balance || 0)}</div>
                                <p className="text-xs text-emerald-600 mt-1">{t('Cash and petty cash')}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-rose-200 bg-rose-50 shadow-[6px_6px_14px_rgba(15,23,42,0.10),-6px_-6px_14px_rgba(255,255,255,0.90)]">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-rose-700">{t('Bank Balance')}</CardTitle>
                                <div className="rounded-full bg-rose-100 p-2 shadow-[inset_2px_2px_5px_rgba(225,29,72,0.16),inset_-2px_-2px_5px_rgba(255,255,255,0.80)]">
                                    <WalletCards className="h-8 w-8 text-rose-600" />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-rose-700">{formatCurrency(stats.bank_balance || 0)}</div>
                                <p className="text-xs text-rose-600 mt-1">{t('Active checking and savings')}</p>
                            </CardContent>
                        </Card>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div className="space-y-6">
                    <Card className="h-96 border-gray-200 bg-white shadow-[7px_7px_16px_rgba(15,23,42,0.10),-7px_-7px_16px_rgba(255,255,255,0.95)]">
                        <CardHeader>
                            <CardTitle className="text-base">{t('Monthly Customer Payments')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <LineChart
                                data={monthlyCustomerPayments}
                                height={300}
                                showTooltip={true}
                                showGrid={true}
                                lines={[
                                    { dataKey: 'customer_payments', color: '#10b981', name: 'Customer Payments' }
                                ]}
                                xAxisKey="month"
                                showLegend={true}
                            />
                        </CardContent>
                    </Card>

                    {recentRevenues && (
                        <Card className="border-gray-200 bg-white shadow-[7px_7px_16px_rgba(15,23,42,0.10),-7px_-7px_16px_rgba(255,255,255,0.95)]">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base">{t('Recent Revenue')}</CardTitle>
                                <span className="text-xs text-gray-500">{t('Last 5 days')}</span>
                            </CardHeader>
                            <CardContent>
                                <div className="max-h-80 overflow-y-auto space-y-3">
                                    {recentRevenues.slice(0, 5).map((revenue) => (
                                        <div key={revenue.id} className="flex justify-between items-center p-3 rounded-lg border">
                                            <div>
                                                <p className="font-medium text-sm">{revenue.title}</p>
                                                <p className="text-xs text-gray-600">{revenue.description}</p>
                                                <p className="text-xs text-gray-500">{formatDate(revenue.date)}</p>
                                            </div>
                                            <div className="text-green-600 font-bold">{formatCurrency(revenue.amount)}</div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                <div className="space-y-6">
                    <Card className="h-96 border-gray-200 bg-white shadow-[7px_7px_16px_rgba(15,23,42,0.10),-7px_-7px_16px_rgba(255,255,255,0.95)]">
                        <CardHeader>
                            <CardTitle className="text-base">{t('Monthly Vendor Payments')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <LineChart
                                data={monthlyVendorPayments}
                                height={300}
                                showTooltip={true}
                                showGrid={true}
                                lines={[
                                    { dataKey: 'vendor_payments', color: '#ef4444', name: 'Vendor Payments' }
                                ]}
                                xAxisKey="month"
                                showLegend={true}
                            />
                        </CardContent>
                    </Card>

                    {recentExpenses && (
                        <Card className="border-gray-200 bg-white shadow-[7px_7px_16px_rgba(15,23,42,0.10),-7px_-7px_16px_rgba(255,255,255,0.95)]">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-base">{t('Recent Expenses')}</CardTitle>
                                <span className="text-xs text-gray-500">{t('Last 5 days')}</span>
                            </CardHeader>
                            <CardContent>
                                <div className="max-h-80 overflow-y-auto space-y-3">
                                    {recentExpenses.slice(0, 5).map((expense) => (
                                        <div key={expense.id} className="flex justify-between items-center p-3 rounded-lg border">
                                            <div>
                                                <p className="font-medium text-sm">{expense.title}</p>
                                                <p className="text-xs text-gray-600">{expense.description}</p>
                                                <p className="text-xs text-gray-500">{formatDate(expense.date)}</p>
                                            </div>
                                            <div className="text-red-600 font-bold">{formatCurrency(expense.amount)}</div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>


        </AuthenticatedLayout>
    );
}

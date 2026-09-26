import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { BarChart3, Building2, UserCheck, ArrowUpCircle, ArrowDownCircle } from 'lucide-react';
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

const chartTooltipStyle = {
    border: '1px solid #e2e8f0',
    borderRadius: '12px',
    boxShadow: '0 12px 30px -16px rgba(15, 23, 42, 0.35)',
    fontSize: '12px',
    padding: '10px 12px',
};

function EmptyChart({ message }: { message: string }) {
    return <div className="flex h-[245px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 text-center dark:border-slate-800 dark:bg-slate-900/40">
        <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-900"><BarChart3 className="h-5 w-5" /></div>
        <p className="text-sm font-semibold text-slate-700 dark:text-slate-200">{message}</p>
        <p className="mt-1 text-xs text-slate-400">Payment activity will appear here automatically.</p>
    </div>;
}

function CustomerPaymentChart({ data = [] }: { data?: Array<{ month: string; customer_payments: number }> }) {
    const hasData = data.some(item => Number(item.customer_payments) > 0);
    if (!hasData) return <EmptyChart message="No customer payments in this period" />;

    return <ResponsiveContainer width="100%" height={245}>
        <AreaChart data={data} margin={{ top: 12, right: 8, left: -12, bottom: 0 }}>
            <defs><linearGradient id="customerPaymentFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stopColor="#0f9f8f" stopOpacity={0.3} /><stop offset="95%" stopColor="#0f9f8f" stopOpacity={0.02} /></linearGradient></defs>
            <CartesianGrid vertical={false} stroke="#e8eef1" strokeDasharray="3 5" />
            <XAxis dataKey="month" axisLine={false} tickLine={false} tick={{ fill: '#64748b', fontSize: 11 }} tickMargin={10} />
            <YAxis axisLine={false} tickLine={false} tick={{ fill: '#94a3b8', fontSize: 11 }} tickFormatter={(value) => Number(value).toLocaleString()} width={58} />
            <Tooltip contentStyle={chartTooltipStyle} formatter={(value: number) => [formatCurrency(value), 'Customer payments']} cursor={{ stroke: '#cbd5e1', strokeDasharray: '4 4' }} />
            <Area type="monotone" dataKey="customer_payments" stroke="#0f9f8f" strokeWidth={2.5} fill="url(#customerPaymentFill)" activeDot={{ r: 5, fill: '#0f9f8f', stroke: '#fff', strokeWidth: 3 }} />
        </AreaChart>
    </ResponsiveContainer>;
}

function VendorPaymentChart({ data = [] }: { data?: Array<{ month: string; vendor_payments: number }> }) {
    const hasData = data.some(item => Number(item.vendor_payments) > 0);
    if (!hasData) return <EmptyChart message="No vendor payments in this period" />;

    return <ResponsiveContainer width="100%" height={245}>
        <BarChart data={data} margin={{ top: 12, right: 8, left: -12, bottom: 0 }} barCategoryGap="35%">
            <CartesianGrid vertical={false} stroke="#e8eef1" strokeDasharray="3 5" />
            <XAxis dataKey="month" axisLine={false} tickLine={false} tick={{ fill: '#64748b', fontSize: 11 }} tickMargin={10} />
            <YAxis axisLine={false} tickLine={false} tick={{ fill: '#94a3b8', fontSize: 11 }} tickFormatter={(value) => Number(value).toLocaleString()} width={58} />
            <Tooltip contentStyle={chartTooltipStyle} formatter={(value: number) => [formatCurrency(value), 'Vendor payments']} cursor={{ fill: '#f1f5f9' }} />
            <Bar dataKey="vendor_payments" fill="#334155" radius={[7, 7, 2, 2]} maxBarSize={46} />
        </BarChart>
    </ResponsiveContainer>;
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
                <div className="mb-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Card className="overflow-hidden border-slate-200/80 bg-white">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                                <CardTitle className="text-xs font-semibold uppercase tracking-[0.06em] text-slate-500">{t('Total Clients')}</CardTitle>
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><UserCheck className="h-[18px] w-[18px]" /></span>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-semibold tracking-[-0.04em] text-slate-950">{stats.total_clients || 0}</div>
                                <p className="mt-1 text-xs text-slate-400">{t('Active clients')}</p>
                            </CardContent>
                        </Card>
                        <Card className="overflow-hidden border-slate-200/80 bg-white">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                                <CardTitle className="text-xs font-semibold uppercase tracking-[0.06em] text-slate-500">{t('Total Vendors')}</CardTitle>
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><Building2 className="h-[18px] w-[18px]" /></span>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-semibold tracking-[-0.04em] text-slate-950">{stats.total_vendors || 0}</div>
                                <p className="mt-1 text-xs text-slate-400">{t('Active vendors')}</p>
                            </CardContent>
                        </Card>
                        <Card className="overflow-hidden border-slate-200/80 bg-white">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                                <CardTitle className="text-xs font-semibold uppercase tracking-[0.06em] text-slate-500">{t('Total Customer Payment')}</CardTitle>
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 text-teal-700"><ArrowDownCircle className="h-[18px] w-[18px]" /></span>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-semibold tracking-[-0.04em] text-slate-950">{formatCurrency(stats.total_customer_payment || 0)}</div>
                                <p className="mt-1 text-xs text-slate-400">{t('Received payments')}</p>
                            </CardContent>
                        </Card>
                        <Card className="overflow-hidden border-slate-200/80 bg-white">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                                <CardTitle className="text-xs font-semibold uppercase tracking-[0.06em] text-slate-500">{t('Total Vendor Payment')}</CardTitle>
                                <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600"><ArrowUpCircle className="h-[18px] w-[18px]" /></span>
                            </CardHeader>
                            <CardContent>
                                <div className="text-3xl font-semibold tracking-[-0.04em] text-slate-950">{formatCurrency(stats.total_vendor_payment || 0)}</div>
                                <p className="mt-1 text-xs text-slate-400">{t('Paid to vendors')}</p>
                            </CardContent>
                        </Card>
                </div>
            )}

            <div className="mb-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div className="space-y-5">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between space-y-0 pb-2">
                            <div><CardTitle className="text-base">{t('Customer payment trend')}</CardTitle><p className="mt-1 text-xs text-slate-400">{t('Received during the last 6 months')}</p></div>
                            <span className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">6 months</span>
                        </CardHeader>
                        <CardContent>
                            <CustomerPaymentChart data={monthlyCustomerPayments} />
                        </CardContent>
                    </Card>

                    {recentRevenues && (
                        <Card>
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

                <div className="space-y-5">
                    <Card>
                        <CardHeader className="flex-row items-start justify-between space-y-0 pb-2">
                            <div><CardTitle className="text-base">{t('Vendor payment activity')}</CardTitle><p className="mt-1 text-xs text-slate-400">{t('Paid during the last 6 months')}</p></div>
                            <span className="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">6 months</span>
                        </CardHeader>
                        <CardContent>
                            <VendorPaymentChart data={monthlyVendorPayments} />
                        </CardContent>
                    </Card>

                    {recentExpenses && (
                        <Card>
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

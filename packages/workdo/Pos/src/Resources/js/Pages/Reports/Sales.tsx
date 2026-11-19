import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AuthenticatedLayout from "@/layouts/authenticated-layout";
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { formatDate, formatCurrency } from '@/utils/helpers';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface SalesReportProps {
    salesData: {
        data: Array<{
            id: number;
            sale_number: string;
            total: number;
            pos_date: string;
            sale_date?: string;
            customer?: { name: string };
            warehouse?: { name: string };
            payment?: { amount: number; discount: number; discount_amount: number };
        }>;
        links?: Array<{ url: string | null; label: string; active: boolean }>;
        total?: number;
    };
    dailySales?: Array<{ date: string; sales: number; count: number }>;
    monthlySales?: Array<{ month: string; sales: number; count: number }>;
    warehouseSales?: Array<{ name: string; sales: number; count: number }>;
    summary?: { sales_count: number; gross: number; discount: number; net: number; average: number };
    warehouses?: Array<{ id: number; name: string }>;
    filters?: { date_from: string; date_to: string; warehouse_id: string };
}

export default function SalesReport({ salesData, dailySales, monthlySales, warehouseSales, summary, warehouses = [], filters }: SalesReportProps) {
    const { t } = useTranslation();
    const [dateFrom, setDateFrom] = useState(filters?.date_from || '');
    const [dateTo, setDateTo] = useState(filters?.date_to || '');
    const [warehouseId, setWarehouseId] = useState(filters?.warehouse_id || 'all');

    const applyFilters = () => {
        router.get(route('pos.reports.sales'), {
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            warehouse_id: warehouseId !== 'all' ? warehouseId : undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setWarehouseId('all');
        router.get(route('pos.reports.sales'));
    };

    const cards = [
        { label: t('Net Sales'), value: formatCurrency(summary?.net ?? 0) },
        { label: t('Number of Sales'), value: String(summary?.sales_count ?? 0) },
        { label: t('Discounts'), value: formatCurrency(summary?.discount ?? 0) },
        { label: t('Average Sale'), value: formatCurrency(summary?.average ?? 0) },
    ];

    return (
        <AuthenticatedLayout
            breadcrumbs={[
                { label: t('POS'), url: route('pos.index') },
                { label: t('Sales Report') }
            ]}
            pageTitle={t('Sales Report')}
        >
            <Head title={t('Sales Report')} />

            <Card className="mb-6">
                <CardContent className="pt-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">
                        <div className="space-y-2">
                            <Label htmlFor="date_from">{t('From')}</Label>
                            <Input id="date_from" type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="date_to">{t('To')}</Label>
                            <Input id="date_to" type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Warehouse')}</Label>
                            <Select value={warehouseId} onValueChange={setWarehouseId}>
                                <SelectTrigger>
                                    <SelectValue placeholder={t('All Warehouses')} />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">{t('All Warehouses')}</SelectItem>
                                    {warehouses.map((warehouse) => (
                                        <SelectItem key={warehouse.id} value={String(warehouse.id)}>{warehouse.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="flex gap-2">
                            <Button onClick={applyFilters}>{t('Apply')}</Button>
                            <Button variant="outline" onClick={clearFilters}>{t('Clear')}</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {cards.map((card) => (
                    <Card key={card.label}>
                        <CardContent className="pt-6">
                            <p className="text-sm text-muted-foreground">{card.label}</p>
                            <p className="mt-1 text-2xl font-semibold">{card.value}</p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Tabs defaultValue="daily" className="w-full">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="daily">{t('Daily Sales')}</TabsTrigger>
                        <TabsTrigger value="monthly">{t('Monthly Sales')}</TabsTrigger>
                        <TabsTrigger value="warehouse">{t('Warehouse Sales')}</TabsTrigger>
                        <TabsTrigger value="list">{t('Sales List')}</TabsTrigger>
                    </TabsList>

                <TabsContent value="daily" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Daily Sales Performance')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={400}>
                                <BarChart data={dailySales || []}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="date" />
                                    <YAxis />
                                    <Tooltip formatter={(value) => [formatCurrency(value), t('Sales')]} />
                                    <Bar dataKey="sales" fill="#8884d8" />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="monthly" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Monthly Sales Performance')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={400}>
                                <BarChart data={monthlySales || []}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" />
                                    <YAxis />
                                    <Tooltip formatter={(value) => [formatCurrency(value), t('Sales')]} />
                                    <Bar dataKey="sales" fill="#00C49F" />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="warehouse" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Warehouse Sales Comparison')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ResponsiveContainer width="100%" height={400}>
                                <BarChart data={warehouseSales || []}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="name" />
                                    <YAxis />
                                    <Tooltip formatter={(value) => [formatCurrency(value), t('Sales')]} />
                                    <Bar dataKey="sales" fill="#ff7300" />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="list" className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Sales List')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-muted-foreground">
                                            <th className="py-2 pr-4">{t('Sale')}</th>
                                            <th className="py-2 pr-4">{t('Date')}</th>
                                            <th className="py-2 pr-4">{t('Warehouse')}</th>
                                            <th className="py-2 pr-4">{t('Customer')}</th>
                                            <th className="py-2 pr-4 text-right">{t('Discount')}</th>
                                            <th className="py-2 text-right">{t('Net Total')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {salesData.data.length === 0 && (
                                            <tr><td colSpan={6} className="py-6 text-center text-muted-foreground">{t('No sales in this period')}</td></tr>
                                        )}
                                        {salesData.data.map((sale) => (
                                            <tr key={sale.id} className="border-b last:border-0">
                                                <td className="py-2 pr-4">{sale.sale_number}</td>
                                                <td className="py-2 pr-4">{formatDate(sale.sale_date || sale.pos_date)}</td>
                                                <td className="py-2 pr-4">{sale.warehouse?.name || '-'}</td>
                                                <td className="py-2 pr-4">{sale.customer?.name || t('Walk-in Customer')}</td>
                                                <td className="py-2 pr-4 text-right">{formatCurrency(sale.payment?.discount ?? 0)}</td>
                                                <td className="py-2 text-right font-medium">{formatCurrency(sale.total)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {salesData.links && salesData.links.length > 3 && (
                                <div className="mt-4 flex flex-wrap gap-1">
                                    {salesData.links.map((link, index) => (
                                        <Button
                                            key={index}
                                            size="sm"
                                            variant={link.active ? 'default' : 'outline'}
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </AuthenticatedLayout>
    );
}

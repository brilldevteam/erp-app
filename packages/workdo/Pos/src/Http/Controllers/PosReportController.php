<?php

namespace Workdo\Pos\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Pos\Models\PosSale;
use Workdo\Pos\Models\PosItem;
use Workdo\ProductService\Models\ProductServiceItem;
use App\Models\Warehouse;
use Carbon\Carbon;
use Workdo\Pos\Models\Pos;

class PosReportController extends Controller
{
    public function sales(Request $request)
    {
        if(Auth::user()->can('manage-pos-reports')){
            $creatorId = creatorId();

            $validated = $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'warehouse_id' => 'nullable|integer',
            ]);

            // Default period: the current month up to today.
            $dateTo = Carbon::parse($validated['date_to'] ?? now())->endOfDay();
            $dateFrom = Carbon::parse($validated['date_from'] ?? $dateTo->copy()->startOfMonth())->startOfDay();
            $warehouseId = $validated['warehouse_id'] ?? null;

            $warehouses = Warehouse::where('created_by', $creatorId)->select('id', 'name')->orderBy('name')->get();
            if ($warehouseId && !$warehouses->contains('id', $warehouseId)) {
                $warehouseId = null;
            }

            // Net sales (after discount) per sale, dated by the sale date.
            $netSales = fn () => Pos::query()
                ->join('pos_payments', 'pos_payments.pos_id', '=', 'pos.id')
                ->where('pos.created_by', $creatorId)
                ->when($warehouseId, fn ($q) => $q->where('pos.warehouse_id', $warehouseId));

            $inPeriod = fn () => $netSales()->whereBetween('pos.pos_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);

            $summaryRow = $inPeriod()
                ->selectRaw('COUNT(DISTINCT pos.id) as sales_count, COALESCE(SUM(pos_payments.amount),0) as gross, COALESCE(SUM(pos_payments.discount),0) as discount, COALESCE(SUM(pos_payments.discount_amount),0) as net')
                ->first();
            $summary = [
                'sales_count' => (int) $summaryRow->sales_count,
                'gross' => round((float) $summaryRow->gross, 2),
                'discount' => round((float) $summaryRow->discount, 2),
                'net' => round((float) $summaryRow->net, 2),
                'average' => $summaryRow->sales_count ? round((float) $summaryRow->net / $summaryRow->sales_count, 2) : 0,
            ];

            $salesData = Pos::where('pos.created_by', $creatorId)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->whereBetween('pos_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                ->with(['customer:id,name', 'warehouse:id,name', 'payment:pos_id,amount,discount,discount_amount'])
                ->orderByDesc('pos_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
            $salesData->getCollection()->transform(function ($sale) {
                $sale->total = (float) ($sale->payment->discount_amount ?? 0);
                $sale->sale_date = Carbon::parse($sale->pos_date ?? $sale->created_at)->toDateString();
                return $sale;
            });

            // Daily sales across the period (last 62 days of it at most).
            $dailyFrom = $dateFrom->copy()->max($dateTo->copy()->subDays(61)->startOfDay());
            $daily = $netSales()
                ->whereBetween('pos.pos_date', [$dailyFrom->toDateString(), $dateTo->toDateString()])
                ->selectRaw('DATE(pos.pos_date) as day, SUM(pos_payments.discount_amount) as sales, COUNT(DISTINCT pos.id) as count')
                ->groupBy('day')
                ->get()
                ->keyBy('day');
            $dailySales = [];
            for ($date = $dailyFrom->copy(); $date->lte($dateTo); $date->addDay()) {
                $row = $daily->get($date->toDateString());
                $dailySales[] = [
                    'date' => $date->format('M d'),
                    'sales' => round((float) ($row->sales ?? 0), 2),
                    'count' => (int) ($row->count ?? 0),
                ];
            }

            // Monthly sales for the 6 months ending with the period end.
            $monthsFrom = $dateTo->copy()->startOfMonth()->subMonths(5);
            $monthly = $netSales()
                ->whereBetween('pos.pos_date', [$monthsFrom->toDateString(), $dateTo->toDateString()])
                ->selectRaw("DATE_FORMAT(pos.pos_date, '%Y-%m') as ym, SUM(pos_payments.discount_amount) as sales, COUNT(DISTINCT pos.id) as count")
                ->groupBy('ym')
                ->get()
                ->keyBy('ym');
            $monthlySales = [];
            for ($i = 0; $i < 6; $i++) {
                $month = $monthsFrom->copy()->addMonths($i);
                $row = $monthly->get($month->format('Y-m'));
                $monthlySales[] = [
                    'month' => $month->format('M Y'),
                    'sales' => round((float) ($row->sales ?? 0), 2),
                    'count' => (int) ($row->count ?? 0),
                ];
            }

            // Sales per warehouse (cafe / outlet) in the period.
            $warehouseSales = $inPeriod()
                ->leftJoin('warehouses', 'warehouses.id', '=', 'pos.warehouse_id')
                ->selectRaw("COALESCE(warehouses.name, 'Unknown') as name, SUM(pos_payments.discount_amount) as sales, COUNT(DISTINCT pos.id) as count")
                ->groupBy('pos.warehouse_id', 'warehouses.name')
                ->orderByDesc('sales')
                ->get()
                ->map(fn ($row) => ['name' => $row->name, 'sales' => round((float) $row->sales, 2), 'count' => (int) $row->count])
                ->values();

            return Inertia::render('Pos/Reports/Sales', [
                'salesData' => $salesData,
                'dailySales' => $dailySales,
                'monthlySales' => $monthlySales,
                'warehouseSales' => $warehouseSales,
                'summary' => $summary,
                'warehouses' => $warehouses,
                'filters' => [
                    'date_from' => $dateFrom->toDateString(),
                    'date_to' => $dateTo->toDateString(),
                    'warehouse_id' => $warehouseId ? (string) $warehouseId : '',
                ],
            ]);
        }else{
            return redirect()->route('pos.index')->with('error', __('Permission denied'));
        }
    }

    public function products(Request $request)
    {
        if(Auth::user()->can('manage-pos-reports')){
            $creatorId = creatorId();
            
            // Product performance data
            $productData = PosItem::where('created_by', $creatorId)
                ->with('product')
                ->get()
                ->groupBy('product_id')
                ->map(function($items, $productId) {
                    $product = $items->first()->product;
                    return [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'total_quantity' => $items->sum('quantity'),
                        'total_revenue' => $items->sum('total_amount'),
                        'total_orders' => $items->pluck('pos_id')->unique()->count()
                    ];
                })
                ->sortByDesc('total_revenue')
                ->values();

            return Inertia::render('Pos/Reports/Products', [
                'productData' => $productData,
            ]);
        }else{
            return redirect()->route('pos.index')->with('error', __('Permission denied'));
        }
    }

    public function customers(Request $request)
    {
        if(Auth::user()->can('manage-pos-reports')){
            $creatorId = creatorId();
            
            // Customer analysis data
            $customerData = PosItem::where('created_by', $creatorId)
                ->with('sale.customer')
                ->get()
                ->groupBy('sale.customer_id')
                ->map(function($items, $customerId) {
                    $customer = $items->first()->sale->customer;
                    $totalSpent = $items->sum('total_amount');
                    $orderCount = $items->pluck('pos_id')->unique()->count();
                    return [
                        'customer_id' => $customerId,
                        'customer' => ['name' => $customer->name ?? 'Walk-in'],
                        'total_orders' => $orderCount,
                        'total_spent' => $totalSpent,
                        'avg_order_value' => $orderCount > 0 ? $totalSpent / $orderCount : 0,
                        'last_order_date' => $items->max('created_at')
                    ];
                })
                ->sortByDesc('total_spent')
                ->take(20)
                ->values();

            return Inertia::render('Pos/Reports/Customers', [
                'customerData' => $customerData,
            ]);
        }else{
            return redirect()->route('pos.index')->with('error', __('Permission denied'));
        }
    }


}
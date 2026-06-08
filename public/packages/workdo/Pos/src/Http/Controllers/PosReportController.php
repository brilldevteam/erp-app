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
            
            // Sales data for the report
            $salesData = Pos::where('created_by', $creatorId)
                ->with(['customer:id,name', 'warehouse:id,name', 'items'])
                ->latest()
                ->paginate(20);

            // Calculate total from pos_sale_items
            $salesWithTotals = $salesData->getCollection()->map(function($sale) {
                $sale->total = $sale->items->sum('total_amount');
                return $sale;
            });
            $salesData->setCollection($salesWithTotals);

            // Daily sales for last 7 days
            $dailySales = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $sales = PosItem::where('created_by', $creatorId)
                    ->whereDate('created_at', $date)
                    ->sum('total_amount');
                $count = Pos::where('created_by', $creatorId)
                    ->whereDate('created_at', $date)
                    ->count();
                $dailySales[] = [
                    'date' => $date->format('M d'),
                    'sales' => $sales,
                    'count' => $count
                ];
            }

            // Monthly sales for last 6 months
            $monthlySales = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $sales = PosItem::where('created_by', $creatorId)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('total_amount');
                $count = Pos::where('created_by', $creatorId)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count();
                $monthlySales[] = [
                    'month' => $date->format('M Y'),
                    'sales' => $sales,
                    'count' => $count
                ];
            }

            // Warehouse sales
            $warehouseSales = PosItem::where('created_by', $creatorId)
                ->with('sale.warehouse')
                ->get()
                ->groupBy('sale.warehouse_id')
                ->map(function($items, $warehouseId) {
                    $warehouse = $items->first()->sale->warehouse;
                    return [
                        'name' => $warehouse->name ?? 'Unknown',
                        'sales' => $items->sum('total_amount'),
                        'count' => $items->pluck('pos_id')->unique()->count()
                    ];
                })
                ->values();

            return Inertia::render('Pos/Reports/Sales', [
                'salesData' => $salesData,
                'dailySales' => $dailySales,
                'monthlySales' => $monthlySales,
                'warehouseSales' => $warehouseSales,
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
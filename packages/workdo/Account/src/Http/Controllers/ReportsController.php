<?php

namespace Workdo\Account\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Workdo\Account\Services\ReportService;

class ReportsController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        if(Auth::user()->can('manage-account-reports')){
            $currentYear = date('Y');
            $financialYear = [
                'year_start_date' => "$currentYear-01-01",
                'year_end_date' => "$currentYear-12-31",
            ];

            return Inertia::render('Account/Reports/Index', [
                'financialYear' => $financialYear,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function invoiceAging(Request $request)
    {
        $filters = $this->invoiceAgingFilters($request);

        $data = $this->reportService->getInvoiceAging($filters);
        return response()->json($data);
    }

    public function billAging(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
        ];

        $data = $this->reportService->getBillAging($filters);
        return response()->json($data);
    }

    public function taxSummary(Request $request)
    {
        $currentYear = date('Y');
        $filters = [
            'from_date' => $request->from_date ?: "$currentYear-01-01",
            'to_date' => $request->to_date ?: "$currentYear-12-31",
        ];

        $data = $this->reportService->getTaxSummary($filters);
        return response()->json($data);
    }

    public function customerBalance(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];

        $data = $this->reportService->getCustomerBalanceSummary($filters);
        return response()->json($data);
    }

    public function vendorBalance(Request $request)
    {
        $filters = [
            'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
            'show_zero_balances' => $request->show_zero_balances === 'true',
        ];

        $data = $this->reportService->getVendorBalanceSummary($filters);
        return response()->json($data);
    }

    public function printInvoiceAging(Request $request)
    {
        if(Auth::user()->can('print-invoice-aging')){
            $filters = $this->invoiceAgingFilters($request);
            $allowedColumns = ['account_code', 'customer', 'invoice_number', 'current', '1_30_days', '31_60_days', '61_90_days', 'over_90_days', 'total'];
            $columns = $request->filled('columns')
                ? array_values(array_intersect(explode(',', (string) $request->get('columns')), $allowedColumns))
                : $allowedColumns;
            if (!in_array('customer', $columns, true)) {
                array_unshift($columns, 'customer');
            }
            $data = $this->reportService->getInvoiceAging($filters);
            return Inertia::render('Account/Reports/Print/InvoiceAging', ['data' => $data, 'filters' => $filters, 'columns' => $columns]);
        }
        else
        {
             return back()->with('error', __('Permission denied'));
        }
    }

    private function invoiceAgingFilters(Request $request): array
    {
        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'customer_id' => ['nullable', 'integer'],
            'invoice_date_from' => ['nullable', 'date_format:Y-m-d'],
            'invoice_date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:invoice_date_from'],
            'aging_bucket' => ['nullable', 'in:current,1_30_days,31_60_days,61_90_days,over_90_days'],
        ]);

        return array_filter([
            'as_of_date' => $validated['as_of_date'] ?? date('Y-m-d'),
            'customer_id' => $validated['customer_id'] ?? null,
            'invoice_date_from' => $validated['invoice_date_from'] ?? null,
            'invoice_date_to' => $validated['invoice_date_to'] ?? null,
            'aging_bucket' => $validated['aging_bucket'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function printBillAging(Request $request)
    {
        if(Auth::user()->can('print-bill-aging')){
            $filters = ['as_of_date' => $request->as_of_date ?: date('Y-m-d')];
            $data = $this->reportService->getBillAging($filters);
            return Inertia::render('Account/Reports/Print/BillAging', ['data' => $data, 'filters' => $filters]);
        }
        else
        {
             return back()->with('error', __('Permission denied'));
        }
    }

    public function printTaxSummary(Request $request)
    {
        if(Auth::user()->can('print-tax-summary')){
             $currentYear = date('Y');
            $filters = [
                'from_date' => $request->from_date ?: "$currentYear-01-01",
                'to_date' => $request->to_date ?: "$currentYear-12-31",
            ];
            $data = $this->reportService->getTaxSummary($filters);
            return Inertia::render('Account/Reports/Print/TaxSummary', ['data' => $data, 'filters' => $filters]);
        }
        else
        {
                return back()->with('error', __('Permission denied'));
        }
    }

    public function printCustomerBalance(Request $request)
    {
        if(Auth::user()->can('print-customer-balance')){
            $filters = [
                'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
                'show_zero_balances' => $request->show_zero_balances === 'true',
                ];
            $data = $this->reportService->getCustomerBalanceSummary($filters);
            return Inertia::render('Account/Reports/Print/CustomerBalance', ['data' => $data, 'filters' => $filters]);
        }
        else{
                return back()->with('error', __('Permission denied'));
        }
    }

    public function printVendorBalance(Request $request)
    {
        if(Auth::user()->can('print-vendor-balance')){
            $filters = [
                'as_of_date' => $request->as_of_date ?: date('Y-m-d'),
                'show_zero_balances' => $request->show_zero_balances === 'true',
            ];
            $data = $this->reportService->getVendorBalanceSummary($filters);
            return Inertia::render('Account/Reports/Print/VendorBalance', ['data' => $data, 'filters' => $filters]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function customerDetail($customerId, Request $request)
    {
        if(Auth::user()->can('view-customer-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getCustomerDetail($customerId, $filters);

            if (!$data) {
                return back()->with('error', __('Customer not found'));
            }

            return Inertia::render('Account/Reports/CustomerDetail', [
                'customerData' => $data,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function vendorDetail($vendorId, Request $request)
    {
        if(Auth::user()->can('view-vendor-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getVendorDetail($vendorId, $filters);

            if (!$data) {
                return back()->with('error', __('Vendor not found'));
            }

            return Inertia::render('Account/Reports/VendorDetail', [
                'vendorData' => $data,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function printCustomerDetail($customerId, Request $request)
    {
        if(Auth::user()->can('print-customer-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getCustomerDetail($customerId, $filters);

            if (!$data) {
                return back()->with('error', __('Customer not found'));
            }

            return Inertia::render('Account/Reports/Print/CustomerDetail', [
                'data' => $data,
                'filters' => $filters,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function printVendorDetail($vendorId, Request $request)
    {
        if(Auth::user()->can('print-vendor-detail-report')){
            $filters = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ];

            $data = $this->reportService->getVendorDetail($vendorId, $filters);

            if (!$data) {
                return back()->with('error', __('Vendor not found'));
            }

            return Inertia::render('Account/Reports/Print/VendorDetail', [
                'data' => $data,
                'filters' => $filters,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }
}

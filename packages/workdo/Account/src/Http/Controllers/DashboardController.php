<?php

namespace Workdo\Account\Http\Controllers;

use App\Models\SalesInvoiceReturn;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Services\ReportService;
use Workdo\DoubleEntry\Services\TrialBalanceService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct(
        private ReportService $reportService,
        private TrialBalanceService $trialBalanceService,
    ) {}

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-account-dashboard')){
            $user = Auth::user();
            $userType = $user->type;

            switch ($userType) {
                case 'company':
                    return $this->companyDashboard();
                case 'vendor':
                    return $this->vendorDashboard();
                case 'client':
                    return $this->clientDashboard();
                case 'staff':
                default:
                    return $this->staffDashboard();
            }
        }
        return back()->with('error', __('Permission denied'));
    }

    private function companyDashboard()
    {
        $creatorId = creatorId();
        $financialStats = $this->financialStats($creatorId);

        $totalClients = Customer::where('created_by', $creatorId)->count();
        $totalVendors = Vendor::where('created_by', $creatorId)->count();
        $totalRevenue = Revenue::where('created_by', $creatorId)->sum('amount');
        $totalExpense = Expense::where('created_by', $creatorId)->sum('amount');
        $totalCustomerPayments = CustomerPayment::whereHas('customer', function($q) use ($creatorId) {
            $q->where('created_by', $creatorId);
        })->sum('payment_amount');
        $totalVendorPayments = VendorPayment::whereHas('vendor', function($q) use ($creatorId) {
            $q->where('created_by', $creatorId);
        })->sum('payment_amount');
        
        $netProfit = $totalRevenue - $totalExpense;

        // Recent income from every source: revenue entries, posted sales invoices and POS sales.
        $recentRevenues = Revenue::where('created_by', $creatorId)
            ->latest('revenue_date')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'id' => 'revenue-' . $item->id,
                'title' => $item->revenue_number,
                'description' => $item->description ?? __('Revenue transaction'),
                'amount' => (float) $item->amount,
                'date' => $item->revenue_date ?? $item->created_at,
                'sort' => $item->created_at,
            ]);

        $recentRevenues = $recentRevenues->concat(
            SalesInvoice::where('created_by', $creatorId)
                ->whereNotIn('status', ['draft', 'cancelled'])
                ->with('customer:id,name')
                ->latest('invoice_date')
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn ($item) => [
                    'id' => 'invoice-' . $item->id,
                    'title' => $item->invoice_number,
                    'description' => __('Sales invoice') . ($item->customer ? ' - ' . $item->customer->name : ''),
                    'amount' => round((float) $item->total_amount * ((float) $item->exchange_rate > 0 ? (float) $item->exchange_rate : 1), 2),
                    'date' => $item->invoice_date,
                    'sort' => $item->created_at,
                ])
        );

        if (Module_is_active('Pos') && class_exists(\Workdo\Pos\Models\Pos::class)) {
            $recentRevenues = $recentRevenues->concat(
                \Workdo\Pos\Models\Pos::where('created_by', $creatorId)
                    ->with(['warehouse:id,name', 'payment:pos_id,discount_amount'])
                    ->latest('pos_date')
                    ->latest('id')
                    ->limit(5)
                    ->get()
                    ->map(fn ($item) => [
                        'id' => 'pos-' . $item->id,
                        'title' => $item->sale_number,
                        'description' => __('POS sale') . ($item->warehouse ? ' - ' . $item->warehouse->name : ''),
                        'amount' => (float) ($item->payment->discount_amount ?? 0),
                        'date' => $item->pos_date,
                        'sort' => $item->created_at,
                    ])
            );
        }

        $recentRevenues = $recentRevenues
            ->sortByDesc(fn ($item) => [Carbon::parse($item['date'])->toDateString(), Carbon::parse($item['sort'])->timestamp])
            ->take(5)
            ->map(fn ($item) => collect($item)->except('sort')->all())
            ->values();

        $recentExpenses = Expense::where('created_by', $creatorId)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->expense_number,
                    'description' => $item->description ?? 'Expense transaction',
                    'amount' => $item->amount,
                    'date' => $item->created_at
                ];
            });

        $monthlyCustomerPayments = [];
        $monthlyVendorPayments = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            
            $customerPayments = CustomerPayment::whereHas('customer', function($q) use ($creatorId) {
                $q->where('created_by', $creatorId);
            })
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->sum('payment_amount');
            
            $vendorPayments = VendorPayment::whereHas('vendor', function($q) use ($creatorId) {
                $q->where('created_by', $creatorId);
            })
            ->whereMonth('created_at', $date->month)
            ->whereYear('created_at', $date->year)
            ->sum('payment_amount');
            
            $monthlyCustomerPayments[] = [
                'month' => $monthName,
                'customer_payments' => $customerPayments
            ];
            
            $monthlyVendorPayments[] = [
                'month' => $monthName,
                'vendor_payments' => $vendorPayments
            ];
        }

        return Inertia::render('Account/Dashboard/CompanyDashboard', [
            'stats' => [
                'total_clients' => $totalClients,
                'total_vendors' => $totalVendors,
                'total_revenue' => $totalRevenue,
                'total_expense' => $totalExpense,
                'total_customer_payment' => $totalCustomerPayments,
                'total_vendor_payment' => $totalVendorPayments,
                'net_profit' => $netProfit,
                ...$financialStats,
            ],
            'monthlyCustomerPayments' => $monthlyCustomerPayments,
            'monthlyVendorPayments' => $monthlyVendorPayments,
            'recentRevenues' => $recentRevenues,
            'recentExpenses' => $recentExpenses
        ]);
    }

    private function vendorDashboard()
    {
        $user = Auth::user();

        $totalPayments = VendorPayment::where('vendor_id', $user->id)->sum('payment_amount');
        $totalExpenses = PurchaseInvoice::where('vendor_id', $user->id)
            ->where('created_by', $user->created_by)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total_amount');
        $paymentCount = VendorPayment::where('vendor_id', $user->id)->count();

        $monthlyPayments = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');

            $monthPayments = VendorPayment::where('vendor_id', $user->id)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('payment_amount');

            $monthlyPayments[] = [
                'month' => $monthName,
                'payments' => $monthPayments
            ];
        }

        // Dynamic return purchase invoices
        $recentReturnInvoices = collect();
        if (class_exists('\\App\Models\\PurchaseReturn')) {
            $recentReturnInvoices = \App\Models\PurchaseReturn::where('vendor_id', $user->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($return) {
                    return [
                        'id' => $return->id,
                        'invoice_number' => $return->return_number ?? 'PUR-RET-' . $return->id,
                        'amount' => $return->total_amount ?? 0,
                        'date' => $return->created_at->format('M d, Y'),
                        'status' => $return->status ?? 'Pending'
                    ];
                });
        }

        // Dynamic debit notes
        $recentDebitNotes = collect();
        if (class_exists('\\Workdo\\Account\\Models\\DebitNote')) {
            $recentDebitNotes = \Workdo\Account\Models\DebitNote::where('vendor_id', $user->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($note) {
                    return [
                        'id' => $note->id,
                        'debit_note_number' => $note->debit_note_number ?? 'DN-' . $note->id,
                        'amount' => $note->total_amount ?? 0,
                        'date' => $note->created_at->format('M d, Y'),
                        'status' => $note->status ?? 'Pending'
                    ];
                });
        }

        return Inertia::render('Account/Dashboard/VendorDashboard', [
            'stats' => [
                'total_payments' => $totalPayments,
                'total_expenses' => $totalExpenses,
                'payment_count' => $paymentCount
            ],
            'monthlyPayments' => $monthlyPayments,
            'recentReturnInvoices' => $recentReturnInvoices,
            'recentDebitNotes' => $recentDebitNotes,
            'vendor' => ['name' => $user->name]
        ]);
    }

    private function clientDashboard()
    {
        $user = Auth::user();

        $totalPayments = CustomerPayment::where('customer_id', $user->id)->sum('payment_amount');
        $totalRevenues = SalesInvoice::where('customer_id', $user->id)
            ->where('created_by', $user->created_by)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->sum('total_amount');
        $paymentCount = CustomerPayment::where('customer_id', $user->id)->count();

        $monthlyPayments = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');

            $monthPayments = CustomerPayment::where('customer_id', $user->id)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('payment_amount');

            $monthlyPayments[] = [
                'month' => $monthName,
                'payments' => $monthPayments
            ];
        }

        // Dynamic return invoices from SalesReturns
        $recentReturnInvoices = collect();
        if (class_exists('\\App\Models\\SalesInvoiceReturn')) {
            $recentReturnInvoices = SalesInvoiceReturn::where('customer_id', $user->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($return) {
                    return [
                        'id' => $return->id,
                        'invoice_number' => $return->return_number ?? 'RET-' . $return->id,
                        'amount' => $return->total_amount ?? 0,
                        'date' => $return->created_at->format('M d, Y'),
                        'status' => $return->status ?? 'Pending'
                    ];
                });
        }

        // Dynamic credit notes
        $recentCreditNotes = collect();
        if (class_exists('\\Workdo\\Account\\Models\\CreditNote')) {
            $recentCreditNotes = \Workdo\Account\Models\CreditNote::where('customer_id', $user->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function($note) {
                    return [
                        'id' => $note->id,
                        'credit_note_number' => $note->credit_note_number ?? 'CN-' . $note->id,
                        'amount' => $note->total_amount ?? 0,
                        'date' => $note->created_at->format('M d, Y'),
                        'status' => $note->status ?? 'Pending'
                    ];
                });
        }

        return Inertia::render('Account/Dashboard/ClientDashboard', [
            'stats' => [
                'total_payments' => $totalPayments,
                'total_revenues' => $totalRevenues,
                'payment_count' => $paymentCount
            ],
            'monthlyPayments' => $monthlyPayments,
            'recentReturnInvoices' => $recentReturnInvoices,
            'recentCreditNotes' => $recentCreditNotes,
            'customer' => ['name' => $user->name]
        ]);
    }

    private function staffDashboard()
    {
        $user = Auth::user();
        $creatorId = $user->created_by;
        $financialStats = $this->financialStats($creatorId);

        $totalClients = Customer::where('created_by', $creatorId)->count();
        $totalVendors = Vendor::where('created_by', $creatorId)->count();
        $monthlyRevenue = Revenue::where('created_by', $creatorId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount');
        $monthlyExpense = Expense::where('created_by', $creatorId)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount');

        $recentActivities = collect()
            ->merge(Revenue::where('created_by', $creatorId)->latest()->limit(3)->get()->map(function($item) {
                return ['type' => 'Revenue', 'title' => $item->revenue_number, 'amount' => $item->amount, 'date' => $item->created_at];
            }))
            ->merge(Expense::where('created_by', $creatorId)->latest()->limit(3)->get()->map(function($item) {
                return ['type' => 'Expense', 'title' => $item->expense_number, 'amount' => $item->amount, 'date' => $item->created_at];
            }))
            ->sortByDesc('date')
            ->take(6)
            ->values();

        return Inertia::render('Account/Dashboard/StaffDashboard', [
            'stats' => [
                'total_clients' => $totalClients,
                'total_vendors' => $totalVendors,
                'monthly_revenue' => $monthlyRevenue,
                'monthly_expense' => $monthlyExpense,
                ...$financialStats,
            ],
            'recentActivities' => $recentActivities
        ]);
    }

    private function financialStats(int $creatorId): array
    {
        $receivables = $this->reportService->getInvoiceAging()['aging_summary'];
        $payables = $this->reportService->getBillAging()['aging_summary'];

        $overdueReceivables = $receivables['total'] - $receivables['current'];
        $overduePayables = $payables['total'] - $payables['current'];

        $cashBalance = ChartOfAccount::where('created_by', $creatorId)
            ->whereIn('account_code', ['1000', '1005'])
            ->sum('current_balance');
        $activeBankAccounts = BankAccount::where('created_by', $creatorId)
            ->where('is_active', true)
            ->whereIn('account_type', ['0', '1'])
            ->get(['gl_account_id', 'current_balance']);

        $bankGlAccountIds = $activeBankAccounts->pluck('gl_account_id')
            ->filter()
            ->unique()
            ->values();

        if ($bankGlAccountIds->isNotEmpty()) {
            $trialBalance = $this->trialBalanceService->generateTrialBalance(
                now()->startOfYear()->toDateString(),
                now()->toDateString(),
            );
            $bankBalance = collect($trialBalance['accounts'])
                ->whereIn('id', $bankGlAccountIds->all())
                ->sum('closing_balance')
                + $activeBankAccounts->whereNull('gl_account_id')->sum('current_balance');
        } else {
            // Older companies may have bank records that have not yet been linked to GL accounts.
            $bankBalance = $activeBankAccounts->sum('current_balance');
        }

        return [
            'accounts_receivable' => round((float) $receivables['total'], 2),
            'receivables_due' => round((float) $overdueReceivables, 2),
            'accounts_payable' => round((float) $payables['total'], 2),
            'payables_due' => round((float) $overduePayables, 2),
            'cash_balance' => round((float) $cashBalance, 2),
            'bank_balance' => round((float) $bankBalance, 2),
        ];
    }
}

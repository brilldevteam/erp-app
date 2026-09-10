<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\ReportService;
use Workdo\DoubleEntry\Services\GeneralLedgerExcelExportService;
use Workdo\DoubleEntry\Services\AccountingReportExcelExportService;
use Workdo\Account\Models\ChartOfAccount;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    protected $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index()
    {
        if(Auth::user()->can('manage-double-entry-reports')){

            $currentYear = date('Y');
            $financialYear = [
                'year_start_date' => "$currentYear-01-01",
                'year_end_date' => "$currentYear-12-31",
            ];

            return Inertia::render('DoubleEntry/Reports/Index', [
                'financialYear' => $financialYear,
            ]);

        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function generalLedger(Request $request)
    {
        abort_unless(Auth::user()->can('view-general-ledger'), 403);

        $accounts = ChartOfAccount::where('created_by', creatorId())
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        $currentYear = date('Y');
        $financialYear = [
            'year_start_date' => "$currentYear-01-01",
            'year_end_date' => "$currentYear-12-31",
        ];

        $firstAccount = $accounts->first();
        $accountId = $request->account_id ?: ($firstAccount ? $firstAccount->id : null);

        $filters = [
            'account_id' => $accountId,
            'from_date' => $request->from_date ?: $financialYear['year_start_date'],
            'to_date' => $request->to_date ?: $financialYear['year_end_date'],
        ];

        $data = $accountId ? $this->reportService->getGeneralLedger($filters) : null;

        $selectedAccount = $accountId
            ? ChartOfAccount::where('created_by', creatorId())->find($accountId)
            : null;

        return response()->json([
            'data' => $data,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'financialYear' => $financialYear,
        ]);
    }

    public function printGeneralLedger(Request $request)
    {
        abort_unless(Auth::user()->can('print-general-ledger'), 403);
        $validated = $this->validateGeneralLedgerFilters($request);

        $filters = [
            'account_id' => $validated['account_id'],
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
        ];

        $data = $this->reportService->getGeneralLedger($filters);

        $selectedAccount = ChartOfAccount::where('created_by', creatorId())
            ->findOrFail($validated['account_id']);

        return Inertia::render('DoubleEntry/Reports/Print/GeneralLedger', [
            'data' => $data,
            'selectedAccount' => $selectedAccount,
            'filters' => $filters,
        ]);
    }

    public function exportGeneralLedger(Request $request, GeneralLedgerExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-general-ledger'), 403);
        $filters = $this->validateGeneralLedgerFilters($request);
        $account = ChartOfAccount::where('created_by', creatorId())
            ->findOrFail($filters['account_id']);
        $path = $exporter->create($this->reportService->getGeneralLedger($filters), $account, $filters);
        $filename = 'general-ledger-'.$account->account_code.'-'.$filters['from_date'].'-to-'.$filters['to_date'].'.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function validateGeneralLedgerFilters(Request $request): array
    {
        return $request->validate([
            'account_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query->where('created_by', creatorId())),
            ],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);
    }

    public function accountStatement(Request $request)
    {
        $accounts = ChartOfAccount::where('created_by', creatorId())
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'account_name']);

        $currentYear = date('Y');
        $financialYear = [
            'year_start_date' => "$currentYear-01-01",
            'year_end_date' => "$currentYear-12-31",
        ];

        $firstAccount = $accounts->first();
        $accountId = $request->account_id ?: ($firstAccount ? $firstAccount->id : null);

        $filters = [
            'account_id' => $accountId,
            'from_date' => $request->from_date ?: $financialYear['year_start_date'],
            'to_date' => $request->to_date ?: $financialYear['year_end_date'],
        ];

        $data = $accountId ? $this->reportService->getGeneralLedger($filters) : null;

        $selectedAccount = null;
        if ($accountId) {
            $selectedAccount = ChartOfAccount::find($accountId);
        }

        return response()->json([
            'data' => $data,
            'accounts' => $accounts,
            'selectedAccount' => $selectedAccount,
            'financialYear' => $financialYear,
        ]);
    }

    public function printAccountStatement(Request $request)
    {
        $filters = [
            'account_id' => $request->account_id,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
        ];

        $data = $this->reportService->getGeneralLedger($filters);

        $selectedAccount = null;
        if ($request->account_id) {
            $selectedAccount = ChartOfAccount::find($request->account_id);
        }

        return Inertia::render('DoubleEntry/Reports/Print/AccountStatement', [
            'data' => $data,
            'selectedAccount' => $selectedAccount,
            'filters' => $filters,
        ]);
    }

    public function exportAccountStatement(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-account-statement'), 403);
        $filters = $this->validateGeneralLedgerFilters($request);
        $account = ChartOfAccount::where('created_by', creatorId())->findOrFail($filters['account_id']);
        $data = $this->reportService->getGeneralLedger($filters);
        $rows = [[__('Opening Balance'), '', '', '', '', (float) $data['opening_balance']]];
        foreach ($data['transactions'] as $transaction) {
            $rows[] = [$transaction['date'], $transaction['description'], $transaction['reference_type'].' #'.$transaction['reference_id'],
                (float) $transaction['debit'], (float) $transaction['credit'], (float) $transaction['balance']];
        }
        $rows[] = [__('Closing Balance'), '', '', '', '', (float) $data['closing_balance']];
        $path = $exporter->create(__('Account Statement'), [__('Account') => $account->account_code.' - '.$account->account_name, __('Period') => $filters['from_date'].' to '.$filters['to_date']],
            [__('Date'), __('Description'), __('Reference'), __('Debit'), __('Credit'), __('Balance')], $rows, ['D', 'E', 'F']);
        return response()->download($path, 'account-statement-'.$account->account_code.'.xlsx')->deleteFileAfterSend(true);
    }

    public function journalEntry(Request $request)
    {
        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getJournalEntries([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'status' => $request->status,
        ]);

        return response()->json($data);
    }

    public function printJournalEntry(Request $request)
    {
        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getJournalEntries([
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'status' => $request->status,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/JournalEntry', [
            'data' => $data,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'status' => $request->status,
            ],
        ]);
    }

    public function exportJournalEntry(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-journal-entry'), 403);
        $filters = $request->validate(['from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date'], 'status' => ['nullable', 'string']]);
        $rows = [];
        foreach ($this->reportService->getJournalEntries($filters) as $entry) {
            foreach ($entry['items'] as $item) {
                $rows[] = [$entry['date'], $entry['journal_number'], $entry['reference_type'], $item['account_code'], $item['account_name'],
                    $item['description'] ?: $entry['description'], (float) $item['debit'], (float) $item['credit'], $entry['status']];
            }
        }
        $path = $exporter->create(__('Journal Entry Report'), [__('Period') => $filters['from_date'].' to '.$filters['to_date']],
            [__('Date'), __('Journal Number'), __('Reference'), __('Account Code'), __('Account Name'), __('Description'), __('Debit'), __('Credit'), __('Status')], $rows, ['G', 'H']);
        return response()->download($path, 'journal-entry-report.xlsx')->deleteFileAfterSend(true);
    }

    public function accountBalance(Request $request)
    {
        $currentYear = date('Y');
        $asOfDate = $request->as_of_date ?: "$currentYear-12-31";
        $accountType = $request->account_type;
        $showZeroBalances = $request->show_zero_balances === 'true';

        $data = $this->reportService->getAccountBalances([
            'as_of_date' => $asOfDate,
            'account_type' => $accountType,
            'show_zero_balances' => $showZeroBalances,
        ]);

        return response()->json($data);
    }

    public function printAccountBalance(Request $request)
    {
        $currentYear = date('Y');
        $asOfDate = $request->as_of_date ?: "$currentYear-12-31";
        $accountType = $request->account_type;
        $showZeroBalances = $request->show_zero_balances === 'true';

        $data = $this->reportService->getAccountBalances([
            'as_of_date' => $asOfDate,
            'account_type' => $accountType,
            'show_zero_balances' => $showZeroBalances,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/AccountBalance', [
            'data' => $data,
            'filters' => [
                'as_of_date' => $asOfDate,
                'account_type' => $accountType,
            ],
        ]);
    }

    public function exportAccountBalance(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-account-balance'), 403);
        $filters = $request->validate(['as_of_date' => ['required', 'date'], 'account_type' => ['nullable', 'string'], 'show_zero_balances' => ['nullable']]);
        $filters['show_zero_balances'] = filter_var($filters['show_zero_balances'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $data = $this->reportService->getAccountBalances($filters);
        $rows = [];
        foreach ($data['grouped'] as $group => $values) foreach ($values['accounts'] as $account) {
            $rows[] = [$group, $account['account_code'], $account['account_name'], $account['debit'], $account['credit'], $account['net_balance']];
        }
        $rows[] = [__('Totals'), '', '', $data['totals']['debit'], $data['totals']['credit'], $data['totals']['net']];
        $path = $exporter->create(__('Account Balance'), [__('As of') => $filters['as_of_date']],
            [__('Account Type'), __('Account Code'), __('Account Name'), __('Debit'), __('Credit'), __('Net Balance')], $rows, ['D', 'E', 'F']);
        return response()->download($path, 'account-balance-'.$filters['as_of_date'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function cashFlow(Request $request)
    {
        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getCashFlow([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return response()->json($data);
    }

    public function printCashFlow(Request $request)
    {
        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getCashFlow([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/CashFlow', [
            'data' => $data,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function exportCashFlow(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-cash-flow'), 403);
        $filters = $request->validate(['from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date']]);
        $data = $this->reportService->getCashFlow($filters);
        $rows = [[__('Beginning Cash'), $data['beginning_cash']], [__('Operating Activities'), $data['operating']],
            [__('Investing Activities'), $data['investing']], [__('Financing Activities'), $data['financing']],
            [__('Net Cash Flow'), $data['net_cash_flow']], [__('Ending Cash'), $data['ending_cash']]];
        $path = $exporter->create(__('Cash Flow'), [__('Period') => $filters['from_date'].' to '.$filters['to_date']], [__('Section'), __('Amount')], $rows, ['B']);
        return response()->download($path, 'cash-flow-'.$filters['from_date'].'-to-'.$filters['to_date'].'.xlsx')->deleteFileAfterSend(true);
    }

    public function expenseReport(Request $request)
    {
        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getExpenseReport([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return response()->json($data);
    }

    public function printExpenseReport(Request $request)
    {
        $currentYear = date('Y');
        $fromDate = $request->from_date ?: "$currentYear-01-01";
        $toDate = $request->to_date ?: "$currentYear-12-31";

        $data = $this->reportService->getExpenseReport([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return Inertia::render('DoubleEntry/Reports/Print/ExpenseReport', [
            'data' => $data,
            'filters' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    public function exportExpenseReport(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-expense-report'), 403);
        $filters = $request->validate(['from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date']]);
        $data = $this->reportService->getExpenseReport($filters);
        $rows = array_map(fn ($expense) => [$expense['account_code'], $expense['account_name'], $expense['amount']], $data['expenses']);
        $rows[] = ['', __('Total Expenses'), $data['total_expenses']];
        $path = $exporter->create(__('Expense Report'), [__('Period') => $filters['from_date'].' to '.$filters['to_date']], [__('Account Code'), __('Account Name'), __('Amount')], $rows, ['C']);
        return response()->download($path, 'expense-report-'.$filters['from_date'].'-to-'.$filters['to_date'].'.xlsx')->deleteFileAfterSend(true);
    }
}

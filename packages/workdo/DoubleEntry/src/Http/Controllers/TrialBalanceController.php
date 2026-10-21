<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\TrialBalanceService;
use Workdo\DoubleEntry\Services\AccountingReportExcelExportService;

class TrialBalanceController extends Controller
{
    protected $trialBalanceService;

    public function __construct(TrialBalanceService $trialBalanceService)
    {
        $this->trialBalanceService = $trialBalanceService;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-trial-balance')){
            $currentYear = date('Y');
            $fromDate = $request->from_date ?: "$currentYear-01-01";
            $toDate = $request->to_date ?: "$currentYear-12-31";

            $trialBalance = $this->trialBalanceService->generateTrialBalance($fromDate, $toDate);

            return Inertia::render('DoubleEntry/TrialBalance/Index', [
                'trialBalance' => $trialBalance,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function print(Request $request)
    {
        if(Auth::user()->can('print-trial-balance')){
            $currentYear = date('Y');
            $fromDate = $request->from_date ?: "$currentYear-01-01";
            $toDate = $request->to_date ?: "$currentYear-12-31";

            $trialBalance = $this->trialBalanceService->generateTrialBalance($fromDate, $toDate);

            return Inertia::render('DoubleEntry/TrialBalance/Print', [
                'trialBalance' => $trialBalance,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function excel(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-trial-balance'), 403);
        $filters = $request->validate(['from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date']]);
        $report = $this->trialBalanceService->generateTrialBalance($filters['from_date'], $filters['to_date']);
        // Opening and closing balances are signed (positive = debit), so each is split into debit and credit columns.
        $rows = array_map(fn ($account) => [$account['account_code'], $account['account_name'],
            max($account['opening_balance'], 0), max(-$account['opening_balance'], 0),
            $account['net_debit'], $account['net_credit'],
            max($account['closing_balance'], 0), max(-$account['closing_balance'], 0)], $report['accounts']);
        $totals = $report['totals'];
        $rows[] = ['', __('Totals'), $totals['opening_debit'], $totals['opening_credit'], $totals['net_debit'], $totals['net_credit'], $totals['closing_debit'], $totals['closing_credit']];
        $path = $exporter->create(__('Trial Balance'), [__('Period') => $filters['from_date'].' to '.$filters['to_date'], __('Status') => $report['is_balanced'] ? __('Balanced') : __('Unbalanced')],
            [__('Account Code'), __('Account Name'), __('Opening Debit'), __('Opening Credit'), __('Net Debit'), __('Net Credit'), __('Closing Debit'), __('Closing Credit')], $rows, ['C', 'D', 'E', 'F', 'G', 'H']);
        return response()->download($path, 'TB-'.$filters['from_date'].'-to-'.$filters['to_date'].'.xlsx')->deleteFileAfterSend(true);
    }
}

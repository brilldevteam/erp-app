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
        $rows = array_map(fn ($account) => [$account['account_code'], $account['account_name'], $account['opening_balance'],
            $account['period_debit'], $account['period_credit'], $account['debit'], $account['credit']], $report['accounts']);
        $rows[] = ['', __('Totals'), '', '', '', $report['total_debit'], $report['total_credit']];
        $path = $exporter->create(__('Trial Balance'), [__('Period') => $filters['from_date'].' to '.$filters['to_date'], __('Status') => $report['is_balanced'] ? __('Balanced') : __('Unbalanced')],
            [__('Account Code'), __('Account Name'), __('Opening Balance'), __('Period Debit'), __('Period Credit'), __('Debit'), __('Credit')], $rows, ['C', 'D', 'E', 'F', 'G']);
        return response()->download($path, 'trial-balance-'.$filters['from_date'].'-to-'.$filters['to_date'].'.xlsx')->deleteFileAfterSend(true);
    }
}

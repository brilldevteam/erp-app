<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\ProfitLossService;
use Workdo\DoubleEntry\Services\AccountingReportExcelExportService;

class ProfitLossController extends Controller
{
    protected $profitLossService;

    public function __construct(ProfitLossService $profitLossService)
    {
        $this->profitLossService = $profitLossService;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-profit-loss')){
            $currentYear = date('Y');
            $fromDate = $request->from_date ?: "$currentYear-01-01";
            $toDate = $request->to_date ?: "$currentYear-12-31";

            $profitLoss = $this->profitLossService->generateProfitLoss($fromDate, $toDate);

            return Inertia::render('DoubleEntry/ProfitLoss/Index', [
                'profitLoss' => $profitLoss,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function print(Request $request)
    {
        if(Auth::user()->can('print-profit-loss')){
            $currentYear = date('Y');
            $fromDate = $request->from_date ?: "$currentYear-01-01";
            $toDate = $request->to_date ?: "$currentYear-12-31";

            $profitLoss = $this->profitLossService->generateProfitLoss($fromDate, $toDate);

            return Inertia::render('DoubleEntry/ProfitLoss/Print', [
                'profitLoss' => $profitLoss,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function excel(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-profit-loss'), 403);
        $filters = $request->validate(['from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date']]);
        $report = $this->profitLossService->generateProfitLoss($filters['from_date'], $filters['to_date']);
        $rows = [];
        foreach ($report['revenue'] as $account) $rows[] = [__('Revenue'), $account->account_code, $account->account_name, (float) $account->balance];
        $rows[] = [__('Total Revenue'), '', '', (float) $report['total_revenue']];
        foreach ($report['expenses'] as $account) $rows[] = [__('Expense'), $account->account_code, $account->account_name, (float) $account->balance];
        $rows[] = [__('Total Expenses'), '', '', (float) $report['total_expenses']];
        $rows[] = [__('Net Profit'), '', '', (float) $report['net_profit']];
        $path = $exporter->create(__('Profit & Loss'), [__('Period') => $filters['from_date'].' to '.$filters['to_date']],
            [__('Section'), __('Account Code'), __('Account Name'), __('Amount')], $rows, ['D']);
        return response()->download($path, 'profit-loss-'.$filters['from_date'].'-to-'.$filters['to_date'].'.xlsx')->deleteFileAfterSend(true);
    }
}

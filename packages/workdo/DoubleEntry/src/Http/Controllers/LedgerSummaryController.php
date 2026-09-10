<?php

namespace Workdo\DoubleEntry\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\LedgerSummaryService;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\DoubleEntry\Services\AccountingReportExcelExportService;

class LedgerSummaryController extends Controller
{
    protected $ledgerSummaryService;

    public function __construct(LedgerSummaryService $ledgerSummaryService)
    {
        $this->ledgerSummaryService = $ledgerSummaryService;
    }

    public function index(Request $request)
    {
        if(Auth::user()->can('manage-ledger-summary')){
            $entries = $this->ledgerSummaryService->getAllLedgerEntries(
                $request->from_date,
                $request->to_date,
                $request->account_id
            );

            $accounts = ChartOfAccount::query()
                ->where('created_by', creatorId())
                ->orderBy('account_code', 'asc')
                ->select('id', 'account_code', 'account_name')
                ->get();

            return Inertia::render('DoubleEntry/LedgerSummary/Index', [
                'entries' => $entries,
                'accounts' => $accounts,
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function print(Request $request)
    {
        if(Auth::user()->can('print-ledger-summary')){
            $entries = $this->ledgerSummaryService->getAllLedgerEntries(
                $request->from_date,
                $request->to_date,
                $request->account_id,
                false
            );

            $accounts = ChartOfAccount::query()
                ->where('created_by', creatorId())
                ->where('is_active', 1)
                ->orderBy('account_code', 'asc')
                ->select('id', 'account_code', 'account_name')
                ->get();

            $selectedAccount = null;
            if ($request->account_id) {
                $selectedAccount = $accounts->firstWhere('id', $request->account_id);
            }

            return Inertia::render('DoubleEntry/LedgerSummary/Print', [
                'entries' => $entries,
                'selectedAccount' => $selectedAccount,
                'filters' => [
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                ],
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function excel(Request $request, AccountingReportExcelExportService $exporter)
    {
        abort_unless(Auth::user()->can('print-ledger-summary'), 403);
        $filters = $request->validate([
            'from_date' => ['nullable', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'account_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('created_by', creatorId()))],
        ]);
        $entries = $this->ledgerSummaryService->getAllLedgerEntries($filters['from_date'] ?? null, $filters['to_date'] ?? null, $filters['account_id'] ?? null, false);
        $rows = $entries->map(fn ($entry) => [$entry->journal_date, $entry->account_code, $entry->account_name,
            $entry->reference_type, $entry->description ?: $entry->journal_description, (float) $entry->debit_amount, (float) $entry->credit_amount])->all();
        $path = $exporter->create(__('Ledger Summary'), [__('Period') => ($filters['from_date'] ?? __('All')).' to '.($filters['to_date'] ?? __('All'))],
            [__('Date'), __('Account Code'), __('Account Name'), __('Reference'), __('Description'), __('Debit'), __('Credit')], $rows, ['F', 'G']);
        return response()->download($path, 'ledger-summary.xlsx')->deleteFileAfterSend(true);
    }
}

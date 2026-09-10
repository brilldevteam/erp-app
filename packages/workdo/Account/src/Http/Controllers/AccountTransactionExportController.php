<?php

namespace Workdo\Account\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\DoubleEntry\Services\ReportService;
use Workdo\DoubleEntry\Services\AccountingReportExcelExportService;

class AccountTransactionExportController extends Controller
{
    public function __invoke(Request $request, ChartOfAccount $account, ReportService $reports, AccountingReportExcelExportService $excel)
    {
        abort_unless(auth()->user()->can('view-chart-of-accounts') && auth()->user()->can('print-general-ledger'), 403);
        abort_unless((int)$account->created_by === (int)creatorId(), 404);
        $filters = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'format' => ['required', 'in:pdf,excel'],
        ]);
        $data = $reports->getGeneralLedger($filters + ['account_id' => $account->id]);
        $rows = [[__('Opening Balance'), '', '', '', 0, 0, abs($data['opening_balance']), $this->side($data['opening_balance'])]];
        $debit = 0; $credit = 0;
        foreach ($data['transactions'] as $transaction) {
            $debit += (float)$transaction['debit']; $credit += (float)$transaction['credit'];
            $rows[] = [$transaction['date'], $transaction['journal_number'],
                trim($transaction['reference_type'].($transaction['reference_id'] ? ' #'.$transaction['reference_id'] : '')),
                $transaction['description'], (float)$transaction['debit'], (float)$transaction['credit'],
                abs($transaction['balance']), $this->side($transaction['balance'])];
        }
        $rows[] = [__('Period Totals'), '', '', '', round($debit,2), round($credit,2), null, ''];
        $rows[] = [__('Closing Balance'), '', '', '', null, null, abs($data['closing_balance']), $this->side($data['closing_balance'])];
        $settings = \App\Models\Setting::where('created_by', creatorId())->whereIn('key', ['company_name','defaultCurrency'])->pluck('value','key');
        $metadata = [__('Company') => $settings['company_name'] ?? \App\Models\User::find(creatorId())?->name,
            __('Account') => $account->account_code.' - '.$account->account_name,
            __('Period') => $filters['from_date'].' to '.$filters['to_date'],
            __('Currency') => $settings['defaultCurrency'] ?? ''];
        $headers = [__('Date'), __('Journal Number'), __('Reference'), __('Description'), __('Debit'), __('Credit'), __('Balance'), __('Dr/Cr')];
        $filename = 'account-transactions-'.preg_replace('/[^a-zA-Z0-9_-]/','_', $account->account_code).'-'.$filters['from_date'].'-'.$filters['to_date'];
        if ($filters['format'] === 'pdf') {
            return Inertia::render('Account/ChartOfAccounts/TransactionsPrint', compact('rows','metadata','headers','filename'));
        }
        // User-controlled labels must not become spreadsheet formulas.
        $safe = fn($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? "'".$value : $value;
        $path = $excel->create(__('Account Transactions'), array_map($safe,$metadata), $headers,
            array_map(fn($row) => array_map($safe,$row), $rows), ['E','F','G']);
        return response()->download($path, $filename.'.xlsx')->deleteFileAfterSend(true);
    }

    private function side($value): string { return $value > 0 ? 'Dr' : ($value < 0 ? 'Cr' : '-'); }
}

<?php

namespace Workdo\Account\Http\Controllers;

use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\BankAccount;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Workdo\DoubleEntry\Services\AccountingReportExcelExportService;

class BankTransactionController extends Controller
{
    public function index(Request $request)
    {
        if(Auth::user()->can('manage-bank-transactions')){
            $query = $this->filteredQuery($request);
            [$sortField, $sortDirection] = $this->sortOptions($request);
            $query->orderBy($sortField, $sortDirection);

            $transactions = $query->paginate($request->get('per_page', 10));
            $bankAccounts = BankAccount::where('is_active', true)->where('created_by', creatorId())->get();

            return Inertia::render('Account/BankTransactions/Index', [
                'transactions' => $transactions,
                'bankAccounts' => $bankAccounts,
                'filters' => $request->only(['bank_account_id', 'transaction_type', 'search', 'date_from', 'date_to'])
            ]);
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    public function export(Request $request, AccountingReportExcelExportService $excel)
    {
        abort_unless(Auth::user()->can('manage-bank-transactions'), 403);

        $filters = $request->validate([
            'bank_account_id' => ['nullable', 'integer'],
            'transaction_type' => ['nullable', 'in:debit,credit'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'in:asc,desc'],
            'format' => ['required', 'in:csv,excel'],
        ]);

        [$sortField, $sortDirection] = $this->sortOptions($request);
        $transactions = $this->filteredQuery($request)
            ->orderBy($sortField, $sortDirection)
            ->get();

        $headers = [
            __('Date'), __('Bank Account'), __('Reference'), __('Type'), __('Description'),
            __('Amount'), __('Running Balance'), __('Status'), __('Reconciliation Status'),
        ];
        $safe = fn ($value) => is_string($value) && preg_match('/^[=+@-]/', $value) ? "'".$value : $value;
        $rows = $transactions->map(function ($transaction) use ($safe) {
            $account = $transaction->bankAccount;

            return array_map($safe, [
                $transaction->transaction_date,
                $account ? $account->account_name.' ('.$account->account_number.')' : '',
                $transaction->reference_number,
                ucfirst($transaction->transaction_type),
                $transaction->description,
                (float) $transaction->amount,
                (float) $transaction->running_balance,
                ucfirst($transaction->transaction_status),
                ucfirst($transaction->reconciliation_status),
            ]);
        })->all();

        $filename = 'bank-transactions-'.now()->format('Y-m-d-His');
        if ($filters['format'] === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers);
                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $metadata = [
            __('Period') => ($filters['date_from'] ?? __('All')).' - '.($filters['date_to'] ?? __('All')),
            __('Transaction Type') => isset($filters['transaction_type']) ? ucfirst($filters['transaction_type']) : __('All'),
            __('Search') => $filters['search'] ?? __('All'),
        ];
        $path = $excel->create(__('Bank Transactions'), $metadata, $headers, $rows, ['F', 'G']);

        return response()->download($path, $filename.'.xlsx')->deleteFileAfterSend(true);
    }

    public function markReconciled($id)
    {
        if(Auth::user()->can('reconcile-bank-transactions')){
            $transaction = BankTransaction::where('id', $id)
                ->where('created_by', creatorId())
                ->first();

            if($transaction && $transaction->reconciliation_status === 'unreconciled') {
                $transaction->reconciliation_status = 'reconciled';
                $transaction->save();

                return back()->with('success', __('Transaction marked as reconciled'));
            }

            return back()->with('error', __('Transaction not found or already reconciled'));
        }
        else{
            return back()->with('error', __('Permission denied'));
        }
    }

    private function filteredQuery(Request $request)
    {
        $query = BankTransaction::with(['bankAccount'])
            ->where('created_by', creatorId());

        $query->when($request->filled('bank_account_id'), fn ($query) =>
            $query->where('bank_account_id', $request->bank_account_id));
        $query->when($request->filled('transaction_type'), fn ($query) =>
            $query->where('transaction_type', $request->transaction_type));
        $query->when($request->filled('date_from'), fn ($query) =>
            $query->whereDate('transaction_date', '>=', $request->date_from));
        $query->when($request->filled('date_to'), fn ($query) =>
            $query->whereDate('transaction_date', '<=', $request->date_to));
        $query->when($request->filled('search'), function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('reference_number', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        });

        return $query;
    }

    private function sortOptions(Request $request): array
    {
        $allowed = ['transaction_date', 'reference_number', 'transaction_type', 'amount', 'transaction_status', 'created_at'];
        $field = in_array($request->get('sort'), $allowed, true) ? $request->get('sort') : 'created_at';
        $direction = $request->get('direction') === 'asc' ? 'asc' : 'desc';

        return [$field, $direction];
    }
}

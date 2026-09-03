<?php

namespace Workdo\DoubleEntry\Services;

use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

class TrialBalanceService
{
    public function generateTrialBalance($fromDate, $toDate)
    {
        $accounts = DB::select("
            SELECT
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.normal_balance,
                COALESCE(coa.opening_balance, 0) as opening_balance,
                COALESCE(SUM(CASE WHEN je.journal_date >= ? AND je.journal_date <= ? AND je.status = 'posted' THEN jei.debit_amount ELSE 0 END), 0) as period_debit,
                COALESCE(SUM(CASE WHEN je.journal_date >= ? AND je.journal_date <= ? AND je.status = 'posted' THEN jei.credit_amount ELSE 0 END), 0) as period_credit
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_items jei ON coa.id = jei.account_id
            LEFT JOIN journal_entries je ON jei.journal_entry_id = je.id
            WHERE coa.is_active = 1
              AND coa.created_by = ?
            GROUP BY coa.id, coa.account_code, coa.account_name, coa.normal_balance, coa.opening_balance
            ORDER BY coa.account_code ASC
        ", [$fromDate, $toDate, $fromDate, $toDate, creatorId()]);

        $totalDebit = 0;
        $totalCredit = 0;
        $accountsList = [];

        foreach($accounts as $account) {
            $openingBalance = (float)$account->opening_balance;
            $periodDebit = (float)$account->period_debit;
            $periodCredit = (float)$account->period_credit;
            $balance = $account->normal_balance === 'debit'
                ? $openingBalance + $periodDebit - $periodCredit
                : $openingBalance + $periodCredit - $periodDebit;

            if (abs($balance) > 0.01) {
                $debit = 0;
                $credit = 0;

                if ($balance > 0) {
                    if ($account->normal_balance === 'debit') {
                        $debit = $balance;
                        $totalDebit += $debit;
                    } else {
                        $credit = $balance;
                        $totalCredit += $credit;
                    }
                } else {
                    // Negative balance goes to opposite side
                    if ($account->normal_balance === 'debit') {
                        $credit = abs($balance);
                        $totalCredit += $credit;
                    } else {
                        $debit = abs($balance);
                        $totalDebit += $debit;
                    }
                }

                $accountsList[] = [
                    'id' => $account->id,
                    'account_code' => $account->account_code,
                    'account_name' => $account->account_name,
                    'opening_balance' => round($openingBalance, 2),
                    'period_debit' => round($periodDebit, 2),
                    'period_credit' => round($periodCredit, 2),
                    'debit' => $debit,
                    'credit' => $credit
                ];
            }
        }

        $activeAccounts = ChartOfAccount::where('is_active', true)->where('created_by', creatorId())->count();
        $postedJournals = JournalEntry::where('status', 'posted')
            ->where('created_by', creatorId())
            ->whereBetween('journal_date', [$fromDate, $toDate])
            ->count();

        return [
            'accounts' => $accountsList,
            'total_debit' => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.01,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'diagnostics' => [
                'active_accounts' => $activeAccounts,
                'posted_journals' => $postedJournals,
                'has_accounts' => $activeAccounts > 0,
                'has_period_journals' => $postedJournals > 0,
            ],
        ];
    }
}

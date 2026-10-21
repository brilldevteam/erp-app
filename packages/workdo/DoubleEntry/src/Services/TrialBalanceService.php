<?php

namespace Workdo\DoubleEntry\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

class TrialBalanceService
{
    private const RETAINED_EARNINGS_CODE = '3200';

    /**
     * Trial balance with opening, net movement and closing balance per account (Zoho layout).
     *
     * Balances are signed: positive = debit, negative = credit.
     * - Balance sheet accounts (1000-3999) carry their balance forward from all earlier periods.
     * - Income and expense accounts (4000-5999) start each calendar year at zero; their earlier-year
     *   results are added to the opening balance of Retained Earnings.
     * - Net debit / net credit is each account's net movement within the period.
     */
    public function generateTrialBalance($fromDate, $toDate)
    {
        $yearStart = Carbon::parse($fromDate)->startOfYear()->toDateString();

        $accounts = DB::select("
            SELECT
                coa.id,
                coa.account_code,
                coa.account_name,
                coa.normal_balance,
                COALESCE(coa.opening_balance, 0) as opening_balance,
                COALESCE(SUM(CASE WHEN je.status = 'posted' AND je.journal_date < ? THEN jei.debit_amount - jei.credit_amount ELSE 0 END), 0) as before_from,
                COALESCE(SUM(CASE WHEN je.status = 'posted' AND je.journal_date < ? THEN jei.debit_amount - jei.credit_amount ELSE 0 END), 0) as before_year,
                COALESCE(SUM(CASE WHEN je.status = 'posted' AND je.journal_date >= ? AND je.journal_date <= ? THEN jei.debit_amount - jei.credit_amount ELSE 0 END), 0) as period_net,
                COUNT(CASE WHEN je.status = 'posted' AND je.journal_date >= ? AND je.journal_date <= ? THEN 1 END) as period_lines
            FROM chart_of_accounts coa
            LEFT JOIN journal_entry_items jei ON coa.id = jei.account_id
            LEFT JOIN journal_entries je ON jei.journal_entry_id = je.id
            WHERE coa.is_active = 1
              AND coa.created_by = ?
            GROUP BY coa.id, coa.account_code, coa.account_name, coa.normal_balance, coa.opening_balance
            ORDER BY coa.account_code ASC
        ", [$fromDate, $yearStart, $fromDate, $toDate, $fromDate, $toDate, creatorId()]);

        $rows = [];
        $priorYearsResult = 0.0;
        $retainedEarningsKey = null;

        foreach ($accounts as $account) {
            $openingBalance = (float) $account->opening_balance;
            $openingSigned = $account->normal_balance === 'debit' ? $openingBalance : -$openingBalance;
            $section = $this->section($account->account_code);

            if (in_array($section, ['income', 'expense'], true)) {
                $opening = (float) $account->before_from - (float) $account->before_year;
                $priorYearsResult += $openingSigned + (float) $account->before_year;
            } else {
                $opening = $openingSigned + (float) $account->before_from;
            }

            $rows[$account->id] = [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->account_name,
                'section' => $section,
                'opening' => $opening,
                'period_net' => (float) $account->period_net,
                'period_lines' => (int) $account->period_lines,
            ];

            if ($account->account_code === self::RETAINED_EARNINGS_CODE) {
                $retainedEarningsKey = $account->id;
            }
        }

        if (abs($priorYearsResult) >= 0.005) {
            if ($retainedEarningsKey === null) {
                $retainedEarningsKey = 'retained-earnings';
                $rows[$retainedEarningsKey] = [
                    'id' => null,
                    'account_code' => self::RETAINED_EARNINGS_CODE,
                    'account_name' => __('Retained Earnings'),
                    'section' => 'equity',
                    'opening' => 0.0,
                    'period_net' => 0.0,
                    'period_lines' => 0,
                ];
            }
            $rows[$retainedEarningsKey]['opening'] += $priorYearsResult;
        }

        $totals = ['opening_debit' => 0, 'opening_credit' => 0, 'net_debit' => 0, 'net_credit' => 0, 'closing_debit' => 0, 'closing_credit' => 0];
        $accountsList = [];

        foreach ($rows as $row) {
            $opening = round($row['opening'], 2);
            $periodNet = round($row['period_net'], 2);
            $closing = round($opening + $periodNet, 2);

            if (abs($opening) < 0.005 && abs($closing) < 0.005 && $row['period_lines'] === 0) {
                continue;
            }

            $netDebit = max($periodNet, 0);
            $netCredit = max(-$periodNet, 0);

            $totals['opening_debit'] += max($opening, 0);
            $totals['opening_credit'] += max(-$opening, 0);
            $totals['net_debit'] += $netDebit;
            $totals['net_credit'] += $netCredit;
            $totals['closing_debit'] += max($closing, 0);
            $totals['closing_credit'] += max(-$closing, 0);

            $accountsList[] = [
                'id' => $row['id'],
                'account_code' => $row['account_code'],
                'account_name' => $row['account_name'],
                'section' => $row['section'],
                'opening_balance' => $opening,
                'net_debit' => $netDebit,
                'net_credit' => $netCredit,
                'closing_balance' => $closing,
            ];
        }

        usort($accountsList, fn ($a, $b) => strcmp((string) $a['account_code'], (string) $b['account_code']));
        $totals = array_map(fn ($value) => round($value, 2), $totals);

        $activeAccounts = ChartOfAccount::where('is_active', true)->where('created_by', creatorId())->count();
        $postedJournals = JournalEntry::where('status', 'posted')
            ->where('created_by', creatorId())
            ->whereBetween('journal_date', [$fromDate, $toDate])
            ->count();

        return [
            'accounts' => $accountsList,
            'totals' => $totals,
            'total_debit' => $totals['net_debit'],
            'total_credit' => $totals['net_credit'],
            'is_balanced' => abs($totals['net_debit'] - $totals['net_credit']) < 0.01
                && abs($totals['opening_debit'] - $totals['opening_credit']) < 0.01
                && abs($totals['closing_debit'] - $totals['closing_credit']) < 0.01,
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

    private function section($accountCode): string
    {
        $code = (int) $accountCode;

        return match (true) {
            $code >= 1000 && $code <= 1999 => 'assets',
            $code >= 2000 && $code <= 2999 => 'liabilities',
            $code >= 3000 && $code <= 3999 => 'equity',
            $code >= 4000 && $code <= 4999 => 'income',
            $code >= 5000 && $code <= 5999 => 'expense',
            default => 'other',
        };
    }
}

<?php

namespace App\Console\Commands;

use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CustomerPayment;
use Workdo\Account\Models\Expense;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\Revenue;
use Workdo\Account\Models\VendorPayment;
use Workdo\Account\Services\BankTransactionsService;
use Workdo\Account\Services\JournalService;

class RepairDoubleEntryReports extends Command
{
    protected $signature = 'double-entry:repair-reports
        {--company= : Company/user id to repair}
        {--dry-run : Show what would be repaired without changing data}';

    protected $description = 'Backfill missing accounting journals and recalculate balances used by Double Entry reports.';

    public function handle(JournalService $journalService, BankTransactionsService $bankTransactionsService): int
    {
        $companyId = (int) $this->option('company');
        $dryRun = (bool) $this->option('dry-run');

        if (!$companyId) {
            $this->error('Please pass --company={id}. This command intentionally repairs one company at a time.');
            return self::FAILURE;
        }

        $company = User::find($companyId);
        if (!$company) {
            $this->error("Company/user {$companyId} was not found.");
            return self::FAILURE;
        }

        Auth::login($company);

        $summary = [
            'sales_invoice_journals' => 0,
            'revenue_journals' => 0,
            'expense_journals' => 0,
            'customer_payment_journals' => 0,
            'vendor_payment_journals' => 0,
            'journal_dates_fixed' => 0,
            'balances_recalculated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $this->line(($dryRun ? 'Dry run' : 'Repairing') . " Double Entry reports for company {$companyId}.");

        DB::transaction(function () use ($dryRun, $journalService, $bankTransactionsService, &$summary) {
            $this->repairSalesInvoices($journalService, $dryRun, $summary);
            $this->repairRevenues($journalService, $bankTransactionsService, $dryRun, $summary);
            $this->repairExpenses($journalService, $bankTransactionsService, $dryRun, $summary);
            $this->repairCustomerPayments($journalService, $bankTransactionsService, $dryRun, $summary);
            $this->repairVendorPayments($journalService, $bankTransactionsService, $dryRun, $summary);
            $this->repairJournalDates($dryRun, $summary);

            if (!$dryRun) {
                $summary['balances_recalculated'] = $this->recalculateChartBalances();
            } else {
                $summary['balances_recalculated'] = ChartOfAccount::where('created_by', creatorId())->count();
            }
        });

        foreach ($summary as $label => $count) {
            $this->line(str_replace('_', ' ', ucfirst($label)) . ": {$count}");
        }

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function repairSalesInvoices(JournalService $journalService, bool $dryRun, array &$summary): void
    {
        SalesInvoice::where('created_by', creatorId())
            ->whereIn('status', ['posted', 'partial', 'paid'])
            ->chunkById(100, function ($invoices) use ($journalService, $dryRun, &$summary) {
                foreach ($invoices as $invoice) {
                    $referenceType = $invoice->type === 'product' ? 'sales_invoice' : 'service_invoice';
                    if ($this->hasJournal($referenceType, $invoice->id)) {
                        $summary['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $summary['sales_invoice_journals']++;
                        continue;
                    }

                    try {
                        if ($invoice->type === 'product') {
                            $journalService->createSalesInvoiceJournal($invoice);
                            $journalService->createSalesCOGSJournal($invoice);
                        } else {
                            $journalService->createServiceInvoiceJournal($invoice);
                        }
                        $summary['sales_invoice_journals']++;
                    } catch (\Throwable $exception) {
                        $summary['errors']++;
                        $this->warn("Sales invoice {$invoice->id}: {$exception->getMessage()}");
                    }
                }
            });
    }

    private function repairRevenues(JournalService $journalService, BankTransactionsService $bankTransactionsService, bool $dryRun, array &$summary): void
    {
        Revenue::where('created_by', creatorId())
            ->where('status', 'posted')
            ->chunkById(100, function ($revenues) use ($journalService, $bankTransactionsService, $dryRun, &$summary) {
                foreach ($revenues as $revenue) {
                    if ($this->hasJournal('revenue', $revenue->id)) {
                        $summary['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $summary['revenue_journals']++;
                        continue;
                    }

                    try {
                        $journalService->createRevenueEntryJournal($revenue);
                        $bankTransactionsService->createRevenuePayment($revenue);
                        $summary['revenue_journals']++;
                    } catch (\Throwable $exception) {
                        $summary['errors']++;
                        $this->warn("Revenue {$revenue->id}: {$exception->getMessage()}");
                    }
                }
            });
    }

    private function repairExpenses(JournalService $journalService, BankTransactionsService $bankTransactionsService, bool $dryRun, array &$summary): void
    {
        Expense::where('created_by', creatorId())
            ->where('status', 'posted')
            ->chunkById(100, function ($expenses) use ($journalService, $bankTransactionsService, $dryRun, &$summary) {
                foreach ($expenses as $expense) {
                    if ($this->hasJournal('expense', $expense->id)) {
                        $summary['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $summary['expense_journals']++;
                        continue;
                    }

                    try {
                        $journalService->createExpenseEntryJournal($expense);
                        $bankTransactionsService->createExpensePayment($expense);
                        $summary['expense_journals']++;
                    } catch (\Throwable $exception) {
                        $summary['errors']++;
                        $this->warn("Expense {$expense->id}: {$exception->getMessage()}");
                    }
                }
            });
    }

    private function repairCustomerPayments(JournalService $journalService, BankTransactionsService $bankTransactionsService, bool $dryRun, array &$summary): void
    {
        CustomerPayment::where('created_by', creatorId())
            ->where('status', 'cleared')
            ->chunkById(100, function ($payments) use ($journalService, $bankTransactionsService, $dryRun, &$summary) {
                foreach ($payments as $payment) {
                    if ($this->hasJournal('customer_payment', $payment->id)) {
                        $summary['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $summary['customer_payment_journals']++;
                        continue;
                    }

                    try {
                        $journalService->createCustomerPaymentJournal($payment);
                        $bankTransactionsService->createCustomerPayment($payment);
                        $summary['customer_payment_journals']++;
                    } catch (\Throwable $exception) {
                        $summary['errors']++;
                        $this->warn("Customer payment {$payment->id}: {$exception->getMessage()}");
                    }
                }
            });
    }

    private function repairVendorPayments(JournalService $journalService, BankTransactionsService $bankTransactionsService, bool $dryRun, array &$summary): void
    {
        VendorPayment::where('created_by', creatorId())
            ->where('status', 'cleared')
            ->chunkById(100, function ($payments) use ($journalService, $bankTransactionsService, $dryRun, &$summary) {
                foreach ($payments as $payment) {
                    if ($this->hasJournal('vendor_payment', $payment->id)) {
                        $summary['skipped']++;
                        continue;
                    }

                    if ($dryRun) {
                        $summary['vendor_payment_journals']++;
                        continue;
                    }

                    try {
                        $journalService->createVendorPaymentJournal($payment);
                        $bankTransactionsService->createVendorPayment($payment);
                        $summary['vendor_payment_journals']++;
                    } catch (\Throwable $exception) {
                        $summary['errors']++;
                        $this->warn("Vendor payment {$payment->id}: {$exception->getMessage()}");
                    }
                }
            });
    }

    private function repairJournalDates(bool $dryRun, array &$summary): void
    {
        $revenueJournals = JournalEntry::where('journal_entries.created_by', creatorId())
            ->where('reference_type', 'revenue')
            ->join('revenues', 'journal_entries.reference_id', '=', 'revenues.id')
            ->whereColumn('journal_entries.journal_date', '!=', 'revenues.revenue_date');

        $expenseJournals = JournalEntry::where('journal_entries.created_by', creatorId())
            ->where('reference_type', 'expense')
            ->join('expenses', 'journal_entries.reference_id', '=', 'expenses.id')
            ->whereColumn('journal_entries.journal_date', '!=', 'expenses.expense_date');

        $summary['journal_dates_fixed'] += (clone $revenueJournals)->count();
        $summary['journal_dates_fixed'] += (clone $expenseJournals)->count();

        if ($dryRun) {
            return;
        }

        (clone $revenueJournals)->select('journal_entries.id', 'revenues.revenue_date')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    JournalEntry::where('id', $row->id)->update(['journal_date' => $row->revenue_date]);
                }
            }, 'journal_entries.id', 'id');

        (clone $expenseJournals)->select('journal_entries.id', 'expenses.expense_date')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    JournalEntry::where('id', $row->id)->update(['journal_date' => $row->expense_date]);
                }
            }, 'journal_entries.id', 'id');
    }

    private function recalculateChartBalances(): int
    {
        $updated = 0;

        ChartOfAccount::where('created_by', creatorId())->chunkById(100, function ($accounts) use (&$updated) {
            foreach ($accounts as $account) {
                $totals = DB::table('journal_entry_items')
                    ->join('journal_entries', 'journal_entry_items.journal_entry_id', '=', 'journal_entries.id')
                    ->where('journal_entry_items.account_id', $account->id)
                    ->where('journal_entries.status', 'posted')
                    ->selectRaw('COALESCE(SUM(debit_amount), 0) as debit_total, COALESCE(SUM(credit_amount), 0) as credit_total')
                    ->first();

                $debitTotal = (float) ($totals->debit_total ?? 0);
                $creditTotal = (float) ($totals->credit_total ?? 0);
                $openingBalance = (float) $account->opening_balance;
                $currentBalance = $account->normal_balance === 'debit'
                    ? $openingBalance + $debitTotal - $creditTotal
                    : $openingBalance + $creditTotal - $debitTotal;

                $account->update(['current_balance' => round($currentBalance, 2)]);
                $updated++;
            }
        });

        return $updated;
    }

    private function hasJournal(string $referenceType, $referenceId): bool
    {
        return JournalEntry::where('created_by', creatorId())
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', 'posted')
            ->exists();
    }
}

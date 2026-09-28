<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillCurrencySnapshots extends Command
{
    protected $signature = 'account:backfill-currency-snapshots {--dry-run : Report changes without writing them}';

    protected $description = 'Backfill base-currency snapshots for existing accounting transactions';

    private array $amountColumns = [
        'sales_quotations' => 'total_amount',
        'sales_invoices' => 'total_amount',
        'sales_invoice_returns' => 'total_amount',
        'purchase_orders' => 'total_amount',
        'purchase_invoices' => 'total_amount',
        'purchase_returns' => 'total_amount',
        'customer_payments' => 'payment_amount',
        'vendor_payments' => 'payment_amount',
        'credit_notes' => 'total_amount',
        'debit_notes' => 'total_amount',
        'revenues' => 'amount',
        'expenses' => 'amount',
        'bank_transactions' => 'amount',
        'bank_transfers' => 'transfer_amount',
        'journal_entries' => 'total_debit',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($this->amountColumns as $table => $amountColumn) {
            if (!Schema::hasTable($table) || !Schema::hasColumns($table, ['id', 'created_by', $amountColumn, 'currency_code', 'exchange_rate', 'base_amount'])) {
                continue;
            }

            $query = DB::table($table)->where(function ($builder) {
                $builder->whereNull('currency_code')->orWhereNull('exchange_rate')->orWhereNull('base_amount');
            });
            $count = (clone $query)->count();
            $total += $count;
            $this->line("{$table}: {$count}");

            if ($dryRun || $count === 0) {
                continue;
            }

            $query->orderBy('id')->chunkById(500, function ($rows) use ($table, $amountColumn) {
                foreach ($rows as $row) {
                    $currency = strtoupper((string) (company_setting('defaultCurrency', $row->created_by) ?: 'USD'));
                    DB::table($table)->where('id', $row->id)->update([
                        'currency_code' => $row->currency_code ?: $currency,
                        'exchange_rate' => $row->exchange_rate ?: 1,
                        'base_amount' => $row->base_amount ?? round((float) $row->{$amountColumn} * (float) ($row->exchange_rate ?: 1), 2),
                    ]);
                }
            });
        }

        $this->info(($dryRun ? 'Would backfill ' : 'Backfilled ') . "{$total} transaction snapshots.");

        return self::SUCCESS;
    }
}

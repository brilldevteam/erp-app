<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Workdo\PettyCashManagement\Models\PettyCashExpense;
use Workdo\PettyCashManagement\Models\PettyCashRequest;

/**
 * Shows a petty cash account's journal history in the petty cash section. Journals are only read: each
 * money-in line becomes a top-up (fund) record and each spend line an approved request with its expense,
 * categorised by the account it was spent on. Nothing is posted, so the accounts do not change.
 */
class ImportPettyCashLedger extends Command
{
    protected $signature = 'petty-cash:import-ledger
        {--company= : Company (tenant) user id}
        {--account-code=1005 : Petty cash account code in that company}
        {--prefix=PCJ : Number prefix for imported records}
        {--replace= : pettycash_number of an earlier summary record this import replaces}
        {--apply : Save the records (without it the run is rolled back)}';

    protected $description = 'Bring a petty cash account\'s journal history into the petty cash section (dry run unless --apply)';

    public function handle(): int
    {
        $companyId = (int) $this->option('company');
        $prefix = (string) $this->option('prefix');
        $account = ChartOfAccount::where('created_by', $companyId)->where('account_code', $this->option('account-code'))->first();

        if (!$companyId || !$account) {
            $this->error('Company or petty cash account not found.');
            return self::FAILURE;
        }

        if (PettyCash::where('created_by', $companyId)->where('pettycash_number', 'like', "{$prefix}-%")->exists()) {
            $this->error("Records with prefix {$prefix} already exist for this company; nothing was changed.");
            return self::FAILURE;
        }

        $lines = DB::table('journal_entry_items as i')
            ->join('journal_entries as je', 'je.id', '=', 'i.journal_entry_id')
            ->where('i.account_id', $account->id)
            ->where('je.created_by', $companyId)
            ->select('i.id', 'i.journal_entry_id', 'i.debit_amount', 'i.credit_amount', 'je.journal_date', 'je.description', 'je.reference_type')
            ->orderBy('je.journal_date')
            ->orderByRaw('CASE WHEN i.debit_amount > 0 THEN 0 ELSE 1 END') // same-day top-ups first
            ->orderBy('je.id')
            ->get();

        $ledgerIn = round((float) $lines->sum('debit_amount'), 2);
        $ledgerOut = round((float) $lines->sum('credit_amount'), 2);
        $billAccountIds = ChartOfAccount::billAccountOptions($companyId)->pluck('id')->all();

        $summary = ['funds' => 0, 'requests' => 0, 'categories' => 0, 'split_lines' => 0, 'replaced' => null];

        DB::beginTransaction();
        try {
            if ($replace = $this->option('replace')) {
                $old = PettyCash::where('created_by', $companyId)->where('pettycash_number', $replace)->first();
                if ($old) {
                    if (PettyCashExpense::where('pettycash_id', $old->id)->exists()) {
                        throw new \RuntimeException("{$replace} has expenses linked to it; it was not replaced.");
                    }
                    $summary['replaced'] = $old->only(['id', 'pettycash_number', 'added_amount', 'total_expense', 'closing_balance']);
                    $old->delete();
                }
            }

            $balance = 0.0;
            $fund = null;
            $fundNo = 0;
            $requestNo = 0;
            $categories = [];

            foreach ($lines as $line) {
                $date = substr((string) $line->journal_date, 0, 10);
                $text = trim(preg_replace('/\s+/', ' ', (string) $line->description));
                $tag = " [JE {$line->journal_entry_id}]";

                if ((float) $line->debit_amount > 0) {
                    $amount = round((float) $line->debit_amount, 2);
                    $fund = new PettyCash();
                    $fund->forceFill([
                        'pettycash_number' => sprintf('%s-%03d', $prefix, ++$fundNo),
                        'date' => $date,
                        'opening_balance' => $balance,
                        'added_amount' => $amount,
                        'total_balance' => round($balance + $amount, 2),
                        'total_expense' => 0,
                        'closing_balance' => round($balance + $amount, 2),
                        'status' => 1,
                        'remarks' => \Str::limit($text, 200) . $tag,
                        'creator_id' => $companyId,
                        'created_by' => $companyId,
                    ])->save();
                    $balance = round($balance + $amount, 2);
                    $summary['funds']++;
                    continue;
                }

                if (!$fund) {
                    throw new \RuntimeException("Spending on {$date} comes before the first top-up.");
                }

                // Split a spend paid for several expenses into one line per expense account.
                $parts = DB::table('journal_entry_items')
                    ->where('journal_entry_id', $line->journal_entry_id)
                    ->where('id', '<>', $line->id)
                    ->where('debit_amount', '>', 0)
                    ->orderByDesc('debit_amount')->orderBy('id')
                    ->get(['account_id', 'debit_amount']);
                $credit = round((float) $line->credit_amount, 2);
                if ($parts->isEmpty() || abs(round((float) $parts->sum('debit_amount'), 2) - $credit) > 0.009) {
                    $parts = collect([(object) ['account_id' => $parts->first()?->account_id, 'debit_amount' => $credit]]);
                } elseif ($parts->count() > 1) {
                    $summary['split_lines'] += $parts->count();
                }

                foreach ($parts as $part) {
                    $category = $categories[$part->account_id ?? 0] ??= $this->category($companyId, $part->account_id, $billAccountIds, $summary);
                    $amount = round((float) $part->debit_amount, 2);

                    $request = new PettyCashRequest();
                    $request->forceFill([
                        'request_number' => sprintf('%s-R-%04d', $prefix, ++$requestNo),
                        'user_id' => null,
                        'categorie_id' => $category->id,
                        'requested_amount' => $amount,
                        'status' => '1',
                        'remarks' => \Str::limit($text, 200) . $tag,
                        'approved_at' => $date,
                        'approved_by' => null,
                        'approved_amount' => $amount,
                        'creator_id' => $companyId,
                        'created_by' => $companyId,
                    ])->save();

                    // Saved directly: the posting listener only runs on CreatePettyCashExpense, which is not dispatched.
                    $expense = new PettyCashExpense();
                    $expense->forceFill([
                        'pettycash_id' => $fund->id,
                        'request_id' => $request->id,
                        'type' => 'pettycash',
                        'amount' => $amount,
                        'remarks' => \Str::limit($text, 200) . $tag,
                        'status' => 1,
                        'approved_at' => $date,
                        'approved_by' => null,
                        'creator_id' => $companyId,
                        'created_by' => $companyId,
                    ])->save();

                    $fund->total_expense = round((float) $fund->total_expense + $amount, 2);
                    $fund->closing_balance = round((float) $fund->closing_balance - $amount, 2);
                    $balance = round($balance - $amount, 2);
                    $summary['requests']++;
                }
                $fund->save();
            }

            $funds = PettyCash::where('created_by', $companyId)->where('pettycash_number', 'like', "{$prefix}-%");
            $added = round((float) (clone $funds)->sum('added_amount'), 2);
            $spent = round((float) (clone $funds)->sum('total_expense'), 2);
            $closing = (float) (clone $funds)->orderByDesc('date')->orderByDesc('id')->value('closing_balance');
            $journalsAfter = DB::table('journal_entry_items')->where('account_id', $account->id)->count();

            $this->table(['Check', 'Journals (ledger)', 'Petty cash section', 'Match'], [
                ['Money in', number_format($ledgerIn, 2), number_format($added, 2), $ledgerIn === $added ? 'yes' : 'NO'],
                ['Money out', number_format($ledgerOut, 2), number_format($spent, 2), $ledgerOut === $spent ? 'yes' : 'NO'],
                ['Balance', number_format($ledgerIn - $ledgerOut, 2), number_format($closing, 2), round($ledgerIn - $ledgerOut, 2) === round($closing, 2) ? 'yes' : 'NO'],
                ['Journal lines on account', $lines->count(), $journalsAfter, $lines->count() === $journalsAfter ? 'unchanged' : 'CHANGED'],
            ]);
            $this->line("Account: {$account->account_code} {$account->account_name} | top-ups: {$summary['funds']} | spend lines: {$summary['requests']} (of which split: {$summary['split_lines']}) | new categories: {$summary['categories']}");
            if ($summary['replaced']) {
                $this->line('Replaced summary record: ' . json_encode($summary['replaced']));
            }

            $ok = $ledgerIn === $added && $ledgerOut === $spent && $lines->count() === $journalsAfter
                && round($ledgerIn - $ledgerOut, 2) === round($closing, 2);

            if (!$ok) {
                throw new \RuntimeException('Totals do not match the journals; nothing was saved.');
            }

            if ($this->option('apply')) {
                DB::commit();
                $this->info('Saved.');
            } else {
                DB::rollBack();
                $this->warn('Dry run: nothing was saved. Run again with --apply to save.');
            }
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** Category named after the account the money was spent on; linked to it when it is an expense account. */
    private function category(int $companyId, ?int $accountId, array $billAccountIds, array &$summary): PettyCashCategory
    {
        $account = $accountId ? ChartOfAccount::find($accountId) : null;
        $name = $account?->account_name ?? 'Other';

        $category = PettyCashCategory::where('created_by', $companyId)->where('name', $name)->first();
        if (!$category) {
            $category = PettyCashCategory::create([
                'name' => $name,
                'account_id' => in_array((int) $accountId, array_map('intval', $billAccountIds), true) ? $accountId : null,
                'creator_id' => $companyId,
                'created_by' => $companyId,
            ]);
            $summary['categories']++;
        }

        return $category;
    }
}

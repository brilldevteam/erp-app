<?php

namespace App\Services\ZohoMigration;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\CreditNote;
use Workdo\Account\Models\DebitNote;

/**
 * Final reconciliation: compares ERP with Zoho's own reports and changes nothing.
 *
 * - Trial Balance: every account's ERP balance on the report date against Zoho, account by account (Zoho's
 *   Accounts Receivable / Payable against the ERP control accounts 1100 / 2000, Zoho's Bad Debt against 5700, as
 *   the imports post them). An "As of" report shows income and expenses for the current year only, with earlier
 *   years closed into Retained Earnings, so ERP is folded the same way (financial year from 1 January). A
 *   "From … To …" report must start at or before the first transaction; it is then compared as running totals.
 *   ERP accounts with a balance that Zoho does not list are reported too.
 * - AR / AP Aging Summary (optional): each customer's and vendor's open balance — open invoices or bills less
 *   open credit notes or vendor credits — against the aging total.
 */
class ZohoReconciliationService
{
    private const PROFIT_AND_LOSS_TYPES = ['income', 'other income', 'expense', 'other expense', 'cost of goods sold'];

    /** @var array<int, array> */
    private array $planCache = [];

    public function __construct(private readonly ZohoProfileCatalog $profiles)
    {
    }

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] ??= is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);

        if (array_key_exists('account code', $source)) {
            $name = $get('account');
            $hasAmounts = $get('net debit') !== '' || $get('net credit') !== '' || $get('closing balance') !== '';

            return [
                'kind' => 'trial_balance',
                // Section headings and "Total for …" subtotals (which repeat their sub-accounts) are not accounts.
                'include' => $name !== '' && $hasAmounts && !str_starts_with(mb_strtolower($name), 'total for'),
                'name' => $name,
                'code' => $get('account code'),
                // Debit-positive: the closing balance of a "From … To …" report, or net debit less net credit.
                'balance' => $get('closing balance') !== '' ? $amount('closing balance') : round($amount('net debit') - $amount('net credit'), 2),
            ];
        }

        $partner = array_key_exists('vendor name', $source) ? 'vendor' : 'customer';

        return [
            'kind' => $partner === 'vendor' ? 'ap_aging' : 'ar_aging',
            'include' => $get($partner.' name') !== '',
            'name' => $get($partner.' name'),
            'code' => '',
            'balance' => $amount('total'),
        ];
    }

    public function context(iterable $preparedRows): array
    {
        $rows = [];
        // Keys are "<file id>:<row>" when the stage has several files.
        foreach ($preparedRows as $key => $row) {
            $rows[] = ['row' => (int) (str_contains((string) $key, ':') ? substr((string) $key, strrpos((string) $key, ':') + 1) : $key)] + $row;
        }

        return ['rows' => $rows, 'first' => $rows ? [$rows[0]['kind'], $rows[0]['row']] : null];
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $plan = $this->planCache[$batch->id] ??= $this->plan($batch, $context['rows']);
        $issue = fn (string $message, ?string $value) => [
            'type' => 'reconciliation_difference',
            'field' => $value === null ? null : ($row['kind'] === 'trial_balance' ? 'account' : 'contact'),
            'source_value' => $value,
            'message' => $message,
            'resolution_url' => Route::has('account.reports.index') ? route('account.reports.index') : null,
        ];
        $issues = array_map(fn (string $message) => $issue($message, $row['code'] !== '' ? $row['code'].' '.$row['name'] : $row['name']), $plan['row_problems'][$row['kind'].'|'.$sourceRowNumber] ?? []);
        if ($context['first'] === [$row['kind'], $sourceRowNumber]) {
            $issues = array_merge($issues, array_map(fn (string $message) => $issue($message, null), $plan['problems']));
        }

        return $issues;
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'reconciliation')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve every difference before confirming the reconciliation.');
        }
        $rows = $batch->files()->where('stage_key', 'reconciliation')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        $prepared = $rows->map(fn ($row) => ['row' => (int) $row->source_row_number] + $this->prepare($row->original_values, $batch))->all();
        // Re-checked against ERP as it is now: anything posted since validation must still match.
        $plan = $this->plan($batch, $prepared);
        $problems = array_merge($plan['problems'], ...array_values($plan['row_problems']));
        if ($problems) {
            throw new RuntimeException(implode(' ', array_slice(array_unique($problems), 0, 5)).' Upload the reports again to re-check them.');
        }

        return DB::transaction(function () use ($plan, $rows, $batch, $actorId, $stage): array {
            $summary = $plan['summary'];
            foreach ($rows as $row) {
                $row->update(['status' => 'imported']);
            }
            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $summary]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'reconciliation',
                'event_type' => 'stage_imported',
                'message' => "Reconciliation confirmed: all {$summary['accounts']} accounts match Zoho's trial balance as of {$summary['as_of']}"
                    .($summary['customers'] ? ", {$summary['customers']} customer balances match the AR aging" : '')
                    .($summary['vendors'] ? ", {$summary['vendors']} vendor balances match the AP aging" : '').'.',
                'metadata' => $summary,
            ]);

            return $summary;
        });
    }

    private function plan(ZohoMigrationBatch $batch, array $rows): array
    {
        $tenantId = $batch->tenant_id;
        $plan = ['problems' => [], 'row_problems' => [], 'summary' => ['accounts' => 0, 'customers' => 0, 'vendors' => 0, 'as_of' => null]];
        $rowProblem = function (array $row, string $message) use (&$plan): void {
            $plan['row_problems'][$row['kind'].'|'.$row['row']][] = $message;
        };
        $money = fn (float $value) => number_format($value, 2);

        // --- Trial balance ---
        $trialRows = array_values(array_filter($rows, fn (array $row) => $row['kind'] === 'trial_balance'));
        if (!$trialRows) {
            $plan['problems'][] = "Upload Zoho's Trial Balance report; the aging reports alone do not reconcile the books.";
        } else {
            $title = (string) $batch->files()->where('stage_key', 'reconciliation')->where('status', 'staged')->where('dataset_type', 'trial_balance')->value('report_title');
            $period = $this->period($title);
            $firstJournal = DB::table('journal_entries')->where('created_by', $tenantId)->where('status', 'posted')->min('journal_date');
            if (!$period) {
                $plan['problems'][] = "The trial balance has no \"As of\" or \"From … To …\" date in its title row. Upload Zoho's Trial Balance report exactly as exported.";
            } elseif ($period['from'] && $firstJournal && $period['from'] > substr((string) $firstJournal, 0, 10)) {
                $plan['problems'][] = 'The trial balance starts on '.$period['from'].' but ERP has transactions from '.substr((string) $firstJournal, 0, 10).". Export it from the first transaction, or use an \"As of\" trial balance.";
            } else {
                $this->compareTrialBalance($batch, $trialRows, $period, $plan, $rowProblem, $money);
            }
        }

        // --- AR / AP aging ---
        foreach (['ar_aging' => 'customer', 'ap_aging' => 'vendor'] as $kind => $partner) {
            $agingRows = array_values(array_filter($rows, fn (array $row) => $row['kind'] === $kind));
            if (!$agingRows) {
                continue;
            }
            $erp = $this->openBalances($tenantId, $partner);
            $seen = [];
            foreach ($agingRows as $row) {
                $key = mb_strtolower($row['name']);
                $seen[$key] = true;
                $value = round($erp[$key]['balance'] ?? 0.0, 2);
                if (abs($value - $row['balance']) > 0.005) {
                    $rowProblem($row, ucfirst($partner)." {$row['name']}: ERP has ".$money($value).' open, Zoho '.$money($row['balance']).' (difference '.$money($value - $row['balance']).').');
                }
            }
            foreach ($erp as $key => $balance) {
                if (!isset($seen[$key]) && abs($balance['balance']) > 0.005) {
                    $plan['problems'][] = ucfirst($partner)." {$balance['name']} has ".$money($balance['balance'])." open in ERP but is not in Zoho's ".($partner === 'vendor' ? 'AP' : 'AR').' aging.';
                }
            }
            $plan['summary'][$partner === 'vendor' ? 'vendors' : 'customers'] = count($agingRows);
        }

        return $plan;
    }

    private function compareTrialBalance(ZohoMigrationBatch $batch, array $trialRows, array $period, array &$plan, callable $rowProblem, callable $money): void
    {
        $tenantId = $batch->tenant_id;
        $asOf = $period['to'];
        $zohoTypes = $this->zohoTypes($batch);
        $accounts = DB::table('chart_of_accounts as a')
            ->leftJoin('account_types as t', 't.id', '=', 'a.account_type_id')
            ->leftJoin('account_categories as c', 'c.id', '=', 't.category_id')
            ->where('a.created_by', $tenantId)
            ->get(['a.id', 'a.account_code', 'a.account_name', 'c.name as category'])
            ->keyBy('account_code');
        $net = fn (?string $before, bool $inclusive) => DB::table('journal_entry_items as i')
            ->join('journal_entries as j', 'j.id', '=', 'i.journal_entry_id')
            ->where('j.created_by', $tenantId)->where('j.status', 'posted')
            ->when($before, fn ($query) => $query->where('j.journal_date', $inclusive ? '<=' : '<', $before))
            ->groupBy('i.account_id')
            ->selectRaw('i.account_id, ROUND(SUM(i.debit_amount) - SUM(i.credit_amount), 2) net')
            ->pluck('net', 'account_id')->map(fn ($value) => (float) $value)->all();
        $balances = $net($asOf, true);
        $isProfitAndLoss = fn ($account) => in_array($account?->category, ['Revenue', 'Expenses'], true);

        // An "As of" report closes earlier years into Retained Earnings: ERP is folded the same way.
        $retainedCode = null;
        if (!$period['from']) {
            $yearStart = substr($asOf, 0, 4).'-01-01';
            $earlier = $net($yearStart, false);
            $earlierProfit = 0.0;
            foreach ($accounts as $account) {
                if ($isProfitAndLoss($account) || in_array($zohoTypes[$account->account_code] ?? '', self::PROFIT_AND_LOSS_TYPES, true)) {
                    $earlierProfit += $earlier[$account->id] ?? 0.0;
                    $balances[$account->id] = round(($balances[$account->id] ?? 0.0) - ($earlier[$account->id] ?? 0.0), 2);
                }
            }
            foreach ($trialRows as $row) {
                if (mb_strtolower($row['name']) === 'retained earnings') {
                    $retainedCode = $row['code'];
                }
            }
            if ($retainedCode !== null && ($retained = $accounts->get($retainedCode))) {
                $balances[$retained->id] = round(($balances[$retained->id] ?? 0.0) + $earlierProfit, 2);
            } elseif (abs($earlierProfit) > 0.005) {
                $plan['problems'][] = 'ERP has '.$money($earlierProfit)." of income and expenses before {$yearStart} but Zoho's trial balance has no Retained Earnings account to compare them with.";
            }
        }

        // Zoho rows mapped to the ERP accounts the imports post to.
        $byTarget = [];
        foreach ($trialRows as $row) {
            $target = match (true) {
                ($zohoTypes[$row['code']] ?? '') === 'accounts receivable' => ZohoSalesInvoiceService::RECEIVABLE_ACCOUNT_CODE,
                ($zohoTypes[$row['code']] ?? '') === 'accounts payable' => ZohoBillService::PAYABLE_ACCOUNT_CODE,
                $row['code'] === '' => ZohoManualJournalService::SYSTEM_ACCOUNTS[mb_strtolower($row['name'])] ?? null,
                default => $row['code'],
            };
            if ($target === null || !$accounts->has($target)) {
                $rowProblem($row, "{$row['name']} ".($row['code'] !== '' ? "({$row['code']}) " : '')."is in Zoho's trial balance with ".$money($row['balance']).' but has no ERP account.');
                continue;
            }
            $byTarget[$target]['rows'][] = $row;
            $byTarget[$target]['zoho'] = round(($byTarget[$target]['zoho'] ?? 0.0) + $row['balance'], 2);
        }
        foreach ($byTarget as $target => $group) {
            $account = $accounts->get($target);
            $erp = round($balances[$account->id] ?? 0.0, 2);
            if (abs($erp - $group['zoho']) > 0.005) {
                $rowProblem($group['rows'][0], "{$account->account_code} {$account->account_name}: ERP ".$money($erp).', Zoho '.$money($group['zoho'])." on {$asOf} (difference ".$money($erp - $group['zoho']).'; debit balances positive).');
            }
        }
        foreach ($accounts as $code => $account) {
            if (!isset($byTarget[$code]) && abs($balances[$account->id] ?? 0.0) > 0.005) {
                $plan['problems'][] = "{$code} {$account->account_name} has ".$money($balances[$account->id])." in ERP on {$asOf} but is not in Zoho's trial balance.";
            }
        }
        $plan['summary']['accounts'] = count($byTarget);
        $plan['summary']['as_of'] = $asOf;
    }

    /** Open balances per customer or vendor (lower-case name): open invoices or bills less open credits. */
    private function openBalances(int $tenantId, string $partner): array
    {
        [$documents, $credits, $partnerColumn] = $partner === 'vendor'
            ? [PurchaseInvoice::class, DebitNote::class, 'vendor_id']
            : [SalesInvoice::class, CreditNote::class, 'customer_id'];
        $balances = [];
        foreach ([[$documents, 1], [$credits, -1]] as [$model, $sign]) {
            $model::where('created_by', $tenantId)->where('status', '!=', 'draft')->where('balance_amount', '!=', 0)
                ->with($partner === 'vendor' ? 'vendor' : 'customer')->get()
                ->each(function ($document) use (&$balances, $sign, $partner): void {
                    $name = (string) ($partner === 'vendor' ? $document->vendor?->name : $document->customer?->name);
                    $key = mb_strtolower($name);
                    $balances[$key]['name'] = $name;
                    $balances[$key]['balance'] = round(($balances[$key]['balance'] ?? 0.0) + $sign * (float) $document->balance_amount, 2);
                });
        }

        return $balances;
    }

    /** The report period from its title row: "As of 31 Jul 2026" or "From 01 Jan 2021 To 31 Dec 2026". */
    private function period(string $title): ?array
    {
        $date = fn (string $value) => Carbon::createFromFormat('!d M Y', trim($value))->format('Y-m-d');
        try {
            if (preg_match('/As of\s+(\d{1,2}\s+\w{3}\s+\d{4})/i', $title, $match)) {
                return ['from' => null, 'to' => $date($match[1])];
            }
            if (preg_match('/From\s+(\d{1,2}\s+\w{3}\s+\d{4})\s+To\s+(\d{1,2}\s+\w{3}\s+\d{4})/i', $title, $match)) {
                return ['from' => $date($match[1]), 'to' => $date($match[2])];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /** Zoho account code → Zoho account type, from the migration's Chart of Accounts export (step 2). */
    private function zohoTypes(ZohoMigrationBatch $batch): array
    {
        return $batch->files()->where('stage_key', 'chart_of_accounts')->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->get(['original_values']))
            ->mapWithKeys(function ($row) {
                $values = [];
                foreach ((array) $row->original_values as $key => $value) {
                    $values[$this->profiles->normalize($key)] = trim((string) $value);
                }

                return [($values['account code'] ?? '') => mb_strtolower($values['account type'] ?? '')];
            })
            ->filter(fn (string $type, $code) => (string) $code !== '')
            ->all();
    }
}

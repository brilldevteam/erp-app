<?php

namespace Workdo\Account\Services;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\CustomerActivityLog;

class CustomerWorkspaceService
{
    public const TABS = ['overview', 'transactions', 'statement', 'activity', 'details'];

    public function directory(Request $request, int $tenantId): LengthAwarePaginator
    {
        $search = $request->string('search')->trim()->squish()->value();
        $baseCurrency = app(CurrencyConversionService::class)->baseCurrency($tenantId);
        $searchTokens = collect(preg_split('/\s+/u', mb_strtolower($search), -1, PREG_SPLIT_NO_EMPTY))
            ->unique()
            ->take(8)
            ->values();

        $query = Customer::query()
            ->with('user:id,name,email,mobile_no,avatar,is_disable,is_enable_login,preferred_currency_code')
            ->where('customers.created_by', $tenantId)
            ->when(! auth()->user()->can('manage-any-customers'), fn ($query) => $query->where('customers.creator_id', auth()->id()))
            ->when($searchTokens->isNotEmpty(), function ($query) use ($searchTokens) {
                // Match names at word boundaries so "al" does not match "Salazar".
                // Identifier fields retain contains matching for partial codes and numbers.
                foreach ($searchTokens as $token) {
                    $wordPrefix = '(^|[^[:alnum:]])' . preg_quote($token, '/');
                    $identifierLike = '%' . addcslashes($token, '%_\\') . '%';

                    $query->where(function ($query) use ($wordPrefix, $identifierLike) {
                        $query->whereRaw('LOWER(customers.company_name) REGEXP ?', [$wordPrefix])
                            ->orWhereRaw('LOWER(customers.contact_person_name) REGEXP ?', [$wordPrefix])
                            ->orWhereRaw('LOWER(customers.contact_person_email) REGEXP ?', [$wordPrefix])
                            ->orWhere('customers.customer_code', 'like', $identifierLike)
                            ->orWhere('customers.contact_person_mobile', 'like', $identifierLike)
                            ->orWhere('customers.cr_number', 'like', $identifierLike)
                            ->orWhere('customers.tax_number', 'like', $identifierLike);
                    });
                }
            })
            ->when($request->string('currency')->trim()->upper()->value(), function ($query, $currency) use ($baseCurrency) {
                $query->whereHas('user', function ($user) use ($currency, $baseCurrency) {
                    $user->whereRaw('UPPER(preferred_currency_code) = ?', [$currency]);

                    if ($currency === $baseCurrency) {
                        $user->orWhereNull('preferred_currency_code')
                            ->orWhere('preferred_currency_code', '');
                    }
                });
            })
            ->when($request->string('portal')->value() === 'enabled', fn ($query) => $query->whereHas('user', fn ($user) => $user->where('is_enable_login', true)->where('is_disable', false)))
            ->when($request->string('portal')->value() === 'disabled', fn ($query) => $query->whereHas('user', fn ($user) => $user->where(fn ($user) => $user->where('is_enable_login', false)->orWhere('is_disable', true))))
            ->when($request->date('activity_from'), fn ($query, $date) => $query->whereExists(fn ($invoice) => $invoice->selectRaw('1')->from('sales_invoices')
                ->whereColumn('sales_invoices.customer_id', 'customers.user_id')->where('sales_invoices.created_by', $tenantId)->whereDate('sales_invoices.updated_at', '>=', $date)))
            ->select('customers.*')
            ->selectSub(function ($query) use ($tenantId) {
                $query->from('sales_invoices')
                    ->selectRaw('COALESCE(SUM(balance_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0)')
                    ->whereColumn('sales_invoices.customer_id', 'customers.user_id')
                    ->where('sales_invoices.created_by', $tenantId);
            }, 'outstanding_base')
            ->selectSub(function ($query) use ($tenantId) {
                $query->from('sales_invoices')
                    ->selectRaw('COALESCE(SUM(balance_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0)')
                    ->whereColumn('sales_invoices.customer_id', 'customers.user_id')
                    ->where('sales_invoices.created_by', $tenantId)
                    ->where('balance_amount', '>', 0)
                    ->whereDate('due_date', '<', today());
            }, 'overdue_base')
            ->selectSub(function ($query) use ($tenantId) {
                $query->from('sales_invoices')
                    ->selectRaw('MAX(updated_at)')
                    ->whereColumn('sales_invoices.customer_id', 'customers.user_id')
                    ->where('sales_invoices.created_by', $tenantId);
            }, 'last_activity_at');

        $view = $request->string('view', 'all')->value();
        if ($view === 'outstanding') {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('sales_invoices')
                ->whereColumn('sales_invoices.customer_id', 'customers.user_id')->where('created_by', $tenantId)->where('balance_amount', '>', 0));
        } elseif ($view === 'overdue') {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('sales_invoices')
                ->whereColumn('sales_invoices.customer_id', 'customers.user_id')->where('created_by', $tenantId)
                ->where('balance_amount', '>', 0)->whereDate('due_date', '<', today()));
        } elseif ($view === 'portal_disabled') {
            $query->whereHas('user', fn ($q) => $q->where(fn ($q) => $q->where('is_enable_login', false)->orWhere('is_disable', true)));
        } elseif ($view === 'inactive') {
            $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('sales_invoices')
                ->whereColumn('sales_invoices.customer_id', 'customers.user_id')->where('created_by', $tenantId)
                ->where('updated_at', '>=', now()->subDays(90)));
        }

        $sort = $request->string('sort', 'company_name')->value();
        $direction = $request->string('direction', 'asc')->lower()->value() === 'desc' ? 'desc' : 'asc';
        $sortColumn = match ($sort) {
            'outstanding' => 'outstanding_base',
            'overdue' => 'overdue_base',
            'last_activity' => 'last_activity_at',
            'created_at' => 'customers.created_at',
            default => 'customers.company_name',
        };

        if ($search !== '') {
            $escapedSearch = addcslashes(mb_strtolower($search), '%_\\');
            $companyWordPrefix = '(^|[^[:alnum:]])' . preg_quote(mb_strtolower($search), '/');
            $query->orderByRaw(
                'CASE
                    WHEN LOWER(customers.company_name) = ? THEN 0
                    WHEN LOWER(customers.company_name) LIKE ? ESCAPE \'\\\\\' THEN 1
                    WHEN LOWER(customers.company_name) REGEXP ? THEN 2
                    WHEN LOWER(customers.customer_code) = ? THEN 3
                    WHEN LOWER(customers.customer_code) LIKE ? ESCAPE \'\\\\\' THEN 4
                    WHEN LOWER(customers.contact_person_name) LIKE ? ESCAPE \'\\\\\' THEN 5
                    ELSE 6
                END',
                [$search, $escapedSearch . '%', $companyWordPrefix, $search, $escapedSearch . '%', $escapedSearch . '%']
            );
        }

        return $query->orderBy($sortColumn, $direction)->paginate(20, ['*'], 'directory_page')->withQueryString();
    }

    public function workspace(Customer $customer, Request $request): array
    {
        $customer->load(['user:id,name,email,mobile_no,avatar,is_disable,is_enable_login,preferred_currency_code', 'attachments.uploader:id,name']);
        $summary = $this->summary($customer);
        $tab = in_array($request->string('tab')->value(), self::TABS, true) ? $request->string('tab')->value() : 'overview';

        return [
            'customer' => $customer,
            'tab' => $tab,
            'summary' => $summary,
            'aging' => $this->aging($customer),
            'currency_breakdown' => $this->currencyBreakdown($customer),
            'revenue_trend' => $this->revenueTrend($customer),
            'attention_items' => $this->attentionItems($customer, $summary),
            'available_actions' => $this->availableActions(),
            'transactions' => $tab === 'transactions' ? $this->transactions($customer, $request) : null,
            'statement' => $tab === 'statement' ? $this->statement($customer, $request) : null,
            'activity' => in_array($tab, ['overview', 'activity'], true) ? $this->activity($customer, $tab === 'overview' ? 8 : 40) : null,
        ];
    }

    public function statementExport(Customer $customer, Request $request): array
    {
        return $this->statement($customer, $request, 100000);
    }

    private function summary(Customer $customer): array
    {
        $invoice = DB::table('sales_invoices')->where('created_by', $customer->created_by)->where('customer_id', $customer->user_id)
            ->selectRaw('COALESCE(SUM(total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) invoiced')
            ->selectRaw('COALESCE(SUM(paid_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) paid')
            ->selectRaw('COALESCE(SUM(balance_amount * COALESCE(NULLIF(exchange_rate, 0), 1)), 0) outstanding')
            ->selectRaw('COALESCE(SUM(CASE WHEN due_date < ? AND balance_amount > 0 THEN balance_amount * COALESCE(NULLIF(exchange_rate, 0), 1) ELSE 0 END), 0) overdue', [today()->toDateString()])
            ->selectRaw('MAX(updated_at) last_activity')->first();
        $credits = Schema::hasTable('credit_notes') ? (float) DB::table('credit_notes')->where('created_by', $customer->created_by)
            ->where('customer_id', $customer->user_id)->sum(DB::raw('balance_amount * COALESCE(NULLIF(exchange_rate, 0), 1)')) : 0;
        $returns = Schema::hasTable('sales_invoice_returns') ? (float) DB::table('sales_invoice_returns')->where('created_by', $customer->created_by)
            ->where('customer_id', $customer->user_id)->sum(DB::raw('total_amount * COALESCE(NULLIF(exchange_rate, 0), 1)')) : 0;
        $paidInvoices = DB::table('sales_invoices')->where('created_by', $customer->created_by)->where('customer_id', $customer->user_id)
            ->where('balance_amount', '<=', 0)->whereNotNull('invoice_date')->get(['invoice_date', 'updated_at']);
        $averagePaymentDays = $paidInvoices->isEmpty() ? null : round($paidInvoices->avg(
            fn ($paidInvoice) => Carbon::parse($paidInvoice->invoice_date)->diffInDays(Carbon::parse($paidInvoice->updated_at))
        ), 1);

        return [
            'base_currency' => $this->baseCurrency($customer),
            'total_invoiced' => round((float) $invoice->invoiced, 2),
            'total_paid' => round((float) $invoice->paid, 2),
            'outstanding' => round((float) $invoice->outstanding, 2),
            'overdue' => round((float) $invoice->overdue, 2),
            'unused_credits' => round($credits, 2),
            'total_returns' => round($returns, 2),
            'average_payment_days' => $averagePaymentDays,
            'last_activity_at' => $invoice->last_activity ?: $customer->updated_at?->toISOString(),
        ];
    }

    private function aging(Customer $customer): array
    {
        $rows = DB::table('sales_invoices')->where('created_by', $customer->created_by)->where('customer_id', $customer->user_id)
            ->where('balance_amount', '>', 0)->get(['due_date', 'balance_amount', 'exchange_rate']);
        $aging = ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'over_90' => 0];
        foreach ($rows as $row) {
            $days = Carbon::parse($row->due_date)->diffInDays(today(), false);
            $key = $days <= 0 ? 'current' : ($days <= 30 ? 'days_1_30' : ($days <= 60 ? 'days_31_60' : ($days <= 90 ? 'days_61_90' : 'over_90')));
            $aging[$key] += (float) $row->balance_amount * ((float) $row->exchange_rate ?: 1);
        }
        return array_map(fn ($value) => round($value, 2), $aging);
    }

    private function currencyBreakdown(Customer $customer): array
    {
        $baseCurrency = $this->baseCurrency($customer);
        $outstanding = collect();
        DB::table('sales_invoices')->where('created_by', $customer->created_by)->where('customer_id', $customer->user_id)
            ->where('balance_amount', '>', 0)->get($this->currencyColumns('sales_invoices', ['balance_amount']))
            ->each(function ($invoice) use ($outstanding, $baseCurrency) {
                $currency = $this->rowCurrency($invoice, $baseCurrency);
                $outstanding->put($currency, (float) ($outstanding->get($currency, 0)) + (float) $invoice->balance_amount);
            });
        $credits = collect();
        if (Schema::hasTable('credit_notes')) {
            DB::table('credit_notes')->where('created_by', $customer->created_by)->where('customer_id', $customer->user_id)
                ->where('balance_amount', '>', 0)->get($this->currencyColumns('credit_notes', ['balance_amount']))
                ->each(function ($credit) use ($credits, $baseCurrency) {
                    $currency = $this->rowCurrency($credit, $baseCurrency);
                    $credits->put($currency, (float) ($credits->get($currency, 0)) + (float) $credit->balance_amount);
                });
        }

        return $outstanding->keys()->merge($credits->keys())->unique()->sort()->values()->map(fn ($currency) => [
            'currency' => $currency,
            'outstanding' => round((float) ($outstanding[$currency] ?? 0), 2),
            'credits' => round((float) ($credits[$currency] ?? 0), 2),
        ])->all();
    }

    private function revenueTrend(Customer $customer): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $totals = DB::table('sales_invoices')->where('created_by', $customer->created_by)->where('customer_id', $customer->user_id)
            ->whereDate('invoice_date', '>=', $start)->get(['invoice_date', 'total_amount', 'exchange_rate'])
            ->groupBy(fn ($invoice) => Carbon::parse($invoice->invoice_date)->format('Y-m'))
            ->map(fn ($invoices) => $invoices->sum(fn ($invoice) => (float) $invoice->total_amount * ((float) $invoice->exchange_rate ?: 1)));
        return collect(range(0, 5))->map(function ($offset) use ($start, $totals) {
            $date = $start->copy()->addMonths($offset);
            return ['month' => $date->format('M Y'), 'value' => round((float) ($totals[$date->format('Y-m')] ?? 0), 2)];
        })->all();
    }

    private function attentionItems(Customer $customer, array $summary): array
    {
        $items = [];
        if ($summary['overdue'] > 0) $items[] = ['tone' => 'danger', 'title' => __('Overdue receivables'), 'detail' => __('Follow up on overdue invoices.')];
        if (!$customer->billing_address) $items[] = ['tone' => 'warning', 'title' => __('Billing address missing'), 'detail' => __('Add an address before issuing documents.')];
        if (!$customer->payment_terms) $items[] = ['tone' => 'warning', 'title' => __('Payment terms missing'), 'detail' => __('Set default terms for future invoices.')];
        if (!$customer->user?->is_enable_login || $customer->user?->is_disable) $items[] = ['tone' => 'neutral', 'title' => __('Portal access disabled'), 'detail' => __('Enable access when the customer is ready.')];
        if ($summary['unused_credits'] > 0) $items[] = ['tone' => 'info', 'title' => __('Unused credit available'), 'detail' => __('Apply available credit to an open invoice.')];
        if ($summary['last_activity_at'] && Carbon::parse($summary['last_activity_at'])->lt(now()->subDays(90))) $items[] = ['tone' => 'neutral', 'title' => __('No recent activity'), 'detail' => __('This customer has been inactive for more than 90 days.')];
        return $items;
    }

    private function availableActions(): array
    {
        $user = auth()->user();
        return [
            'edit' => $user->can('edit-customers'),
            'quotation' => module_is_active('Quotation') && $user->can('create-quotations'),
            'invoice' => $user->can('create-sales-invoices'),
            'payment' => $user->can('create-customer-payments'),
            'credit_note' => $user->can('create-credit-notes'),
            'retainer' => module_is_active('Retainer') && $user->can('create-retainer'),
            'statement' => $user->can('view-customer-detail-report'),
        ];
    }

    private function transactionQueries(Customer $customer): array
    {
        $queries = [];
        $tenant = $customer->created_by;
        $userId = $customer->user_id;
        $baseCurrency = $this->baseCurrency($customer);
        $queries[] = DB::table('sales_invoices')->where('created_by', $tenant)->where('customer_id', $userId)
            ->selectRaw("id, 'invoice' type, invoice_number number, invoice_date date, due_date, status, {$this->currencyExpression('sales_invoices')} currency, total_amount total, balance_amount balance, total_amount * COALESCE(NULLIF(exchange_rate, 0), 1) base_total, created_at", [$baseCurrency]);
        if (Schema::hasTable('sales_quotations')) $queries[] = DB::table('sales_quotations')->where('created_by', $tenant)->where('customer_id', $userId)
            ->selectRaw("id, 'quotation' type, quotation_number number, quotation_date date, due_date, status, {$this->currencyExpression('sales_quotations')} currency, total_amount total, 0 balance, total_amount * COALESCE(NULLIF(exchange_rate, 0), 1) base_total, created_at", [$baseCurrency]);
        if (Schema::hasTable('customer_payments')) $queries[] = DB::table('customer_payments')->where('created_by', $tenant)->where('customer_id', $userId)
            ->selectRaw("id, 'payment' type, payment_number number, payment_date date, NULL due_date, status, {$this->currencyExpression('customer_payments')} currency, payment_amount total, 0 balance, payment_amount * COALESCE(NULLIF(exchange_rate, 0), 1) base_total, created_at", [$baseCurrency]);
        if (Schema::hasTable('credit_notes')) $queries[] = DB::table('credit_notes')->where('created_by', $tenant)->where('customer_id', $userId)
            ->selectRaw("id, 'credit_note' type, credit_note_number number, credit_note_date date, NULL due_date, status, {$this->currencyExpression('credit_notes')} currency, total_amount total, balance_amount balance, total_amount * COALESCE(NULLIF(exchange_rate, 0), 1) base_total, created_at", [$baseCurrency]);
        if (Schema::hasTable('sales_invoice_returns')) $queries[] = DB::table('sales_invoice_returns')->where('created_by', $tenant)->where('customer_id', $userId)
            ->selectRaw("id, 'return' type, return_number number, return_date date, NULL due_date, status, {$this->currencyExpression('sales_invoice_returns')} currency, total_amount total, 0 balance, total_amount * COALESCE(NULLIF(exchange_rate, 0), 1) base_total, created_at", [$baseCurrency]);
        if (Schema::hasTable('sales_retainers')) $queries[] = DB::table('sales_retainers')->where('created_by', $tenant)->where('customer_id', $userId)
            ->selectRaw("id, 'retainer' type, retainer_number number, retainer_date date, due_date, status, {$this->currencyExpression('sales_retainers')} currency, total_amount total, balance_amount balance, total_amount base_total, created_at", [$baseCurrency]);
        return $queries;
    }

    private function baseCurrency(Customer $customer): string
    {
        return app(CurrencyConversionService::class)->baseCurrency($customer->created_by);
    }

    private function currencyExpression(string $table): string
    {
        $columns = [];
        if (Schema::hasColumn($table, 'currency_code')) $columns[] = "NULLIF(currency_code, '')";
        if (Schema::hasColumn($table, 'base_currency_code')) $columns[] = "NULLIF(base_currency_code, '')";
        $columns[] = '?';

        return 'COALESCE('.implode(', ', $columns).')';
    }

    private function currencyColumns(string $table, array $columns): array
    {
        if (Schema::hasColumn($table, 'currency_code')) $columns[] = 'currency_code';
        if (Schema::hasColumn($table, 'base_currency_code')) $columns[] = 'base_currency_code';

        return $columns;
    }

    private function rowCurrency(object $row, string $baseCurrency): string
    {
        return strtoupper((string) (($row->currency_code ?? null) ?: (($row->base_currency_code ?? null) ?: $baseCurrency)));
    }

    private function transactionUnion(Customer $customer): Builder
    {
        $queries = $this->transactionQueries($customer);
        $union = array_shift($queries);
        foreach ($queries as $query) $union->unionAll($query);
        return DB::query()->fromSub($union, 'customer_transactions');
    }

    private function transactions(Customer $customer, Request $request): LengthAwarePaginator
    {
        $query = $this->transactionUnion($customer)
            ->when($request->string('type')->value(), fn ($q, $type) => $q->where('type', $type))
            ->when($request->string('status')->value(), fn ($q, $status) => $q->where('status', $status))
            ->when($request->date('start_date'), fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($request->date('end_date'), fn ($q, $date) => $q->whereDate('date', '<=', $date));
        return $query->orderByDesc('date')->orderByDesc('id')->paginate(25, ['*'], 'transaction_page')->withQueryString();
    }

    private function statement(Customer $customer, Request $request, int $perPage = 25): array
    {
        $start = $request->date('start_date');
        $end = $request->date('end_date');
        $openingBalance = $start ? (float) $this->transactionUnion($customer)->whereIn('type', ['invoice', 'payment', 'credit_note', 'return'])
            ->whereDate('date', '<', $start)->get()->sum(fn ($row) => $row->type === 'invoice' ? (float) $row->base_total : -(float) $row->base_total) : 0;
        $rows = $this->transactionUnion($customer)->whereIn('type', ['invoice', 'payment', 'credit_note', 'return'])
            ->when($start, fn ($q) => $q->whereDate('date', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('date', '<=', $end))->orderBy('date')->orderBy('id')->get();
        $balance = $openingBalance;
        $entries = $rows->map(function ($row) use (&$balance) {
            $debit = $row->type === 'invoice' ? (float) $row->base_total : 0;
            $credit = $row->type === 'invoice' ? 0 : (float) $row->base_total;
            $balance += $debit - $credit;
            return [...(array) $row, 'debit' => $debit, 'credit' => $credit, 'running_balance' => round($balance, 2)];
        });
        $page = max(1, (int) request('statement_page', 1));
        return [
            'data' => $entries->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($entries->count() / $perPage)),
            'total' => $entries->count(),
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($balance, 2),
        ];
    }

    private function activity(Customer $customer, int $limit): array
    {
        $logs = CustomerActivityLog::with('actor:id,name')->where('created_by', $customer->created_by)->where('customer_id', $customer->id)
            ->latest('occurred_at')->limit($limit)->get()->map(fn ($log) => [
                'id' => 'log-' . $log->id, 'type' => $log->event_type, 'title' => $log->title,
                'detail' => $log->actor?->name, 'occurred_at' => $log->occurred_at?->toISOString(),
            ]);
        $documents = $this->transactionUnion($customer)->latest('created_at')->limit($limit)->get()->map(fn ($row) => [
            'id' => $row->type . '-' . $row->id, 'type' => 'document.' . $row->type,
            'title' => ucfirst(str_replace('_', ' ', $row->type)) . ' ' . $row->number,
            'detail' => ucfirst((string) $row->status), 'occurred_at' => $row->created_at,
        ]);
        return $logs->concat($documents)->sortByDesc('occurred_at')->take($limit)->values()->all();
    }
}

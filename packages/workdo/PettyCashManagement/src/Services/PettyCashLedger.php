<?php

namespace Workdo\PettyCashManagement\Services;

use Illuminate\Support\Collection;
use Workdo\PettyCashManagement\Models\PettyCash;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Workdo\PettyCashManagement\Models\PettyCashExpense;

class PettyCashLedger
{
    /**
     * The fund spending is taken from: the newest by date. Funds created at the same moment (for example by
     * an import) made the previous created_at ordering pick an arbitrary one.
     */
    public static function currentFund(?int $tenantId = null): ?PettyCash
    {
        return PettyCash::where('created_by', $tenantId ?? creatorId())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();
    }

    public static function accountingActive(): bool
    {
        return class_exists(\Workdo\Account\Services\JournalService::class) && Module_is_active('Account');
    }

    /** An approval cannot post without an expense account on its category. */
    public static function missingAccountMessage(?PettyCashCategory $category): ?string
    {
        if (!self::accountingActive() || $category?->account_id) {
            return null;
        }

        return __('Set an expense account on the petty cash category :category before approving.', [
            'category' => $category?->name ?? '-',
        ]);
    }

    /**
     * Undo approved spending when its request or reimbursement is deleted: remove the journal,
     * give the amount back to the fund and delete the expense row.
     */
    public static function reverse(Collection $expenses): void
    {
        foreach ($expenses as $expense) {
            /** @var PettyCashExpense $expense */
            if (self::accountingActive()) {
                app(\Workdo\Account\Services\JournalService::class)->deletePettyCashExpenseJournal($expense->id);
            }

            if ($fund = $expense->pettyCash) {
                $fund->closing_balance += (float) $expense->amount;
                $fund->total_expense = max(0, (float) $fund->total_expense - (float) $expense->amount);
                $fund->save();
            }

            $expense->delete();
        }
    }
}

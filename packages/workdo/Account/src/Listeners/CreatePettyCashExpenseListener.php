<?php

namespace Workdo\Account\Listeners;

use Illuminate\Support\Str;
use Workdo\Account\Services\JournalService;
use Workdo\PettyCashManagement\Events\CreatePettyCashExpense;

/**
 * Posts approved petty cash spending to the accounts: the category's expense account is debited and
 * petty cash credited. Runs inside the approval transaction, so a failure here cancels the approval.
 */
class CreatePettyCashExpenseListener
{
    public function __construct(private readonly JournalService $journalService)
    {
    }

    public function handle(CreatePettyCashExpense $event): void
    {
        if (!Module_is_active('Account')) {
            return;
        }

        $expense = $event->expense->loadMissing(['request.category', 'request.user', 'reimbursement.category', 'reimbursement.user']);
        $source = $expense->request ?: $expense->reimbursement;
        $category = $source?->category;

        if (!$category?->account_id) {
            throw new \RuntimeException(__('Set an expense account on the petty cash category :category before approving.', [
                'category' => $category?->name ?? '-',
            ]));
        }

        $date = $expense->request?->approved_at ?? $expense->reimbursement?->approved_date ?? now();
        $number = $expense->request?->request_number ?? $expense->reimbursement?->reimbursement_number;
        $description = Str::limit(trim(implode(' - ', array_filter([
            'Petty cash',
            $category->name,
            $source?->user?->name,
            $number,
            $expense->remarks,
        ]))), 250);

        $this->journalService->pettyCashExpenseJournal($expense, (int) $category->account_id, $date, $description);
    }
}

<?php

namespace App\Services\ZohoMigration\Concerns;

use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\BankTransaction;
use Workdo\Account\Models\ChartOfAccount;

/**
 * Matches the Zoho account money moved through (Deposit To / Paid Through) to an ERP bank account, and records
 * bank movements the way the ERP's BankTransactionsService does for cleared transactions.
 *
 * Rows passed in carry 'account_code', 'account_name' and 'currency_code'.
 */
trait ResolvesZohoBankAccounts
{
    /** The ERP bank account for a Zoho account: by Zoho code, ledger code, then name. */
    protected function bankAccount(array $row, int $tenantId): ?BankAccount
    {
        $banks = BankAccount::where('created_by', $tenantId)->whereNotNull('gl_account_id');
        if ($row['account_code'] !== '') {
            $bank = (clone $banks)->where('zoho_gl_code', $row['account_code'])->first()
                ?? (clone $banks)->whereHas('glAccount', fn ($query) => $query->where('account_code', $row['account_code']))->first();
            if ($bank) {
                return $bank;
            }
        }

        return $row['account_name'] !== '' ? (clone $banks)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($row['account_name'])])->first() : null;
    }

    protected function ledgerAccount(array $row, int $tenantId): ?ChartOfAccount
    {
        $query = ChartOfAccount::where('created_by', $tenantId);

        return ($row['account_code'] !== '' ? (clone $query)->where('account_code', $row['account_code'])->first() : null)
            ?? ($row['account_name'] !== '' ? (clone $query)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($row['account_name'])])->first() : null);
    }

    /**
     * For money moved through a non-bank Zoho account (Salary Payable, Projects on Process, …). Same shape as the
     * migration import's clearing accounts: no account number, flagged as internal.
     */
    protected function clearingAccount(array $row, int $tenantId, int $actorId): BankAccount
    {
        $ledger = $this->ledgerAccount($row, $tenantId);

        return BankAccount::create([
            'account_name' => $ledger->account_name,
            'account_number' => null,
            'zoho_gl_code' => $ledger->account_code,
            'bank_name' => 'Internal clearing account (Zoho migration)',
            'account_type' => 'clearing_account',
            'currency_code' => $row['currency_code'],
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'gl_account_id' => $ledger->id,
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);
    }

    /** The bank register line and balance change the ERP makes for a cleared transaction. */
    protected function recordBankMovement(BankAccount $bank, string $type, ?string $date, string $reference, string $description, float $amount, int $tenantId): void
    {
        $bank->refresh();
        $bank->current_balance = round((float) $bank->current_balance + ($type === 'credit' ? $amount : -$amount), 2);
        $bank->save();
        BankTransaction::create([
            'bank_account_id' => $bank->id,
            'transaction_date' => $date,
            'transaction_type' => $type,
            'reference_number' => $reference,
            'description' => $description,
            'amount' => $amount,
            'running_balance' => $bank->current_balance,
            'transaction_status' => 'cleared',
            'reconciliation_status' => 'unreconciled',
            'created_by' => $tenantId,
        ]);
    }
}

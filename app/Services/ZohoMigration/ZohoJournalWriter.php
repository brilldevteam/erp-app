<?php

namespace App\Services\ZohoMigration;

use RuntimeException;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;
use Workdo\Account\Models\JournalEntryItem;

/**
 * Writes the posted journal entry for an imported Zoho document, the same way the ERP journal service does
 * (journal + lines + account balances), but without relying on a logged-in user, so it works in the import job.
 *
 * Journal numbers are the importer's own — ZOHO-<company>-<Zoho id>-<yyyymmdd> — so the shared JE-<year>-###
 * sequence is not consumed and numbers never collide with another company's imported entries.
 */
class ZohoJournalWriter
{
    public static function number(int $tenantId, string $zohoId, string $fallbackNumber, ?string $date): string
    {
        $source = $zohoId !== '' ? $zohoId : preg_replace('/[^A-Za-z0-9]+/', '-', $fallbackNumber);

        return "ZOHO-{$tenantId}-{$source}-".str_replace('-', '', (string) $date);
    }

    /**
     * @param  array<int, array{account: ChartOfAccount, debit: float, credit: float, description: string, contact_id?: ?int}>  $lines  Base-currency amounts.
     */
    public function post(array $journal, array $lines, int $actorId, int $tenantId): JournalEntry
    {
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if (abs($debit - $credit) > 0.001) {
            throw new RuntimeException("Journal {$journal['journal_number']} is not balanced: debit {$debit} != credit {$credit}.");
        }

        $entry = JournalEntry::create($journal + [
            'entry_type' => 'automatic',
            'total_debit' => $debit,
            'total_credit' => $credit,
            'status' => 'posted',
            'base_amount' => $debit,
            'base_currency_code' => strtoupper((string) (company_setting('defaultCurrency', $tenantId) ?: 'USD')),
            'creator_id' => $actorId,
            'created_by' => $tenantId,
        ]);

        foreach ($lines as $line) {
            JournalEntryItem::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line['account']->id,
                'contact_id' => $line['contact_id'] ?? null,
                'description' => $line['description'],
                'debit_amount' => $line['debit'],
                'credit_amount' => $line['credit'],
                'creator_id' => $actorId,
                'created_by' => $tenantId,
            ]);
            // Same balance rule as the ERP journal service. Re-read first: a journal can post one account on several
            // lines, and a stale copy would overwrite the earlier line's change.
            $account = $line['account']->refresh();
            $account->current_balance += $account->normal_balance === 'debit' ? $line['debit'] - $line['credit'] : $line['credit'] - $line['debit'];
            $account->save();
        }

        return $entry;
    }

    /**
     * Converts per-account document amounts to the base currency, putting any rounding difference on the largest
     * amount so the converted parts add up exactly to the converted document total.
     *
     * @param  array<int, float>  $amountsByAccount  Account id => document-currency amount, largest first.
     * @return array<int, float>
     */
    public static function toBase(array $amountsByAccount, float $rate, float $documentTotal): array
    {
        $total = round($documentTotal * $rate, 2);
        $converted = array_map(fn (float $amount) => round($amount * $rate, 2), $amountsByAccount);
        $first = array_key_first($converted);
        $converted[$first] = round($converted[$first] + $total - array_sum($converted), 2);

        return $converted;
    }
}

<?php

namespace App\Services\ZohoMigration;

use App\Models\User;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\ZohoMigration\Concerns\ReadsZohoDocuments;
use App\Services\ZohoMigration\Concerns\ResolvesZohoBankAccounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;
use Workdo\Account\Models\JournalEntry;

/**
 * Shared import for Zoho payments — customer payments (received) and vendor payments (made). One row per
 * payment application; a payment without a document row is an unapplied payment.
 *
 * Each payment is created cleared, allocated to its invoices/bills exactly as Zoho applied it (paid amount,
 * balance and status updated the way the ERP payment screens do), and posted with its own balanced journal:
 * the bank's ledger account against the receivable/payable control account, with any unapplied vendor amount on
 * the Zoho advances account, as Zoho posts it. Payments made or received through a non-bank Zoho account use an
 * internal clearing bank account linked to that ledger account, as the earlier migration import does.
 */
abstract class ZohoPaymentService
{
    use ReadsZohoDocuments;
    use ResolvesZohoBankAccounts;

    public function __construct(
        protected readonly ZohoProfileCatalog $profiles,
        protected readonly ZohoJournalWriter $journals,
    ) {
    }

    abstract protected function config(): array;

    abstract protected function partnerUser(string $name, int $tenantId): ?User;

    /** The partner's invoice or bill with this Zoho number. */
    abstract protected function document(string $number, int $partnerId, int $tenantId): ?Model;

    abstract protected function existing(string $number, int $partnerId, int $tenantId): ?Model;

    /** Creates the payment and its allocations (updating each document); returns the payment. */
    abstract protected function createPayment(array $header, array $allocations, int $partnerId, BankAccount $bank, int $actorId, int $tenantId): Model;

    /** Journal lines in base currency. */
    abstract protected function journalLines(array $header, float $appliedBase, float $unusedBase, ChartOfAccount $bankLedger, int $partnerId, int $tenantId): array;

    public function prepare(array $values, ZohoMigrationBatch $batch): array
    {
        $c = $this->config();
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] ??= is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $amount = fn (string $key): float => round((float) str_replace(',', '', $get($key)), 2);
        $zohoId = '';
        foreach ($c['id'] as $key) {
            if (($zohoId = $get($key)) !== '') {
                break;
            }
        }

        return [
            'zoho_id' => $zohoId,
            'number' => $get('payment number'),
            'date' => $this->date($get('date'), (string) $batch->source_date_format),
            'date_source' => $get('date'),
            'partner' => $get($c['partner']),
            'mode' => $get('mode') ?: null,
            'reference' => $get('reference number') ?: null,
            'description' => $get('description'),
            'zoho_status' => $get('payment status'),
            'currency_code' => strtoupper($get('currency code') ?: (string) $batch->base_currency),
            'exchange_rate' => (float) ($get('exchange rate') ?: 1),
            'amount' => $amount('amount'),
            'unused' => $amount('unused amount'),
            'bank_charges' => $amount('bank charges'),
            'account_name' => $get($c['account']),
            'account_code' => $get($c['account_code']),
            'document_number' => $get($c['document']),
            'applied' => $amount($c['applied']),
        ];
    }

    /** Per-payment facts (first row, applied sum, header) and per-document applied totals. */
    public function context(iterable $preparedRows): array
    {
        $payments = [];
        $documents = [];
        foreach ($preparedRows as $rowNumber => $row) {
            $key = $row['zoho_id'];
            $payments[$key] ??= ['first_row' => $rowNumber, 'applied' => 0.0, 'header' => $this->header($row)];
            $payments[$key]['applied'] = round($payments[$key]['applied'] + $row['applied'], 2);
            if ($row['document_number'] !== '') {
                $documentKey = mb_strtolower($row['partner']).'|'.mb_strtolower($row['document_number']);
                $documents[$documentKey] = round(($documents[$documentKey] ?? 0) + $row['applied'], 2);
            }
        }

        return ['payments' => $payments, 'documents' => $documents];
    }

    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array $context): array
    {
        $c = $this->config();
        $errors = [];
        $payment = $context['payments'][$row['zoho_id']] ?? null;

        if ($row['zoho_id'] === '') {
            $errors[] = "{$c['id_label']} is required.";
        }
        if (!$row['date']) {
            $errors[] = "Date '{$row['date_source']}' is not a valid date.";
        }
        $partner = $row['partner'] !== '' ? $this->partnerUser($row['partner'], $batch->tenant_id) : null;
        if (!$partner) {
            $errors[] = $row['partner'] === ''
                ? "{$c['partner_label']} Name is required."
                : "{$c['partner_label']} '{$row['partner']}' was not found in ERP. Complete the {$c['partner_step']} step first.";
        }
        if (mb_strtolower($row['zoho_status']) !== 'paid') {
            $errors[] = "Payment Status '{$row['zoho_status']}' is not supported; only paid Zoho payments are imported.";
        }
        if ($row['currency_code'] !== strtoupper((string) $batch->base_currency) && $row['exchange_rate'] <= 0) {
            $errors[] = 'Exchange Rate is required for a foreign-currency payment.';
        }
        if (!$this->bankAccount($row, $batch->tenant_id) && !$this->ledgerAccount($row, $batch->tenant_id)) {
            $errors[] = "{$c['account_label']} '{$row['account_name']}' ({$row['account_code']}) is neither a bank account nor a chart account in ERP. Complete the Bank accounts step first.";
        }

        if ($row['document_number'] !== '') {
            $document = $partner ? $this->document($row['document_number'], $partner->id, $batch->tenant_id) : null;
            if ($partner && !$document) {
                $errors[] = "{$c['document_label']} {$row['document_number']} of {$row['partner']} was not found in ERP. Complete the {$c['document_step']} step first.";
            } elseif ($document && $document->status === 'draft') {
                $errors[] = "{$c['document_label']} {$row['document_number']} is a draft in ERP; a payment cannot be applied to it.";
            } elseif ($document) {
                $appliedInFile = $context['documents'][mb_strtolower($row['partner']).'|'.mb_strtolower($row['document_number'])] ?? 0;
                if ($appliedInFile - (float) $document->balance_amount > 0.01) {
                    $errors[] = "Payments in this file apply ".number_format($appliedInFile, 2)." to {$c['document_label']} {$row['document_number']}, more than its open balance of ".number_format((float) $document->balance_amount, 2).'.';
                }
            }
            if ($row['applied'] <= 0) {
                $errors[] = 'The amount applied to '.$row['document_number'].' must be greater than zero.';
            }
        } elseif ($row['applied'] != 0.0) {
            $errors[] = "An amount is applied but no {$c['document_label']} number is given.";
        }

        // Payment-level checks are reported once, on the payment's first row.
        if ($payment && $payment['first_row'] === $sourceRowNumber) {
            if ($row['amount'] <= 0) {
                $errors[] = 'Amount must be greater than zero.';
            }
            if ($row['bank_charges'] != 0.0) {
                $errors[] = 'Payments with Zoho bank charges are not supported by this import yet.';
            }
            if ($row['unused'] > 0 && !$c['allows_unused']) {
                $errors[] = 'This payment has an unused amount of '.number_format($row['unused'], 2)."; unapplied {$c['label_plural']} are not supported by this import yet.";
            }
            if ($row['unused'] > 0 && $c['allows_unused'] && !$this->advanceAccount($batch->tenant_id)) {
                $errors[] = "The advances account '{$c['advance_account']}' was not found in ERP. Complete the Chart of Accounts step first.";
            }
            if (abs($payment['applied'] + $row['unused'] - $row['amount']) > 0.01) {
                $errors[] = 'Applied amounts ('.number_format($payment['applied'], 2).') plus the unused amount ('.number_format($row['unused'], 2).') do not equal the payment amount ('.number_format($row['amount'], 2).').';
            }
            if (!$this->controlAccount($batch->tenant_id)) {
                $errors[] = "Account {$c['control_code']} ({$c['control_name']}) was not found in ERP.";
            }
            $existing = $partner ? $this->existing($row['number'], $partner->id, $batch->tenant_id) : null;
            if ($existing && $batch->duplicate_strategy === 'block') {
                $errors[] = "A {$c['label']} with this number from this {$c['partner_label']} already exists in ERP and this migration is configured to block duplicates.";
            } elseif ($existing && $batch->duplicate_strategy === 'update') {
                $errors[] = "A {$c['label']} with this number already exists in ERP. Imported payments are not updated in place because they are already applied and posted; remove it or use the link duplicate setting.";
            }
            if (!$existing && JournalEntry::where('journal_number', $this->journalNumber($row, $batch->tenant_id))->exists()) {
                $errors[] = 'Journal number '.$this->journalNumber($row, $batch->tenant_id)." is already used; this {$c['label']} may have been imported before.";
            }
        } elseif ($payment && $this->header($row) !== $payment['header']) {
            $errors[] = "This row's payment details (number, date, {$c['partner_label']}, amount or account) differ from row {$payment['first_row']} of the same payment.";
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_'.$c['stage_key'],
            'field' => 'payment_number',
            'source_value' => $row['number'],
            'message' => $message,
            'resolution_url' => Route::has($c['route']) ? route($c['route']) : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $c = $this->config();
        $stage = $batch->stages()->where('stage_key', $c['stage_key'])->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException("Resolve all validation issues before importing {$c['label_plural']}.");
        }

        $rows = $batch->files()->where('stage_key', $c['stage_key'])->where('status', 'staged')->get()
            ->flatMap(fn ($file) => $file->stagedRows()->where('status', 'validated')->orderBy('source_row_number')->get());
        if ($rows->isEmpty()) {
            throw new RuntimeException("No validated {$c['label']} rows are ready to import.");
        }

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $prepared = [];
        foreach ($rows as $row) {
            $prepared[(int) $row->source_row_number] = $this->prepare($row->original_values, $batch);
        }
        $context = $this->context($prepared);
        $payments = [];
        foreach ($rows as $row) {
            $data = $prepared[(int) $row->source_row_number];
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $context)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $payments[$data['zoho_id']][] = [$row, $data];
        }

        // Oldest first, so bank running balances read in date order.
        uasort($payments, fn (array $a, array $b) => [$a[0][1]['date'], $a[0][0]->source_row_number] <=> [$b[0][1]['date'], $b[0][0]->source_row_number]);

        return DB::transaction(function () use ($payments, $batch, $actorId, $stage, $c): array {
            $counts = ['payments' => 0, 'allocations' => 0, 'amount' => 0.0, 'unapplied_amount' => 0.0, 'documents_paid' => 0, 'documents_partial' => 0, 'skipped_existing' => 0, 'clearing_accounts_created' => 0];
            $control = $this->controlAccount($batch->tenant_id);
            $touched = [];

            foreach ($payments as $rows) {
                $header = $rows[0][1];
                $partner = $this->partnerUser($header['partner'], $batch->tenant_id);
                if ($this->existing($header['number'], $partner->id, $batch->tenant_id)) {
                    // Only reachable with the "link" duplicate setting: the existing ERP payment is kept as is.
                    $counts['skipped_existing']++;
                    foreach ($rows as [$row]) {
                        $row->update(['status' => 'skipped']);
                    }
                    continue;
                }

                $bank = $this->bankAccount($header, $batch->tenant_id);
                if (!$bank) {
                    $bank = $this->clearingAccount($header, $batch->tenant_id, $actorId);
                    $counts['clearing_accounts_created']++;
                }
                $allocations = array_values(array_filter(array_column($rows, 1), fn (array $data) => $data['document_number'] !== ''));
                $payment = $this->createPayment($header, $allocations, $partner->id, $bank, $actorId, $batch->tenant_id);
                $this->recordBankMovement($bank, $c['bank_direction'], $header['date'], $header['number'], "{$c['journal_label']} #{$header['number']} - {$header['partner']}", $header['amount'], $batch->tenant_id);
                foreach ($allocations as $allocation) {
                    $touched[$allocation['document_number'].'|'.$partner->id] = $this->document($allocation['document_number'], $partner->id, $batch->tenant_id);
                }

                $rate = (float) $header['exchange_rate'] ?: 1.0;
                $totalBase = round($header['amount'] * $rate, 2);
                $unusedBase = round($header['unused'] * $rate, 2);
                $this->journals->post([
                    'journal_number' => $this->journalNumber($header, $batch->tenant_id),
                    'journal_date' => $header['date'],
                    'reference_type' => $c['reference_type'],
                    'reference_id' => $payment->id,
                    'description' => "{$c['journal_label']} #{$header['number']} (imported from Zoho)",
                    'currency_code' => $header['currency_code'],
                    'exchange_rate' => $rate,
                    'foreign_amount' => $header['amount'],
                ], $this->journalLines($header, round($totalBase - $unusedBase, 2), $unusedBase, $bank->glAccount, $partner->id, $batch->tenant_id), $actorId, $batch->tenant_id);

                foreach ($rows as [$row, $data]) {
                    $row->update(['status' => 'imported', 'normalized_values' => $data]);
                }
                $counts['payments']++;
                $counts['allocations'] += count($allocations);
                $counts['amount'] = round($counts['amount'] + $header['amount'], 2);
                $counts['unapplied_amount'] = round($counts['unapplied_amount'] + $header['unused'], 2);
            }

            foreach ($touched as $document) {
                $document->refresh();
                $counts[$document->status === 'paid' ? 'documents_paid' : 'documents_partial']++;
            }

            $stage->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'summary' => $counts]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => $c['stage_key'],
                'event_type' => 'stage_imported',
                'message' => ucfirst($c['label_plural'])." import completed: {$counts['payments']} payments (".number_format($counts['amount'], 2).") with {$counts['allocations']} allocations; {$counts['documents_paid']} {$c['document_label']}s now paid, {$counts['documents_partial']} partly paid"
                    .($counts['unapplied_amount'] > 0 ? '; '.number_format($counts['unapplied_amount'], 2).' unapplied (advances)' : '')
                    .($counts['clearing_accounts_created'] ? "; {$counts['clearing_accounts_created']} internal clearing account(s) created" : '')
                    .($counts['skipped_existing'] ? "; {$counts['skipped_existing']} already in ERP" : '').'.',
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    /** Applies an allocation to an invoice or bill the way the ERP payment screens do. */
    protected function applyToDocument(Model $document, float $amount): void
    {
        $document->paid_amount = round((float) $document->paid_amount + $amount, 2);
        $document->balance_amount = max(0, round((float) $document->total_amount - (float) $document->paid_amount, 2));
        $document->status = $document->balance_amount <= 0 ? 'paid' : 'partial';
        $document->save();
    }

    protected function controlAccount(int $tenantId): ?ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->where('account_code', $this->config()['control_code'])->first();
    }

    protected function advanceAccount(int $tenantId): ?ChartOfAccount
    {
        return ChartOfAccount::where('created_by', $tenantId)->whereRaw('LOWER(account_name) = ?', [mb_strtolower($this->config()['advance_account'])])->first();
    }

    private function journalNumber(array $row, int $tenantId): string
    {
        return ZohoJournalWriter::number($tenantId, $row['zoho_id'], $this->config()['stage_key'].'-'.$row['number'], $row['date']);
    }

    private function header(array $row): array
    {
        return [$row['number'], $row['date'], mb_strtolower($row['partner']), $row['amount'], $row['unused'], $row['currency_code'], $row['account_code'], $row['account_name']];
    }
}

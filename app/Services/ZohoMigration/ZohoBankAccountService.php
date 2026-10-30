<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Workdo\Account\Events\CreateBankAccount;
use Workdo\Account\Events\UpdateBankAccount;
use Workdo\Account\Models\BankAccount;
use Workdo\Account\Models\ChartOfAccount;

/**
 * Creates ERP bank accounts from the Bank, Cash and Credit Card rows of a Zoho Chart of Accounts export.
 * Zoho rarely records real account numbers, so — as in the migration import — account_number stays empty
 * until finance verifies it, the Zoho account code is kept in zoho_gl_code, and an unknown bank is marked
 * "Pending verification". Each account links to the chart account imported in the Chart of Accounts stage.
 */
class ZohoBankAccountService
{
    private const TYPES = ['bank' => 'checking', 'cash' => 'cash', 'credit card' => 'credit'];

    public function __construct(private readonly ZohoProfileCatalog $profiles)
    {
    }

    public function prepare(array $values, string $baseCurrency): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $zohoType = mb_strtolower($get('account type'));

        return [
            'include' => isset(self::TYPES[$zohoType]),
            'zoho_account_type' => $get('account type'),
            'account_name' => $get('account name'),
            'account_number' => $get('account #') ?: null,
            'zoho_gl_code' => $get('account code') ?: null,
            'bank_name' => $zohoType === 'cash' ? 'Cash' : 'Pending verification',
            'account_type' => self::TYPES[$zohoType] ?? null,
            'currency_code' => strtoupper($get('currency') ?: $baseCurrency),
            'is_active' => mb_strtolower($get('account status') ?: 'active') === 'active',
        ];
    }

    /**
     * @param  array<string, int>  $seen  Account names already seen in this pass, with their row numbers.
     */
    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array &$seen): array
    {
        $errors = [];
        $name = (string) $row['account_name'];
        $key = mb_strtolower($name);
        if ($name === '') {
            $errors[] = 'Account Name is required.';
        } elseif (isset($seen[$key])) {
            $errors[] = "Account Name appears more than once in this file (rows {$seen[$key]} and {$sourceRowNumber}).";
        } else {
            $seen[$key] = $sourceRowNumber;
        }
        if (!$this->chartAccount($row, $batch->tenant_id)) {
            $errors[] = "Chart account {$row['zoho_gl_code']} ({$name}) was not found in ERP. Complete the Chart of Accounts step with the same Zoho export first.";
        }
        if ($name !== '' && $batch->duplicate_strategy === 'block' && $this->existing($row, $batch->tenant_id)) {
            $errors[] = 'A bank account with this name already exists in ERP and this migration is configured to block duplicates.';
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_bank_account',
            'field' => 'account_name',
            'source_value' => $name,
            'message' => $message,
            'resolution_url' => Route::has('account.bank-accounts.index') ? route('account.bank-accounts.index') : null,
        ], $errors);
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'payment_accounts')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing bank accounts.');
        }

        $rows = $batch->files()->where('stage_key', 'payment_accounts')->where('status', 'staged')
            ->with(['stagedRows' => fn ($query) => $query->where('status', 'validated')->orderBy('source_row_number')])
            ->get()->flatMap->stagedRows;

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $seen = [];
        $prepared = [];
        foreach ($rows as $row) {
            $data = $this->prepare($row->original_values, (string) $batch->base_currency);
            if (!$data['include']) {
                continue;
            }
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $seen)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $prepared[] = [$row, $data];
        }
        if (!$prepared) {
            throw new RuntimeException('No Bank, Cash or Credit Card accounts were found in this Chart of Accounts export.');
        }

        // Blocked duplicates are rejected during validation, so only "update" changes existing accounts.
        $update = $batch->duplicate_strategy === 'update';

        return DB::transaction(function () use ($prepared, $batch, $actorId, $stage, $update): array {
            $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
            foreach ($prepared as [$row, $data]) {
                $existing = $this->existing($data, $batch->tenant_id);
                if ($existing && !$update) {
                    $counts['skipped']++;
                    $row->update(['status' => 'skipped', 'normalized_values' => $data]);
                    continue;
                }

                $values = [
                    'account_name' => $data['account_name'],
                    'account_number' => $data['account_number'],
                    'zoho_gl_code' => $data['zoho_gl_code'],
                    'bank_name' => $data['bank_name'],
                    'account_type' => $data['account_type'],
                    'currency_code' => $data['currency_code'],
                    'is_active' => $data['is_active'],
                    'gl_account_id' => $this->chartAccount($data, $batch->tenant_id)->id,
                ];
                if ($existing) {
                    $existing->update($values);
                    UpdateBankAccount::dispatch(new Request($values), $existing);
                    $result = 'updated';
                } else {
                    // Opening balances come from the accounting stage, not the chart export.
                    $account = BankAccount::create($values + ['opening_balance' => 0, 'current_balance' => 0, 'creator_id' => $actorId, 'created_by' => $batch->tenant_id]);
                    CreateBankAccount::dispatch(new Request($values), $account);
                    $result = 'imported';
                }
                $counts[$result]++;
                $row->update(['status' => $result, 'normalized_values' => $data]);
            }

            $stage->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $actorId,
                'summary' => $counts,
            ]);
            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => 'payment_accounts',
                'event_type' => 'stage_imported',
                'message' => "Bank accounts import completed: {$counts['imported']} created, {$counts['updated']} updated, {$counts['skipped']} already existed.",
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    private function chartAccount(array $row, int $tenantId): ?ChartOfAccount
    {
        $code = trim((string) ($row['zoho_gl_code'] ?? ''));

        return $code === '' ? null : ChartOfAccount::where('created_by', $tenantId)->where('account_code', $code)->first();
    }

    // Matched by name, like the migration import's bank account lookup, because Zoho rarely has account numbers.
    private function existing(array $row, int $tenantId): ?BankAccount
    {
        return BankAccount::where('created_by', $tenantId)
            ->whereRaw('LOWER(account_name) = ?', [mb_strtolower((string) $row['account_name'])])
            ->first();
    }
}

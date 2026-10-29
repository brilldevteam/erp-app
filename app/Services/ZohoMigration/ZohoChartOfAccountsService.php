<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\BulkImport\Definitions\ChartOfAccountDefinition;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ZohoChartOfAccountsService
{
    private const CREDIT_TYPES = [
        'accounts payable', 'credit card', 'equity', 'income', 'other income',
        'other current liability', 'long term liability', 'other liability',
    ];

    public function __construct(private readonly ZohoProfileCatalog $profiles)
    {
    }

    public function prepare(array $values): array
    {
        $row = [];
        foreach ($values as $key => $value) {
            $row[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : null;
        }

        $type = $row['account type'] ?? '';

        return [
            'account_code' => $row['account code'] ?? '',
            'account_name' => $row['account name'] ?? '',
            'account_type' => $type,
            'parent_account' => $row['parent account'] ?? '',
            'normal_balance' => in_array(mb_strtolower($type), self::CREDIT_TYPES, true) ? 'credit' : 'debit',
            'opening_balance' => 0,
            'current_balance' => 0,
            'description' => $row['description'] ?? null,
            'is_active' => mb_strtolower($row['account status'] ?? 'active') === 'active',
        ];
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'chart_of_accounts')->firstOrFail();
        if ($stage->status !== 'ready' || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing Chart of Accounts.');
        }

        $rows = $batch->files()->where('stage_key', 'chart_of_accounts')->where('status', 'staged')
            ->with(['stagedRows' => fn ($query) => $query->where('status', 'validated')->orderBy('source_row_number')])
            ->get()->flatMap->stagedRows;
        if ($rows->isEmpty()) {
            throw new RuntimeException('No validated Chart of Accounts rows are ready to import.');
        }

        $definition = new ChartOfAccountDefinition();
        $counts = DB::transaction(function () use ($rows, $definition, $batch, $actorId): array {
            $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
            $pending = $rows->values();

            while ($pending->isNotEmpty()) {
                $remaining = collect();
                $progress = false;

                foreach ($pending as $row) {
                    $data = $row->normalized_values;
                    $errors = $definition->validate($data, $batch->tenant_id);
                    if ($errors && !($this->onlyPendingParent($errors, $data, $pending))) {
                        throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', $errors));
                    }
                    if ($errors) {
                        $remaining->push($row);
                        continue;
                    }

                    $result = $definition->import($data, 'skip', $batch->tenant_id, $actorId);
                    $counts[$result]++;
                    $row->update(['status' => $result]);
                    $progress = true;
                }

                if (!$progress && $remaining->isNotEmpty()) {
                    throw new RuntimeException('One or more parent accounts could not be resolved. Correct the parent account values and validate again.');
                }
                $pending = $remaining;
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
                'stage_key' => 'chart_of_accounts',
                'event_type' => 'stage_imported',
                'message' => "Chart of Accounts import completed: {$counts['imported']} created, {$counts['skipped']} already existed.",
                'metadata' => $counts,
            ]);

            return $counts;
        });

        return $counts;
    }

    private function onlyPendingParent(array $errors, array $data, $pending): bool
    {
        if ($errors !== ['Parent account was not found.']) {
            return false;
        }

        $parent = mb_strtolower(trim((string) ($data['parent_account'] ?? '')));

        return $pending->contains(fn ($row) => in_array($parent, [
            mb_strtolower((string) ($row->normalized_values['account_code'] ?? '')),
            mb_strtolower((string) ($row->normalized_values['account_name'] ?? '')),
        ], true));
    }
}

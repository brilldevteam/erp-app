<?php

namespace App\Services\ZohoMigration;

use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Services\BulkImport\Definitions\ExpenseCategoryDefinition;
use App\Services\BulkImport\Definitions\RevenueCategoryDefinition;
use App\Services\BulkImport\EntityDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Creates ERP revenue and expense categories from a Zoho Chart of Accounts export: one revenue category per
 * Income / Other Income account and one expense category per Expense / Other Expense / Cost Of Goods Sold
 * account. Each category is named exactly like its Zoho account (ERP revenues and expenses find their category
 * by name), uses the Zoho account code as its code, and links to the chart account imported in step 2.
 * Other account types are skipped; expenses Zoho booked to them get their category in the Revenues and
 * expenses stage.
 */
class ZohoIncomeExpenseCategoryService
{
    private const KINDS = [
        'income' => 'revenue',
        'other income' => 'revenue',
        'expense' => 'expense',
        'other expense' => 'expense',
        'cost of goods sold' => 'expense',
    ];

    public function __construct(
        private readonly ZohoProfileCatalog $profiles,
        private readonly RevenueCategoryDefinition $revenueCategories,
        private readonly ExpenseCategoryDefinition $expenseCategories,
    ) {
    }

    public function prepare(array $values): array
    {
        $source = [];
        foreach ($values as $key => $value) {
            $source[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : '';
        }
        $get = fn (string $key): string => trim((string) ($source[$key] ?? ''));
        $kind = self::KINDS[mb_strtolower($get('account type'))] ?? null;

        return [
            'include' => $kind !== null,
            'kind' => $kind,
            'zoho_account_type' => $get('account type'),
            'category_name' => $get('account name'),
            'category_code' => $get('account code'),
            'chart_of_account' => $get('account code'),
            'description' => $get('description') ?: null,
            'is_active' => mb_strtolower($get('account status') ?: 'active') === 'active' ? 'yes' : 'no',
        ];
    }

    /**
     * @param  array<string, int>  $seen  Category names and codes already seen per kind in this pass, with row numbers.
     */
    public function issues(array $row, ZohoMigrationBatch $batch, int $sourceRowNumber, array &$seen): array
    {
        $label = $row['kind'] === 'revenue' ? 'revenue category' : 'expense category';
        $definition = $this->definition($row['kind']);
        $errors = array_map(fn (string $message) => match ($message) {
            'Category name is required.' => 'Account Name is required.',
            'Category code is required.' => 'Account Code is required.',
            'Chart of account was not found.' => "Chart account {$row['category_code']} ({$row['category_name']}) was not found in ERP. Complete the Chart of Accounts step with the same Zoho export first.",
            default => $message,
        }, $definition->validate($row, $batch->tenant_id));
        // The ERP category description column holds 255 characters; reject rather than cut the text.
        if (mb_strlen((string) $row['description']) > 255) {
            $errors[] = 'Description is longer than 255 characters ('.mb_strlen((string) $row['description']).'). Shorten it in the file, then upload it again.';
        }

        foreach (['name' => $row['category_name'], 'code' => $row['category_code']] as $field => $value) {
            $key = $row['kind'].'|'.$field.'|'.mb_strtolower((string) $value);
            if ($value === '') {
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = 'Account '.($field === 'name' ? 'Name' : 'Code')." '{$value}' is used by another {$label} in this file (rows {$seen[$key]} and {$sourceRowNumber}).";
            } else {
                $seen[$key] = $sourceRowNumber;
            }
        }
        if ($batch->duplicate_strategy === 'block' && $definition->duplicate($row, $batch->tenant_id)) {
            $errors[] = ($row['kind'] === 'expense' ? 'An ' : 'A ').$label.' with this name or code already exists in ERP and this migration is configured to block duplicates.';
        }

        return array_map(fn (string $message) => [
            'type' => 'invalid_'.str_replace(' ', '_', $label),
            'field' => 'account_name',
            'source_value' => $row['category_name'],
            'message' => $message,
            'resolution_url' => Route::has('account.chart-of-accounts.index') ? route('account.chart-of-accounts.index') : null,
        ], array_values(array_unique($errors)));
    }

    public function import(ZohoMigrationBatch $batch, int $actorId): array
    {
        $stage = $batch->stages()->where('stage_key', 'income_expense_categories')->firstOrFail();
        if (!in_array($stage->status, ZohoChartOfAccountsService::EXECUTABLE_STATUSES, true) || $stage->rejected_count > 0) {
            throw new RuntimeException('Resolve all validation issues before importing revenue and expense categories.');
        }

        $rows = $batch->files()->where('stage_key', 'income_expense_categories')->where('status', 'staged')
            ->with(['stagedRows' => fn ($query) => $query->where('status', 'validated')->orderBy('source_row_number')])
            ->get()->flatMap->stagedRows;

        // Rebuild every row from the Zoho values and re-run the checks before anything is written.
        $seen = [];
        $prepared = [];
        foreach ($rows as $row) {
            $data = $this->prepare($row->original_values);
            if (!$data['include']) {
                continue;
            }
            if ($issues = $this->issues($data, $batch, (int) $row->source_row_number, $seen)) {
                throw new RuntimeException('Row '.$row->source_row_number.': '.implode(' ', array_column($issues, 'message')).' Upload the file again to re-check it.');
            }
            $prepared[] = [$row, $data];
        }
        if (!$prepared) {
            throw new RuntimeException('No income or expense accounts were found in this Chart of Accounts export.');
        }

        // Blocked duplicates are rejected during validation, so only "update" changes existing categories.
        $strategy = $batch->duplicate_strategy === 'update' ? 'update' : 'skip';

        return DB::transaction(function () use ($prepared, $batch, $actorId, $stage, $strategy): array {
            $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'revenue_categories' => 0, 'expense_categories' => 0];
            foreach ($prepared as [$row, $data]) {
                $result = $this->definition($data['kind'])->import($data, $strategy, $batch->tenant_id, $actorId);
                $counts[$result]++;
                $counts[$data['kind'].'_categories']++;
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
                'stage_key' => 'income_expense_categories',
                'event_type' => 'stage_imported',
                'message' => "Categories import completed: {$counts['revenue_categories']} revenue and {$counts['expense_categories']} expense categories ({$counts['imported']} created, {$counts['updated']} updated, {$counts['skipped']} already existed).",
                'metadata' => $counts,
            ]);

            return $counts;
        });
    }

    private function definition(?string $kind): EntityDefinition
    {
        return $kind === 'revenue' ? $this->revenueCategories : $this->expenseCategories;
    }
}

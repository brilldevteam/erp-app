<?php

namespace App\Services\ZohoMigration;

use App\Models\Warehouse;
use App\Models\ZohoMigrationBatch;
use App\Models\ZohoMigrationEvent;
use App\Models\ZohoMigrationIssue;
use App\Models\ZohoMigrationStagedRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Workdo\Account\Models\Customer;
use Workdo\Account\Models\Vendor;
use Workdo\ProductService\Models\ProductServiceItem;

class ZohoMigrationValidator
{
    public function __construct(private readonly ZohoProfileCatalog $profiles)
    {
    }

    public function validate(ZohoMigrationBatch $batch, string $stageKey, int $actorId): array
    {
        $files = $batch->files()->where('stage_key', $stageKey)->where('status', 'staged')->get();
        if ($files->isEmpty()) {
            return ['accepted' => 0, 'rejected' => 0, 'warnings' => 0, 'issues' => 0];
        }

        return DB::transaction(function () use ($batch, $stageKey, $actorId, $files): array {
            $batch->issues()->where('stage_key', $stageKey)->delete();
            $accepted = 0;
            $rejected = 0;

            foreach ($files as $file) {
                foreach ($file->stagedRows()->orderBy('source_row_number')->cursor() as $row) {
                    $issues = $this->rowIssues($batch->tenant_id, $stageKey, $this->normalize($row->original_values));
                    if ($issues) {
                        $rejected++;
                        $this->storeIssues($batch, $row, $stageKey, $issues);
                        $row->update(['status' => 'invalid', 'validation_errors' => array_column($issues, 'message')]);
                    } else {
                        $accepted++;
                        $row->update(['status' => 'validated', 'validation_errors' => null]);
                    }
                }
            }

            $status = $rejected > 0 ? 'review_required' : 'ready';
            $batch->stages()->where('stage_key', $stageKey)->update([
                'status' => $status,
                'accepted_count' => $accepted,
                'rejected_count' => $rejected,
                'warning_count' => 0,
                'summary' => json_encode(['create' => $accepted, 'link' => 0, 'skip' => 0, 'update' => 0, 'reject' => $rejected]),
                'updated_at' => now(),
            ]);

            ZohoMigrationEvent::create([
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'user_id' => $actorId,
                'stage_key' => $stageKey,
                'event_type' => 'stage_validated',
                'message' => $rejected > 0
                    ? "Validation found {$rejected} rows requiring master-data correction."
                    : "Validation passed for {$accepted} source rows.",
                'metadata' => ['accepted' => $accepted, 'rejected' => $rejected],
            ]);

            return ['accepted' => $accepted, 'rejected' => $rejected, 'warnings' => 0, 'issues' => $rejected];
        });
    }

    private function rowIssues(int $tenantId, string $stageKey, array $row): array
    {
        $issues = [];
        if ($stageKey === 'sales_invoices') {
            $customer = $this->value($row, ['customer name', 'customer']);
            if (!$customer || !Customer::where('created_by', $tenantId)->where(function ($query) use ($customer) {
                $query->whereRaw('LOWER(company_name) = ?', [mb_strtolower($customer)])
                    ->orWhereRaw('LOWER(contact_person_name) = ?', [mb_strtolower($customer)])
                    ->orWhereHas('user', fn ($user) => $user->whereRaw('LOWER(name) = ?', [mb_strtolower($customer)]));
            })->exists()) {
                $issues[] = $this->missing('customer', $customer, 'Customer was not found for this company.', 'account.customers.index');
            }
            $issues = [...$issues, ...$this->itemAndWarehouseIssues($tenantId, $row)];
        }

        if ($stageKey === 'purchase_invoices') {
            $vendor = $this->value($row, ['vendor name', 'vendor']);
            if (!$vendor || !Vendor::where('created_by', $tenantId)->where(function ($query) use ($vendor) {
                $query->whereRaw('LOWER(company_name) = ?', [mb_strtolower($vendor)])
                    ->orWhereRaw('LOWER(contact_person_name) = ?', [mb_strtolower($vendor)])
                    ->orWhereHas('user', fn ($user) => $user->whereRaw('LOWER(name) = ?', [mb_strtolower($vendor)]));
            })->exists()) {
                $issues[] = $this->missing('vendor', $vendor, 'Vendor was not found for this company.', 'account.vendors.index');
            }
            $issues = [...$issues, ...$this->itemAndWarehouseIssues($tenantId, $row)];
        }

        return $issues;
    }

    private function itemAndWarehouseIssues(int $tenantId, array $row): array
    {
        $issues = [];
        $sku = $this->value($row, ['sku', 'item sku', 'product id']);
        $itemName = $this->value($row, ['item name', 'product name']);
        $itemQuery = ProductServiceItem::where('created_by', $tenantId);
        $itemFound = $sku
            ? (clone $itemQuery)->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->exists()
            : ($itemName ? (clone $itemQuery)->whereRaw('LOWER(name) = ?', [mb_strtolower($itemName)])->exists() : false);
        if (!$itemFound) {
            $issues[] = $this->missing('item', $sku ?: $itemName, 'Product or service was not found for this company.', 'product-service.items.index');
        }

        $warehouse = $this->value($row, ['warehouse name', 'warehouse']);
        if ($warehouse && !Warehouse::where('created_by', $tenantId)->whereRaw('LOWER(name) = ?', [mb_strtolower($warehouse)])->exists()) {
            $issues[] = $this->missing('warehouse', $warehouse, 'Warehouse was not found for this company.', 'warehouses.index');
        }

        return $issues;
    }

    private function normalize(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[$this->profiles->normalize($key)] = is_scalar($value) ? trim((string) $value) : null;
        }

        return $normalized;
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (($row[$key] ?? '') !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function missing(string $field, ?string $value, string $message, string $route): array
    {
        return ['type' => 'missing_master', 'field' => $field, 'source_value' => $value, 'message' => $message, 'resolution_url' => Route::has($route) ? route($route) : null];
    }

    private function storeIssues(ZohoMigrationBatch $batch, ZohoMigrationStagedRow $row, string $stageKey, array $issues): void
    {
        foreach ($issues as $issue) {
            ZohoMigrationIssue::create($issue + [
                'batch_id' => $batch->id,
                'file_id' => $row->file_id,
                'staged_row_id' => $row->id,
                'tenant_id' => $batch->tenant_id,
                'stage_key' => $stageKey,
                'severity' => 'error',
                'status' => 'open',
            ]);
        }
    }
}

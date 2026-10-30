<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_ORDER = [
        'configuration', 'chart_of_accounts', 'taxes', 'categories_units', 'items', 'warehouses', 'customers',
        'vendors', 'payment_accounts', 'income_expense_categories', 'quotations', 'sales_invoices',
        'purchase_invoices', 'credit_notes', 'vendor_credits', 'customer_payments', 'vendor_payments',
        'credit_applications', 'income_expenses', 'bank_activity', 'inventory_cutover', 'accounting', 'reconciliation',
    ];

    private const OLD_ORDER = [
        'configuration', 'chart_of_accounts', 'taxes', 'units', 'categories', 'warehouses', 'customers', 'vendors',
        'items', 'payment_accounts', 'income_expense_categories', 'quotations', 'sales_invoices', 'purchase_invoices',
        'credit_notes', 'vendor_credits', 'customer_payments', 'vendor_payments', 'credit_applications',
        'income_expenses', 'bank_activity', 'inventory_cutover', 'accounting', 'reconciliation',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('zoho_migration_stages')) {
            return;
        }

        // Neither stage could be completed on its own: categories was unsupported and units had no completion path.
        DB::table('zoho_migration_stages')->whereIn('stage_key', ['units', 'categories'])->delete();
        $this->insertStageForEveryBatch('categories_units', 4);
        $this->applyOrder(self::NEW_ORDER);
    }

    public function down(): void
    {
        if (!Schema::hasTable('zoho_migration_stages')) {
            return;
        }

        DB::table('zoho_migration_stages')->where('stage_key', 'categories_units')->delete();
        $this->insertStageForEveryBatch('units', 4);
        $this->insertStageForEveryBatch('categories', 5);
        $this->applyOrder(self::OLD_ORDER);
    }

    private function insertStageForEveryBatch(string $stageKey, int $position): void
    {
        $now = now();
        $rows = DB::table('zoho_migration_batches')
            ->whereNotIn('id', DB::table('zoho_migration_stages')->where('stage_key', $stageKey)->select('batch_id'))
            ->get(['id', 'tenant_id'])
            ->map(fn ($batch) => [
                'batch_id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'stage_key' => $stageKey,
                'position' => $position,
                'status' => 'not_started',
                'accepted_count' => 0,
                'rejected_count' => 0,
                'warning_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

        if ($rows !== []) {
            DB::table('zoho_migration_stages')->insert($rows);
        }
    }

    private function applyOrder(array $order): void
    {
        foreach ($order as $index => $stageKey) {
            DB::table('zoho_migration_stages')->where('stage_key', $stageKey)->update(['position' => $index + 1]);
        }
    }
};

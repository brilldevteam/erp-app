<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $currencyTables = [
        'sales_invoices', 'purchase_invoices', 'customer_payments', 'vendor_payments',
        'credit_notes', 'debit_notes', 'journal_entries',
    ];

    public function up(): void
    {
        foreach ($this->currencyTables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'base_currency_code')) {
                continue;
            }

            Schema::table($tableName, fn (Blueprint $table) => $table->string('base_currency_code', 3)->nullable()->after('currency_code'));
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'preferred_currency_code')) {
            Schema::table('users', fn (Blueprint $table) => $table->string('preferred_currency_code', 3)->nullable()->after('email'));
        }

        if (Schema::hasTable('sales_invoices') && !Schema::hasColumn('sales_invoices', 'sales_order_reference')) {
            Schema::table('sales_invoices', fn (Blueprint $table) => $table->string('sales_order_reference')->nullable()->after('invoice_number'));
        }

        if (Schema::hasTable('purchase_invoices')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_invoices', 'supplier_invoice_number')) {
                    $table->string('supplier_invoice_number')->nullable()->after('invoice_number');
                }
                if (!Schema::hasColumn('purchase_invoices', 'purchase_order_number')) {
                    $table->string('purchase_order_number')->nullable()->after('supplier_invoice_number');
                }
            });
        }

        foreach (['customer_payment_allocations', 'vendor_payment_allocations'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'carrying_base_amount')) {
                    $table->decimal('carrying_base_amount', 20, 2)->nullable()->after('allocated_amount');
                }
                if (!Schema::hasColumn($tableName, 'settlement_base_amount')) {
                    $table->decimal('settlement_base_amount', 20, 2)->nullable()->after('carrying_base_amount');
                }
                if (!Schema::hasColumn($tableName, 'exchange_difference')) {
                    $table->decimal('exchange_difference', 20, 2)->default(0)->after('settlement_base_amount');
                }
            });
        }

        $this->backfillBaseCurrencies();
        $this->installDownloadPermissions();
    }

    private function backfillBaseCurrencies(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        foreach ($this->currencyTables as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'created_by')) {
                continue;
            }

            DB::table($tableName)
                ->whereNull('base_currency_code')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName) {
                    $companyIds = $rows->pluck('created_by')->filter()->unique()->values();
                    $currencies = DB::table('settings')
                        ->whereIn('created_by', $companyIds)
                        ->where('key', 'defaultCurrency')
                        ->pluck('value', 'created_by');

                    foreach ($rows as $row) {
                        DB::table($tableName)->where('id', $row->id)->update([
                            'base_currency_code' => strtoupper((string) ($currencies[$row->created_by] ?? $row->currency_code ?? 'USD')),
                        ]);
                    }
                });
        }
    }

    private function installDownloadPermissions(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        foreach ([
            ['download-sales-invoices', 'sales-invoices', 'Download Sales Invoices'],
            ['download-purchase-invoices', 'purchase-invoices', 'Download Purchase Invoices'],
        ] as [$name, $module, $label]) {
            $values = ['name' => $name, 'guard_name' => 'web'];
            foreach (['module' => $module, 'label' => $label, 'add_on' => 'Account'] as $column => $value) {
                if (Schema::hasColumn('permissions', $column)) {
                    $values[$column] = $value;
                }
            }
            if (Schema::hasColumn('permissions', 'created_at')) {
                $values['created_at'] = $now;
                $values['updated_at'] = $now;
            }
            DB::table('permissions')->updateOrInsert(['name' => $name, 'guard_name' => 'web'], $values);
        }

        if (!Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }

        $roleIds = DB::table('roles')->where('name', 'company')->pluck('id');
        $permissions = DB::table('permissions')->whereIn('name', ['download-sales-invoices', 'download-purchase-invoices'])->get(['id']);
        foreach ($roleIds as $roleId) {
            foreach ($permissions as $permission) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permission->id,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_has_permissions') && Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->whereIn('name', ['download-sales-invoices', 'download-purchase-invoices'])->pluck('id');
            DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
            DB::table('permissions')->whereIn('id', $ids)->delete();
        }

        foreach (['customer_payment_allocations', 'vendor_payment_allocations'] as $tableName) {
            if (Schema::hasTable($tableName)) {
                $columns = array_values(array_filter(['carrying_base_amount', 'settlement_base_amount', 'exchange_difference'], fn ($column) => Schema::hasColumn($tableName, $column)));
                if ($columns !== []) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
                }
            }
        }

        foreach ([
            'sales_invoices' => ['sales_order_reference'],
            'purchase_invoices' => ['supplier_invoice_number', 'purchase_order_number'],
            'users' => ['preferred_currency_code'],
        ] as $tableName => $columns) {
            if (Schema::hasTable($tableName)) {
                $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($tableName, $column)));
                if ($existing !== []) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($existing));
                }
            }
        }

        foreach ($this->currencyTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'base_currency_code')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('base_currency_code'));
            }
        }
    }
};

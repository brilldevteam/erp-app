<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sales_invoice_items')) {
            return;
        }

        $this->addNullableUserColumn('sales_invoice_items', 'creator_id', 'sales_invoice_items_creator_id_foreign');
        $this->addNullableUserColumn('sales_invoice_items', 'created_by', 'sales_invoice_items_created_by_foreign');
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_invoice_items')) {
            return;
        }

        $this->dropNullableUserColumn('sales_invoice_items', 'created_by', 'sales_invoice_items_created_by_foreign');
        $this->dropNullableUserColumn('sales_invoice_items', 'creator_id', 'sales_invoice_items_creator_id_foreign');
    }

    private function addNullableUserColumn(string $table, string $column, string $foreignKey): void
    {
        if (!Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $table) use ($column): void {
                $table->foreignId($column)->nullable()->index();
            });
        }

        if ($this->supportsForeignKeyInspection() && !$this->foreignKeyExists($table, $foreignKey)) {
            Schema::table($table, function (Blueprint $table) use ($column, $foreignKey): void {
                $table->foreign($column, $foreignKey)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    private function dropNullableUserColumn(string $table, string $column, string $foreignKey): void
    {
        if (!Schema::hasColumn($table, $column)) {
            return;
        }

        if ($this->supportsForeignKeyInspection() && $this->foreignKeyExists($table, $foreignKey)) {
            Schema::table($table, function (Blueprint $table) use ($foreignKey): void {
                $table->dropForeign($foreignKey);
            });
        }

        Schema::table($table, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }

    private function supportsForeignKeyInspection(): bool
    {
        return Schema::getConnection()->getDriverName() === 'mysql';
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        $result = DB::selectOne(
            'select CONSTRAINT_NAME from information_schema.TABLE_CONSTRAINTS where CONSTRAINT_SCHEMA = DATABASE() and TABLE_NAME = ? and CONSTRAINT_NAME = ? and CONSTRAINT_TYPE = ?',
            [$table, $foreignKey, 'FOREIGN KEY']
        );

        return $result !== null;
    }
};

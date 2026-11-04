<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales_invoice_items', 'purchase_invoice_items', 'sales_quotation_items'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
                if (!Schema::hasColumn($tableName, 'item_name')) {
                    $table->string('item_name')->nullable()->after('product_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['sales_invoice_items', 'purchase_invoice_items', 'sales_quotation_items'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'item_name')) {
                    $table->dropColumn('item_name');
                }
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            });
        }
    }
};

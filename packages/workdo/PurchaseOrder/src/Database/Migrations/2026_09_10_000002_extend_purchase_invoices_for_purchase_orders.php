<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_invoices', 'purchase_order_reference')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('purchase_order_reference')->nullable()->after('invoice_number');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'currency_code')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('currency_code', 3)->nullable()->after('purchase_order_reference');
            });
        }

        if (!Schema::hasColumn('purchase_invoices', 'exchange_rate')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency_code');
            });
        }

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->decimal('quantity', 18, 4)->change();
        });

        if (!Schema::hasColumn('purchase_invoice_items', 'unit')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->string('unit')->nullable()->after('product_id');
            });
        }

        if (!Schema::hasColumn('purchase_invoice_items', 'description')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->text('description')->nullable()->after('unit');
            });
        }
    }

    public function down(): void
    {
        $itemColumns = array_values(array_filter(
            ['unit', 'description'],
            fn (string $column) => Schema::hasColumn('purchase_invoice_items', $column)
        ));
        if ($itemColumns !== []) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) use ($itemColumns) {
                $table->dropColumn($itemColumns);
            });
        }

        $invoiceColumns = array_values(array_filter(
            ['purchase_order_reference', 'currency_code', 'exchange_rate'],
            fn (string $column) => Schema::hasColumn('purchase_invoices', $column)
        ));
        if ($invoiceColumns !== []) {
            Schema::table('purchase_invoices', function (Blueprint $table) use ($invoiceColumns) {
                $table->dropColumn($invoiceColumns);
            });
        }
    }
};

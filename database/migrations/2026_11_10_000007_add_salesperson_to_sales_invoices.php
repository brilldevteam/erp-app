<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a sales invoice carry its own salesperson (same list as quotations), so the salesperson
 * statement can show invoices that were not converted from a quotation. Existing rows are not changed;
 * an invoice without its own salesperson falls back to its quotation's salesperson in the statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales_invoices', 'salesperson_id')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->foreignId('salesperson_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('quotation_salespersons')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('sales_invoices', 'salesperson_id')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('salesperson_id');
        });
    }
};

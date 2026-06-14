<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales_invoices', 'quotation_id')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->foreignId('quotation_id')
                    ->nullable()
                    ->after('id')
                    ->unique()
                    ->constrained('sales_quotations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_invoices', 'quotation_id')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->dropConstrainedForeignId('quotation_id');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'payment_terms')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->text('payment_terms')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_invoices') && Schema::hasColumn('purchase_invoices', 'payment_terms')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->string('payment_terms', 255)->nullable()->change();
            });
        }
    }
};

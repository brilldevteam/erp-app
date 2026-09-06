<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->string('subject', 500)->nullable()->after('payment_terms');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('subject', 500)->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->dropColumn('subject');
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn('subject');
        });
    }
};

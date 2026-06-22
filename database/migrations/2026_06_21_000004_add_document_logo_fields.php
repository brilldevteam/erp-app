<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('document_logo', 500)->nullable()->after('template_key');
        });

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->string('document_logo', 500)->nullable()->after('template_key');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropColumn('document_logo'));
        Schema::table('sales_quotations', fn (Blueprint $table) => $table->dropColumn('document_logo'));
    }
};

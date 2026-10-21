<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales_invoices', 'revenue_account_id')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('revenue_account_id')->nullable()->after('type')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('sales_invoices', 'revenue_account_id')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex(['revenue_account_id']);
            $table->dropColumn('revenue_account_id');
        });
    }
};

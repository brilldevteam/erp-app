<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('balance_sheets', 'period_start_date')) {
            Schema::table('balance_sheets', function (Blueprint $table) {
                $table->date('period_start_date')->nullable()->after('balance_sheet_date');
            });
        }

        // "Net Income for the Period" is an equity line without a chart-of-accounts row.
        if (!Schema::hasColumn('balance_sheet_items', 'label')) {
            Schema::table('balance_sheet_items', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
            });

            Schema::table('balance_sheet_items', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->change();
                $table->string('label')->nullable()->after('account_id');
                $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('balance_sheet_items', 'label')) {
            Schema::table('balance_sheet_items', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
            });

            DB::table('balance_sheet_items')->whereNull('account_id')->delete();

            Schema::table('balance_sheet_items', function (Blueprint $table) {
                $table->dropColumn('label');
                $table->unsignedBigInteger('account_id')->nullable(false)->change();
                $table->foreign('account_id')->references('id')->on('chart_of_accounts')->onDelete('cascade');
            });
        }

        if (Schema::hasColumn('balance_sheets', 'period_start_date')) {
            Schema::table('balance_sheets', function (Blueprint $table) {
                $table->dropColumn('period_start_date');
            });
        }
    }
};

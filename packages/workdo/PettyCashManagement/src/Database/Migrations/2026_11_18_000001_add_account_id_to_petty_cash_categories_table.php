<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The expense account a petty cash category posts to when a request or reimbursement is approved.
     */
    public function up(): void
    {
        if (Schema::hasTable('petty_cash_categories') && !Schema::hasColumn('petty_cash_categories', 'account_id')) {
            Schema::table('petty_cash_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('name')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('petty_cash_categories', 'account_id')) {
            Schema::table('petty_cash_categories', function (Blueprint $table) {
                $table->dropIndex(['account_id']);
                $table->dropColumn('account_id');
            });
        }
    }
};

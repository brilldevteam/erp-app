<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_accounts', 'zoho_gl_code')) {
                $table->string('zoho_gl_code')->nullable()->after('account_number');
            }
        });

        // account_number stays required for normal manual/API creation (enforced in
        // StoreBankAccountRequest, unchanged). This only relaxes the DB-level
        // constraint so the one-time Zoho migration import can leave it null on
        // accounts pending finance verification of the real bank account number.
        DB::statement('ALTER TABLE bank_accounts MODIFY account_number VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE bank_accounts SET account_number = '' WHERE account_number IS NULL");
        DB::statement('ALTER TABLE bank_accounts MODIFY account_number VARCHAR(255) NOT NULL');

        Schema::table('bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('bank_accounts', 'zoho_gl_code')) {
                $table->dropColumn('zoho_gl_code');
            }
        });
    }
};

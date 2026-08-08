<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['expenses', 'customer_payments', 'vendor_payments'];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'needs_bank_verification')) {
                    // Defaults to false so every existing row and every record created
                    // through the normal UI/API is unaffected. Only the one-time Zoho
                    // migration import service explicitly sets this to true.
                    $table->boolean('needs_bank_verification')->default(false)->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'needs_bank_verification')) {
                    $table->dropColumn('needs_bank_verification');
                }
            });
        }
    }
};

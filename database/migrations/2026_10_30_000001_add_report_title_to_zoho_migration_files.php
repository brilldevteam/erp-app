<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho reports (Trial Balance, Detailed General Ledger, aging) carry their period in a title row above the
 * column headers, e.g. "Trial Balance … As of 31 Jul 2026". The reconciliation stage needs that date.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zoho_migration_files') || Schema::hasColumn('zoho_migration_files', 'report_title')) {
            return;
        }

        Schema::table('zoho_migration_files', function (Blueprint $table): void {
            $table->text('report_title')->nullable()->after('headers');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('zoho_migration_files', 'report_title')) {
            Schema::table('zoho_migration_files', function (Blueprint $table): void {
                $table->dropColumn('report_title');
            });
        }
    }
};

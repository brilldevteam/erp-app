<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Zoho export can feed several stages (the Chart of Accounts export feeds both the Chart of Accounts
 * and the bank accounts stages), so a source file is unique per stage instead of per migration batch.
 * Each stage still only accepts files whose detected dataset belongs to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zoho_migration_files')) {
            return;
        }

        Schema::table('zoho_migration_files', function (Blueprint $table): void {
            $table->unique(['batch_id', 'stage_key', 'sha256'], 'zoho_files_batch_stage_sha256_unique');
            $table->dropUnique(['batch_id', 'sha256']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('zoho_migration_files')) {
            return;
        }

        // Fails if the same file is attached to more than one stage; detach the extra copies first.
        Schema::table('zoho_migration_files', function (Blueprint $table): void {
            $table->unique(['batch_id', 'sha256']);
            $table->dropUnique('zoho_files_batch_stage_sha256_unique');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zoho_migration_stages')) {
            return;
        }

        DB::table('zoho_migration_stages')
            ->where('stage_key', 'currencies')
            ->delete();

        DB::table('zoho_migration_stages')
            ->where('position', '>', 2)
            ->decrement('position');
    }

    public function down(): void
    {
        if (!Schema::hasTable('zoho_migration_stages')) {
            return;
        }

        DB::table('zoho_migration_stages')
            ->where('position', '>=', 2)
            ->increment('position');

        $now = now();
        $rows = DB::table('zoho_migration_batches')->get(['id', 'tenant_id'])->map(fn ($batch) => [
            'batch_id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'stage_key' => 'currencies',
            'position' => 2,
            'status' => 'not_started',
            'accepted_count' => 0,
            'rejected_count' => 0,
            'warning_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            DB::table('zoho_migration_stages')->insert($rows);
        }
    }
};

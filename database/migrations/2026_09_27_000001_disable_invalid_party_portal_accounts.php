<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->whereIn('type', ['client', 'vendor'])
            ->select(['id', 'type', 'email'])
            ->orderBy('id')
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $table = $user->type === 'vendor' ? 'vendors' : 'customers';
                    $partyEmail = Schema::hasTable($table)
                        ? DB::table($table)->where('user_id', $user->id)->value('contact_person_email')
                        : null;
                    $partyEmail = strtolower(trim((string) $partyEmail));
                    $userEmail = strtolower(trim((string) $user->email));
                    $hasValidAccess = $partyEmail !== ''
                        && $partyEmail === $userEmail
                        && !str_ends_with($userEmail, '@import.local');

                    if (!$hasValidAccess) {
                        DB::table('users')->where('id', $user->id)->update([
                            'is_enable_login' => 0,
                            'is_disable' => 1,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Invalid portal access must not be restored automatically.
    }
};

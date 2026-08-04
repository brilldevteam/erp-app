<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'security_version')) {
                $table->unsignedInteger('security_version')->default(1)->after('password_changed_at');
            }

            if (!Schema::hasColumn('users', 'security_revoked_at')) {
                $table->timestamp('security_revoked_at')->nullable()->after('security_version');
            }

            if (!Schema::hasColumn('users', 'security_revoked_reason')) {
                $table->string('security_revoked_reason')->nullable()->after('security_revoked_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'security_revoked_reason')) {
                $table->dropColumn('security_revoked_reason');
            }

            if (Schema::hasColumn('users', 'security_revoked_at')) {
                $table->dropColumn('security_revoked_at');
            }

            if (Schema::hasColumn('users', 'security_version')) {
                $table->dropColumn('security_version');
            }
        });
    }
};

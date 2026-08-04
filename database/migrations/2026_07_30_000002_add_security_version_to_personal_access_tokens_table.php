<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens') || Schema::hasColumn('personal_access_tokens', 'security_version')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->unsignedInteger('security_version')->nullable()->after('abilities')->index();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('personal_access_tokens') || !Schema::hasColumn('personal_access_tokens', 'security_version')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('security_version');
        });
    }
};

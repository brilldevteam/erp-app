<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'email')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->string('email')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'email')) {
            DB::table('leads')->whereNull('email')->update(['email' => '']);

            Schema::table('leads', function (Blueprint $table): void {
                $table->string('email')->nullable(false)->change();
            });
        }
    }
};

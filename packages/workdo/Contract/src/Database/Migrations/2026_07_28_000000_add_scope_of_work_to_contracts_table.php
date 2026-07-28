<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts') || Schema::hasColumn('contracts', 'scope_of_work')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table): void {
            $table->longText('scope_of_work')->nullable()->after('subject');
        });

        DB::table('contracts')
            ->whereNull('scope_of_work')
            ->update(['scope_of_work' => DB::raw('subject')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('contracts') || !Schema::hasColumn('contracts', 'scope_of_work')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn('scope_of_work');
        });
    }
};

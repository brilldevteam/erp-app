<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'category')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->string('category', 30)->default('general')->after('name')->index();
            });

            DB::table('projects')
                ->whereNotNull('property_information')
                ->update(['category' => 'property']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'category')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};

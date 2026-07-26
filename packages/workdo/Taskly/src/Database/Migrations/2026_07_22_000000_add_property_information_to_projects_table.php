<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects') && !Schema::hasColumn('projects', 'property_information')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('property_information')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects') && Schema::hasColumn('projects', 'property_information')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('property_information');
            });
        }
    }
};

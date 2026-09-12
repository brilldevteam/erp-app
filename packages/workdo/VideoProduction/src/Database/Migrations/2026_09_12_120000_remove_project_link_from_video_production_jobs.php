<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('video_production_jobs', 'taskly_project_id')) {
            Schema::table('video_production_jobs', function (Blueprint $table) {
                $table->dropColumn('taskly_project_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('video_production_jobs', 'taskly_project_id')) {
            Schema::table('video_production_jobs', function (Blueprint $table) {
                $table->unsignedBigInteger('taskly_project_id')->nullable()->index();
            });
        }
    }
};

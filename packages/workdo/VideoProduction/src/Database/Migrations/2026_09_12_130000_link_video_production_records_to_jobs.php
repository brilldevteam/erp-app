<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('video_production_records', 'production_job_id')) {
            Schema::table('video_production_records', function (Blueprint $table) {
                $table->foreignId('production_job_id')->nullable()->after('id')->index();
                $table->foreign('production_job_id')->references('id')->on('video_production_jobs')->cascadeOnDelete();
            });
        }

        $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
        if ($indexes->contains('video_production_records_created_by_type_record_key_unique')) {
            Schema::table('video_production_records', function (Blueprint $table) {
                $table->dropUnique('video_production_records_created_by_type_record_key_unique');
            });
        }
        $indexes = collect(Schema::getIndexes('video_production_records'))->pluck('name');
        if (! $indexes->contains('video_production_records_job_type_key_unique')) {
            Schema::table('video_production_records', function (Blueprint $table) {
                $table->unique(['created_by', 'production_job_id', 'type', 'record_key'], 'video_production_records_job_type_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('video_production_records', 'production_job_id')) {
            Schema::table('video_production_records', function (Blueprint $table) {
                $table->dropUnique('video_production_records_job_type_key_unique');
                $table->dropForeign(['production_job_id']);
                $table->dropColumn('production_job_id');
                $table->unique(['created_by', 'type', 'record_key']);
            });
        }
    }
};

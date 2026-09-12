<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('video_production_records')) {
            Schema::create('video_production_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_job_id')->index();
                $table->string('type', 30)->index();
                $table->string('record_key', 100);
                $table->dateTime('recorded_at')->nullable()->index();
                $table->string('status', 60)->nullable()->index();
                $table->json('data');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->index();
                $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('production_job_id')->references('id')->on('video_production_jobs')->cascadeOnDelete();
                $table->unique(['created_by', 'production_job_id', 'type', 'record_key'], 'video_production_records_job_type_key_unique');
                $table->index(['created_by', 'type', 'recorded_at']);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('video_production_records');
    }
};

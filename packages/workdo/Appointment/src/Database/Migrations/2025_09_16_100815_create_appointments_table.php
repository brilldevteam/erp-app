<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointments')) {
            Schema::create('appointments', function (Blueprint $table) {
                $table->id();
                $table->string('appointment_name');
                $table->string('appointment_type')->default('0');
                $table->json('week_day')->nullable();
                $table->integer('duration')->nullable();
                $table->boolean('phone_enabled')->default(false);
                $table->json('question_ids')->nullable();
                $table->boolean('enabled')->default(false);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('creator_id', 'appointments_creator_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by', 'appointments_created_by_foreign')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};

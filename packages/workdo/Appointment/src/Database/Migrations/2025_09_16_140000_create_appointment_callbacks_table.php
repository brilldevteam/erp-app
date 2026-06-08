<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appointment_callbacks')) {
            Schema::create('appointment_callbacks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_id')->nullable()->index();
                $table->string('unique_code', 100);
                $table->foreignId('user_id')->nullable()->index();
                $table->foreignId('appointment_id')->nullable()->index();
                $table->longText('reason')->nullable();
                $table->date('date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('schedule_id', 'appointment_callbacks_schedule_id_foreign')->references('id')->on('schedules')->onDelete('cascade');
                $table->foreign('user_id', 'appointment_callbacks_user_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('appointment_id', 'appointment_callbacks_appointment_id_foreign')->references('id')->on('appointments')->onDelete('cascade');
                $table->foreign('creator_id', 'appointment_callbacks_creator_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by', 'appointment_callbacks_created_by_foreign')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('appointment_callbacks')) {
            Schema::table('appointment_callbacks', function (Blueprint $table) {
                $table->dropForeign('appointment_callbacks_schedule_id_foreign');
                $table->dropForeign('appointment_callbacks_user_id_foreign');
                $table->dropForeign('appointment_callbacks_appointment_id_foreign');
                $table->dropForeign('appointment_callbacks_creator_id_foreign');
                $table->dropForeign('appointment_callbacks_created_by_foreign');
            });
        }
        Schema::dropIfExists('appointment_callbacks');
    }
};

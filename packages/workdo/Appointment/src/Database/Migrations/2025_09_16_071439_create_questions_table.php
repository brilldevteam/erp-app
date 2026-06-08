<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('questions')) {
            Schema::create('questions', function (Blueprint $table) {
                $table->id();
                $table->string('question_name');
                $table->string('question_type')->default('0');
                $table->longText('available_answers');
                $table->boolean('required_answer')->default(false);
                $table->boolean('enabled')->default(false);
                $table->foreignId('creator_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->index();

                $table->foreign('creator_id', 'questions_creator_id_foreign')->references('id')->on('users')->onDelete('set null');
                $table->foreign('created_by', 'questions_created_by_foreign')->references('id')->on('users')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

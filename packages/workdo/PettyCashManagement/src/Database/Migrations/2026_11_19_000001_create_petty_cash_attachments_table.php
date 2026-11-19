<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('petty_cash_attachments')) {
            return;
        }

        // Supporting documents for petty cash requests ('request') and petty cash funds ('fund').
        Schema::create('petty_cash_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type', 20);
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('removed_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['attachable_type', 'attachable_id']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_attachments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('helpdesk_attachments')) {
            Schema::create('helpdesk_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->cascadeOnDelete();
                $table->foreignId('reply_id')->constrained('helpdesk_replies')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('disk', 40);
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_attachments');
    }
};

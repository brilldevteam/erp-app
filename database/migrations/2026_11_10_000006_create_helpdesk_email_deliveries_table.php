<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('helpdesk_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_key')->unique();
            $table->foreignId('ticket_id')->nullable()->constrained('helpdesk_tickets')->nullOnDelete();
            $table->string('event', 40);
            $table->string('template');
            $table->json('recipients');
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_email_deliveries');
    }
};

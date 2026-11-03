<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('event_type', 80);
            $table->nullableMorphs('subject');
            $table->string('title');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['created_by', 'customer_id', 'occurred_at'], 'customer_activity_tenant_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_activity_logs');
    }
};

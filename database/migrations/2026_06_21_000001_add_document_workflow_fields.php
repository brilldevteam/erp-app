<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->string('template_key', 32)->default('classic')->after('status');
            $table->json('document_snapshot')->nullable()->after('template_key');
            $table->timestamp('sent_at')->nullable()->after('document_snapshot');
            $table->timestamp('first_viewed_at')->nullable()->after('sent_at');
            $table->timestamp('last_viewed_at')->nullable()->after('first_viewed_at');
            $table->timestamp('last_reminded_at')->nullable()->after('last_viewed_at');
        });

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->string('template_key', 32)->default('classic')->after('status');
            $table->json('document_snapshot')->nullable()->after('template_key');
            $table->timestamp('sent_at')->nullable()->after('document_snapshot');
            $table->timestamp('first_viewed_at')->nullable()->after('sent_at');
            $table->timestamp('last_viewed_at')->nullable()->after('first_viewed_at');
            $table->timestamp('accepted_at')->nullable()->after('last_viewed_at');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->string('customer_action_name')->nullable()->after('rejected_at');
            $table->text('customer_action_comment')->nullable()->after('customer_action_name');
        });

        Schema::create('document_share_links', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->string('token_hash', 64)->unique();
            $table->unsignedBigInteger('created_by');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('last_ip', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['created_by', 'revoked_at']);
        });

        Schema::create('document_activities', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->string('action', 48);
            $table->string('actor_type', 24)->default('system');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['document_type', 'document_id', 'created_at'], 'document_activity_lookup');
        });

        Schema::create('document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->string('delivery_type', 24)->default('email');
            $table->string('recipient');
            $table->text('cc')->nullable();
            $table->text('bcc')->nullable();
            $table->string('subject');
            $table->longText('message')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('document_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->smallInteger('days_offset');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->unique(['invoice_id', 'days_offset']);
            $table->index(['is_active', 'days_offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reminders');
        Schema::dropIfExists('document_deliveries');
        Schema::dropIfExists('document_activities');
        Schema::dropIfExists('document_share_links');

        Schema::table('sales_quotations', function (Blueprint $table) {
            $table->dropColumn([
                'template_key', 'document_snapshot', 'sent_at', 'first_viewed_at',
                'last_viewed_at', 'accepted_at', 'rejected_at',
                'customer_action_name', 'customer_action_comment',
            ]);
        });

        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'template_key', 'document_snapshot', 'sent_at', 'first_viewed_at',
                'last_viewed_at', 'last_reminded_at',
            ]);
        });
    }
};

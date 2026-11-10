<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->foreignId('requester_user_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_agent_id')->nullable()->after('requester_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable()->after('resolved_at');
            $table->timestamp('closed_at')->nullable()->after('first_response_at');
            $table->timestamp('last_reply_at')->nullable()->after('closed_at');
            $table->softDeletes();
        });

        Schema::table('helpdesk_replies', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('created_by');
        });

        DB::table('helpdesk_replies')
            ->leftJoin('users', 'users.id', '=', 'helpdesk_replies.created_by')
            ->whereNull('helpdesk_replies.sender_name')
            ->update(['helpdesk_replies.sender_name' => DB::raw("COALESCE(users.name, 'Deleted user')")]);

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign('helpdesk_tickets_created_by_foreign');
            $table->foreign('created_by', 'helpdesk_tickets_created_by_foreign')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::table('helpdesk_replies', function (Blueprint $table) {
            $table->dropForeign('helpdesk_replies_created_by_foreign');
            $table->dropForeign('helpdesk_replies_ticket_id_foreign');
            $table->foreign('created_by', 'helpdesk_replies_created_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('ticket_id', 'helpdesk_replies_ticket_id_foreign')->references('id')->on('helpdesk_tickets')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE helpdesk_tickets MODIFY status ENUM('open','in_progress','waiting_customer','waiting_internal','resolved','closed') NOT NULL DEFAULT 'open'");
        }

        Schema::create('helpdesk_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->restrictOnDelete();
            $table->foreignId('from_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_agent_name')->nullable();
            $table->string('to_agent_name')->nullable();
            $table->string('changed_by_name')->nullable();
            $table->timestamps();
        });

        Schema::create('helpdesk_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('helpdesk_tickets')->restrictOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('helpdesk_status_histories');
        Schema::dropIfExists('helpdesk_assignment_histories');

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE helpdesk_tickets SET status = 'open' WHERE status IN ('waiting_customer','waiting_internal')");
            DB::statement("ALTER TABLE helpdesk_tickets MODIFY status ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open'");
        }

        Schema::table('helpdesk_replies', function (Blueprint $table) {
            $table->dropForeign('helpdesk_replies_created_by_foreign');
            $table->dropForeign('helpdesk_replies_ticket_id_foreign');
            $table->foreign('created_by', 'helpdesk_replies_created_by_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('ticket_id', 'helpdesk_replies_ticket_id_foreign')->references('id')->on('helpdesk_tickets')->cascadeOnDelete();
            $table->dropColumn('sender_name');
        });

        Schema::table('helpdesk_tickets', function (Blueprint $table) {
            $table->dropForeign('helpdesk_tickets_created_by_foreign');
            $table->foreign('created_by', 'helpdesk_tickets_created_by_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->dropForeign(['requester_user_id']);
            $table->dropForeign(['assigned_agent_id']);
            $table->dropColumn(['requester_user_id', 'assigned_agent_id', 'first_response_at', 'closed_at', 'last_reply_at', 'deleted_at']);
        });
    }
};

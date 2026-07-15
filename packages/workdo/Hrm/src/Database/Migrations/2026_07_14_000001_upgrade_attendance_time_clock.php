<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('work_status', 20)->default('completed')->after('status')->index();
            $table->unsignedInteger('elapsed_seconds')->default(0)->after('work_status');
            $table->unsignedInteger('unpaid_pause_seconds')->default(0)->after('elapsed_seconds');
            $table->unsignedInteger('paid_outside_seconds')->default(0)->after('unpaid_pause_seconds');
            $table->unsignedInteger('worked_seconds')->default(0)->after('paid_outside_seconds');
            $table->text('work_update')->nullable()->after('notes');
            $table->boolean('is_abnormally_long')->default(false)->after('work_update')->index();
            $table->boolean('is_manual')->default(false)->after('is_abnormally_long');
        });

        DB::table('attendances')->whereNull('clock_out')->update(['work_status' => 'working']);

        Schema::create('attendance_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->boolean('counts_as_work')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('created_by_user')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['attendance_id', 'ended_at']);
        });

        Schema::create('attendance_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->nullable()->constrained('attendances')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40)->index();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->index()->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('attendance_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendances')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('original_clock_in')->nullable();
            $table->dateTime('original_clock_out')->nullable();
            $table->dateTime('requested_clock_in')->nullable();
            $table->dateTime('requested_clock_out')->nullable();
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->index()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index(['created_by', 'status']);
        });

        $permissions = [
            'pause-attendance' => 'Pause and Resume Own Attendance',
            'update-own-work-update' => 'Update Own Daily Work Update',
            'request-attendance-correction' => 'Request Attendance Correction',
            'review-attendance-corrections' => 'Review Attendance Corrections',
            'export-attendances' => 'Export Attendances',
        ];
        foreach ($permissions as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], [
                'module' => 'attendances', 'label' => $label, 'add_on' => 'Hrm',
            ]);
        }
        Role::whereIn('name', ['company', 'hr'])->get()->each(fn (Role $role) => $role->givePermissionTo(array_keys($permissions)));
        Role::where('name', 'staff')->get()->each(fn (Role $role) => $role->givePermissionTo([
            'pause-attendance', 'update-own-work-update', 'request-attendance-correction',
        ]));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
        Schema::dropIfExists('attendance_action_logs');
        Schema::dropIfExists('attendance_intervals');

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'work_status', 'elapsed_seconds', 'unpaid_pause_seconds',
                'paid_outside_seconds', 'worked_seconds', 'work_update',
                'is_abnormally_long', 'is_manual',
            ]);
        });
    }
};

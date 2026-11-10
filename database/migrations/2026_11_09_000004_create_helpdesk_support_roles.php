<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $labels = [
            'view-company-helpdesk-tickets' => 'View Company Helpdesk Tickets',
            'assign-helpdesk-tickets' => 'Assign Helpdesk Tickets',
            'view-helpdesk-internal-notes' => 'View Helpdesk Internal Notes',
            'create-helpdesk-internal-notes' => 'Create Helpdesk Internal Notes',
            'view-all-support-tickets' => 'View All Support Tickets',
            'manage-support-team' => 'Manage Support Team',
            'view-helpdesk-records' => 'View Helpdesk Records',
        ];

        foreach ($labels as $name => $label) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['module' => 'helpdesk-tickets', 'label' => $label, 'add_on' => 'general']);
        }

        $superadmin = DB::table('users')->where('type', 'superadmin')->orderBy('id')->first();
        if (!$superadmin) {
            return;
        }

        $agent = Role::firstOrCreate(['name' => 'support-agent', 'guard_name' => 'web'], ['created_by' => $superadmin->id, 'label' => 'Support Agent', 'editable' => false]);
        $manager = Role::firstOrCreate(['name' => 'support-manager', 'guard_name' => 'web'], ['created_by' => $superadmin->id, 'label' => 'Support Manager', 'editable' => false]);

        $agent->syncPermissions(Permission::whereIn('name', [
            'manage-profile', 'edit-profile', 'change-password-profile',
            'manage-helpdesk-tickets', 'view-helpdesk-tickets', 'edit-helpdesk-tickets',
            'manage-helpdesk-replies', 'create-helpdesk-replies',
            'view-helpdesk-internal-notes', 'create-helpdesk-internal-notes', 'view-helpdesk-records',
        ])->get());

        $manager->syncPermissions(Permission::whereIn('name', [
            'manage-profile', 'edit-profile', 'change-password-profile',
            'manage-helpdesk-tickets', 'view-helpdesk-tickets', 'edit-helpdesk-tickets',
            'manage-helpdesk-replies', 'create-helpdesk-replies', 'delete-helpdesk-replies',
            'view-helpdesk-internal-notes', 'create-helpdesk-internal-notes', 'view-helpdesk-records',
            'view-all-support-tickets', 'assign-helpdesk-tickets', 'manage-support-team',
        ])->get());

        Role::where('name', 'superadmin')->get()->each(fn (Role $role) => $role->givePermissionTo(array_keys($labels)));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::whereIn('name', ['support-agent', 'support-manager'])->delete();
        Permission::whereIn('name', [
            'view-company-helpdesk-tickets', 'assign-helpdesk-tickets', 'view-helpdesk-internal-notes',
            'create-helpdesk-internal-notes', 'view-all-support-tickets', 'manage-support-team', 'view-helpdesk-records',
        ])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

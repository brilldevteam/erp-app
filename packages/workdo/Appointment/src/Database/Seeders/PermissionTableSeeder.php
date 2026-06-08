<?php

namespace Workdo\Appointment\Database\Seeders;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;

class PermissionTableSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();
        Artisan::call('cache:clear');

        $permission = [

            // Appointment dashboard management
            ['name' => 'manage-appointment-dashboard', 'module' => 'appointment', 'label' => 'Manage Appointment Dashboard'],

            // Single Appointment management
            ['name' => 'manage-appointment', 'module' => 'appointment', 'label' => 'Manage Appointment'],

            // Appointment management
            ['name' => 'manage-appointments', 'module' => 'appointments', 'label' => 'Manage Appointments'],
            ['name' => 'manage-any-appointments', 'module' => 'appointments', 'label' => 'Manage All Appointments'],
            ['name' => 'manage-own-appointments', 'module' => 'appointments', 'label' => 'Manage Own Appointments'],
            ['name' => 'create-appointments', 'module' => 'appointments', 'label' => 'Create Appointments'],
            ['name' => 'view-appointments', 'module' => 'appointments', 'label' => 'View Appointments'],
            ['name' => 'edit-appointments', 'module' => 'appointments', 'label' => 'Edit Appointments'],
            ['name' => 'delete-appointments', 'module' => 'appointments', 'label' => 'Delete Appointments'],
            ['name' => 'view-appointments-calendar', 'module' => 'appointments', 'label' => 'View Appointment Calendar'],
            ['name' => 'copy-appointment-link', 'module' => 'appointments', 'label' => 'Copy Appointment Link'],

            // Appointment Hours management
            ['name' => 'manage-appointment-hours', 'module' => 'appointments', 'label' => 'Manage Appointment Hours'],
            ['name' => 'create-appointment-hours', 'module' => 'appointments', 'label' => 'Create Appointment Hours'],

            // Questions management
            ['name' => 'manage-questions', 'module' => 'questions', 'label' => 'Manage Questions'],
            ['name' => 'manage-any-questions', 'module' => 'questions', 'label' => 'Manage All Questions'],
            ['name' => 'manage-own-questions', 'module' => 'questions', 'label' => 'Manage Own Questions'],
            ['name' => 'create-questions', 'module' => 'questions', 'label' => 'Create Questions'],
            ['name' => 'edit-questions', 'module' => 'questions', 'label' => 'Edit Questions'],
            ['name' => 'delete-questions', 'module' => 'questions', 'label' => 'Delete Questions'],


            // Schedules management
            ['name' => 'manage-schedules', 'module' => 'schedules', 'label' => 'Manage Schedules'],
            ['name' => 'manage-any-schedules', 'module' => 'schedules', 'label' => 'Manage All Schedules'],
            ['name' => 'manage-own-schedules', 'module' => 'schedules', 'label' => 'Manage Own Schedules'],
            ['name' => 'view-schedules', 'module' => 'schedules', 'label' => 'View Schedules'],
            ['name' => 'delete-schedules', 'module' => 'schedules', 'label' => 'Delete Schedules'],
            ['name' => 'schedule-actions', 'module' => 'schedules', 'label' => 'Schedule Actions'],

            // Appointment Callbacks management
            ['name' => 'manage-appointment-callbacks', 'module' => 'appointment-callbacks', 'label' => 'Manage Appointment Callbacks'],
            ['name' => 'manage-any-appointment-callbacks', 'module' => 'appointment-callbacks', 'label' => 'Manage All Appointment Callbacks'],
            ['name' => 'manage-own-appointment-callbacks', 'module' => 'appointment-callbacks', 'label' => 'Manage Own Appointment Callbacks'],
            ['name' => 'view-appointment-callbacks', 'module' => 'appointment-callbacks', 'label' => 'View Appointment Callbacks'],
            ['name' => 'delete-appointment-callbacks', 'module' => 'appointment-callbacks', 'label' => 'Delete Appointment Callbacks'],

            // Appointment Settings management
            ['name' => 'manage-appointment-settings', 'module' => 'appointment-settings', 'label' => 'Manage Appointment Settings'],
        ];

        $company_role = Role::where('name', 'company')->first();

        foreach ($permission as $perm) {
            $permission_obj = Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                [
                    'module' => $perm['module'],
                    'label' => $perm['label'],
                    'add_on' => 'Appointment',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            if ($company_role && !$company_role->hasPermissionTo($permission_obj)) {
                $company_role->givePermissionTo($permission_obj);
            }
        }
    }
}

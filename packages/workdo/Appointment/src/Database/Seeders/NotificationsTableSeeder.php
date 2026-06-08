<?php

namespace Workdo\Appointment\Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class NotificationsTableSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();
        
        // email notification
        $notifications = [
            'Appointment Booked',
            'Appointment Callback',
            'Appointment Status Update',
            'Appointment Callback Status Update'
        ];
        
        $permissions = [
            'manage-appointment',
            'manage-appointment',
            'manage-appointment',
            'manage-appointment'
        ];
        
        foreach($notifications as $key => $n) {
            $ntfy = Notification::where('action', $n)->where('type', 'mail')->where('module', 'Appointment')->count();
            if($ntfy == 0) {
                $new = new Notification();
                $new->action = $n;
                $new->status = 'on';
                $new->permissions = $permissions[$key];
                $new->module = 'Appointment';
                $new->type = 'mail';
                $new->save();
            }
        }
    }
}
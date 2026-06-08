<?php

namespace Workdo\Appointment\Database\Seeders;

use Workdo\Appointment\Models\AppointmentHour;
use Illuminate\Database\Seeder;

class DemoAppointmentHourSeeder extends Seeder
{
    public function run($userId): void
    {
        if (!empty($userId))
        {
            if (AppointmentHour::where('created_by', $userId)->exists()) {
                return;
            }
            
            $businessHours = [
                ['day_name' => 'monday', 'start_time' => '09:00', 'end_time' => '17:00', 'day_off' => false],
                ['day_name' => 'tuesday', 'start_time' => '09:00', 'end_time' => '17:00', 'day_off' => false],
                ['day_name' => 'wednesday', 'start_time' => '09:00', 'end_time' => '17:00', 'day_off' => false],
                ['day_name' => 'thursday', 'start_time' => '09:00', 'end_time' => '17:00', 'day_off' => false],
                ['day_name' => 'friday', 'start_time' => '09:00', 'end_time' => '16:00', 'day_off' => false],
                ['day_name' => 'saturday', 'start_time' => '10:00', 'end_time' => '14:00', 'day_off' => false],
                ['day_name' => 'sunday', 'start_time' => '00:00', 'end_time' => '00:00', 'day_off' => true],
            ];

            foreach ($businessHours as $hour) {
                AppointmentHour::create([
                    'day_name' => $hour['day_name'],
                    'start_time' => $hour['start_time'],
                    'end_time' => $hour['end_time'],
                    'day_off' => $hour['day_off'],
                    'creator_id' => $userId,
                    'created_by' => $userId,
                ]);
            }
        }
    }
}

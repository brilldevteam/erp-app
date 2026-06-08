<?php

namespace Workdo\Appointment\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class AppointmentDatabaseSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        $this->call(PermissionTableSeeder::class);
        $this->call(MarketplaceSettingSeeder::class);
        $this->call(EmailTemplatesSeeder::class);
        $this->call(NotificationsTableSeeder::class);

        if (config('app.run_demo_seeder')) {
            // Add here your demo data seeders
            $userId = User::where('email', 'company@example.com')->first()->id;
            (new DemoQuestionSeeder())->run($userId);
            (new DemoAppointmentSeeder())->run($userId);
            (new DemoScheduleSeeder())->run($userId);
            (new DemoAppointmentCallbackSeeder())->run($userId);
            (new DemoAppointmentHourSeeder())->run($userId);
            (new DemoAppointmentSettingsSeeder())->run($userId);
        }
    }
}

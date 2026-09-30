<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\InitialAccountCredentials;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        (new PermissionRoleSeeder())->run();
        (new DefultSetting())->run();
        (new PlanSeeder())->run();
        (new EmailTemplatesSeeder())->run();
        (new NotificationsTableSeeder())->run();

        $userId = User::where('email', 'company@example.com')->first()->id;
        User::CompanySetting($userId);

        if(config('app.run_demo_seeder'))
        {
            // // Pass $userId to your custom seeder


            (new CouponSeeder())->run();
            (new DemoUserSeeder())->run();

            (new DemoStaffSeeder())->run($userId);
            (new DemoLoginHistorySeeder())->run($userId);
            (new DemoWarehouseSeeder())->run($userId);
            (new HelpdeskCategorySeeder())->run();
            (new HelpdeskTicketSeeder())->run($userId);
            (new HelpdeskReplySeeder())->run($userId);
            (new DemoOrderSeeder())->run($userId);
            (new DemoCouponDetailsSeeder())->run();
            (new DemoBankTransferSeeder())->run($userId);
            (new MessengerSeeder())->run();
            (new AIAgentChatSessionSeeder())->run($userId);
            (new AIAgentChatMessageSeeder())->run($userId);

             // temporary
            // (new PackageSeeder())->run($userId);

            // in this seeder product
            (new DemoTransferSeeder())->run($userId);
        }

        // Hand the generated login passwords to the installer (shown once), and to the console when seeding directly.
        InitialAccountCredentials::persist();
        if ($this->command && InitialAccountCredentials::issued()) {
            $this->command->warn('Initial login credentials (store them safely; they are not shown again):');
            $this->command->table(['Email', 'Password'], collect(InitialAccountCredentials::issued())->map(fn ($password, $email) => [$email, $password])->values()->all());
        }
    }
}

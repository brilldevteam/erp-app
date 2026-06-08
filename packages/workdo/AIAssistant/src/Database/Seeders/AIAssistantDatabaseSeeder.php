<?php

namespace Workdo\AIAssistant\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class AIAssistantDatabaseSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        $this->call(PermissionTableSeeder::class);
        $this->call(AIPromptSeeder::class);
        $this->call(MarketplaceSettingSeeder::class);
    }
}

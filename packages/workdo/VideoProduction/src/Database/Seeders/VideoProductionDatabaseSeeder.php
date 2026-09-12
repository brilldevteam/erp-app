<?php

namespace Workdo\VideoProduction\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class VideoProductionDatabaseSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        $this->call(PermissionTableSeeder::class);
        $this->call(MarketplaceSettingSeeder::class);

    }
}

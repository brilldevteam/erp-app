<?php
namespace Workdo\PurchaseOrder\Database\Seeders; use Illuminate\Database\Seeder;
class PurchaseOrderDatabaseSeeder extends Seeder { public function run():void{$this->call(PermissionTableSeeder::class);} }

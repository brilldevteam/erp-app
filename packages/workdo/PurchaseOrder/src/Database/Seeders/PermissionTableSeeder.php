<?php
namespace Workdo\PurchaseOrder\Database\Seeders;
use Illuminate\Database\Seeder; use Spatie\Permission\Models\Permission; use Spatie\Permission\Models\Role;
class PermissionTableSeeder extends Seeder { public function run():void{$names=['manage','manage-any','manage-own','view','create','edit','delete','submit','approve','reject','issue','cancel','close','close-early','convert','print','email','export'];$company=Role::where('name','company')->first();foreach($names as $action){$name=$action.'-purchase-orders';$p=Permission::firstOrCreate(['name'=>$name,'guard_name'=>'web'],['module'=>'purchase-order','label'=>ucwords(str_replace('-',' ',$name)),'add_on'=>'PurchaseOrder']);if($company&&!$company->hasPermissionTo($p))$company->givePermissionTo($p);}} }

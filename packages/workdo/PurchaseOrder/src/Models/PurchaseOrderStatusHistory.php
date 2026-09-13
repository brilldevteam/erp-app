<?php
namespace Workdo\PurchaseOrder\Models; use Illuminate\Database\Eloquent\Model;
class PurchaseOrderStatusHistory extends Model { protected $guarded=[]; protected $casts=['metadata'=>'array']; public function user(){return $this->belongsTo(\App\Models\User::class);} }

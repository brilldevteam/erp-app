<?php
namespace Workdo\PurchaseOrder\Models; use Illuminate\Database\Eloquent\Model;
class PurchaseOrderApproval extends Model { protected $guarded=[]; protected $casts=['acted_at'=>'datetime']; public function approver(){return $this->belongsTo(\App\Models\User::class,'approver_user_id');} }

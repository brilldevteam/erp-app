<?php
namespace Workdo\PurchaseOrder\Models; use Illuminate\Database\Eloquent\Model;
class PurchaseOrderAttachment extends Model { protected $guarded=[]; public function uploader(){return $this->belongsTo(\App\Models\User::class,'uploaded_by');} }

<?php
namespace Workdo\PurchaseOrder\Models; use Illuminate\Database\Eloquent\Model;
class PurchaseOrderInvoiceLink extends Model { protected $guarded=[]; public function invoice(){return $this->belongsTo(\App\Models\PurchaseInvoice::class,'purchase_invoice_id');} }

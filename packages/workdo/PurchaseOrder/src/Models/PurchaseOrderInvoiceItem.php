<?php
namespace Workdo\PurchaseOrder\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderInvoiceItem extends Model { protected $guarded=[]; protected $casts=['quantity'=>'decimal:4']; }

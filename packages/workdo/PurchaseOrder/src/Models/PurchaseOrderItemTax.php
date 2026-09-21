<?php
namespace Workdo\PurchaseOrder\Models;
use Illuminate\Database\Eloquent\Model;
class PurchaseOrderItemTax extends Model { protected $guarded=[]; protected $casts=['tax_rate'=>'decimal:4','tax_amount'=>'decimal:2']; }

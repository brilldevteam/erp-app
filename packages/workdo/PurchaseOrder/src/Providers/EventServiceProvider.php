<?php
namespace Workdo\PurchaseOrder\Providers;
use App\Events\DestroyPurchaseInvoice; use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider; use Workdo\PurchaseOrder\Listeners\RemovePurchaseInvoiceAllocations;
class EventServiceProvider extends ServiceProvider { protected $listen=[DestroyPurchaseInvoice::class=>[RemovePurchaseInvoiceAllocations::class]]; }

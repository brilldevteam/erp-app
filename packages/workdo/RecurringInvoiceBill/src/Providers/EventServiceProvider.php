<?php

namespace Workdo\RecurringInvoiceBill\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Workdo\RecurringInvoiceBill\Listeners\CreateRecurringSalesInvoiceListener;
use Workdo\RecurringInvoiceBill\Listeners\CreateRecurringPurchaseInvoiceListener;
use Workdo\RecurringInvoiceBill\Listeners\UpdateRecurringSalesInvoiceListener;
use Workdo\RecurringInvoiceBill\Listeners\UpdateRecurringPurchaseInvoiceListener;
use Workdo\RecurringInvoiceBill\Listeners\DestroyRecurringSalesInvoiceListener;
use Workdo\RecurringInvoiceBill\Listeners\DestroyRecurringPurchaseInvoiceListener;
use App\Events\CreateSalesInvoice;
use App\Events\UpdateSalesInvoice;
use App\Events\DestroySalesInvoice;
use App\Events\EditSalesInvoice;
use App\Events\CreatePurchaseInvoice;
use App\Events\UpdatePurchaseInvoice;
use App\Events\DestroyPurchaseInvoice;
use App\Events\EditPurchaseInvoice;
use Workdo\RecurringInvoiceBill\Listeners\InvoiceRecurringDataListener;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        CreateSalesInvoice::class => [
            CreateRecurringSalesInvoiceListener::class
        ],
        UpdateSalesInvoice::class => [
            UpdateRecurringSalesInvoiceListener::class
        ],
        CreatePurchaseInvoice::class => [
            CreateRecurringPurchaseInvoiceListener::class
        ],
        UpdatePurchaseInvoice::class => [
            UpdateRecurringPurchaseInvoiceListener::class
        ],
        DestroySalesInvoice::class => [
            DestroyRecurringSalesInvoiceListener::class
        ],
        DestroyPurchaseInvoice::class => [
            DestroyRecurringPurchaseInvoiceListener::class
        ],
        EditSalesInvoice::class => [
           InvoiceRecurringDataListener::class
        ],
        EditPurchaseInvoice::class => [
           InvoiceRecurringDataListener::class
        ],
    ];
}

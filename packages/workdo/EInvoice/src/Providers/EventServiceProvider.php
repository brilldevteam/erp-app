<?php

namespace Workdo\EInvoice\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Events\UpdateCustomer;
use Workdo\EInvoice\Listeners\CreateCustomerLis;
use Workdo\EInvoice\Listeners\UpdateCustomerLis;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [        
        CreateCustomer::class => [
            CreateCustomerLis::class,
        ], 
        UpdateCustomer::class => [
            UpdateCustomerLis::class,
        ],
    ];
}
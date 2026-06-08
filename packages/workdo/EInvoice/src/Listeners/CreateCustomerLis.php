<?php

namespace Workdo\EInvoice\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Models\Customer;

class CreateCustomerLis
{
    public function handle(CreateCustomer $event)
    {
        if (Module_is_active('EInvoice')) {
            $request = $event->request;
            $customer = $event->customer;
            $newCustomer = Customer::find($customer->id);
            $newCustomer->electronic_address = $request->electronic_address;
            $newCustomer->electronic_address_scheme = $request->electronic_address_scheme;
            $newCustomer->save();
        }
    }
}

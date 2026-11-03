<?php

namespace Workdo\Account\Listeners;

use Workdo\Account\Events\CreateCustomer;
use Workdo\Account\Events\UpdateCustomer;
use Workdo\Account\Models\CustomerActivityLog;

class LogCustomerActivity
{
    public function handle(CreateCustomer|UpdateCustomer $event): void
    {
        $created = $event instanceof CreateCustomer;
        $allowedFields = [
            'company_name', 'contact_person_name', 'contact_person_email',
            'contact_person_mobile', 'tax_number', 'cr_number', 'payment_terms',
            'preferred_currency_code', 'billing_address', 'shipping_address',
            'same_as_billing', 'notes', 'portal_access_enabled',
        ];

        CustomerActivityLog::create([
            'customer_id' => $event->customer->id,
            'actor_id' => auth()->id(),
            'created_by' => $event->customer->created_by,
            'event_type' => $created ? 'customer.created' : 'customer.updated',
            'subject_type' => $event->customer->getMorphClass(),
            'subject_id' => $event->customer->id,
            'title' => $created ? __('Customer created') : __('Customer details updated'),
            'metadata' => $created ? null : [
                'changed_fields' => array_values(array_intersect($allowedFields, array_keys($event->request->all()))),
            ],
            'occurred_at' => now(),
        ]);
    }
}

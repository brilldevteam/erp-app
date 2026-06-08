<?php

namespace Workdo\RecurringInvoiceBill\Listeners;

use App\Events\DestroySalesInvoice;
use Workdo\RecurringInvoiceBill\Models\RecurringSalesPurchaseInvoice;

class DestroyRecurringSalesInvoiceListener
{
    public function handle(DestroySalesInvoice $event)
    {
        if(Module_is_active('RecurringInvoiceBill'))
        {
            $recurringInvoice = RecurringSalesPurchaseInvoice::where('invoice_id', $event->salesInvoice->id)
                ->where('invoice_type', 'sales')
                ->where('created_by', creatorId())
                ->first();

            if ($recurringInvoice) {
                $recurringInvoice->delete();
            }
        }
    }
}

<?php

namespace Workdo\RecurringInvoiceBill\Listeners;

use App\Events\DestroyPurchaseInvoice;
use Workdo\RecurringInvoiceBill\Models\RecurringSalesPurchaseInvoice;

class DestroyRecurringPurchaseInvoiceListener
{
    public function handle(DestroyPurchaseInvoice $event)
    {
        if(Module_is_active('RecurringInvoiceBill'))
        {
            $recurringInvoice = RecurringSalesPurchaseInvoice::where('invoice_id', $event->purchaseInvoice->id)
                ->where('invoice_type', 'purchase')
                ->where('created_by', creatorId())
                ->first();

            if ($recurringInvoice) {
                $recurringInvoice->delete();
            }
        }
    }
}
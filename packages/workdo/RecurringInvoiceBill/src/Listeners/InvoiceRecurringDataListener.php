<?php

namespace Workdo\RecurringInvoiceBill\Listeners;

use Workdo\RecurringInvoiceBill\Models\RecurringSalesPurchaseInvoice;

class InvoiceRecurringDataListener
{
    public function handle($event)
    {
        if (isset($event->invoice) && $event->invoice->id) {
            $invoiceType = $this->getInvoiceType($event);
            
            if ($invoiceType) {
                $recurringData = RecurringSalesPurchaseInvoice::where('invoice_id', $event->invoice->id)
                    ->where('invoice_type', $invoiceType)
                    ->where('created_by', creatorId())
                    ->first();
                
                $event->invoice->recurring_data = $recurringData;
            }
        }
    }

    private function getInvoiceType($event): ?string
    {
        $className = class_basename($event);
        
        if (str_contains($className, 'Sales')) {
            return 'sales';
        } elseif (str_contains($className, 'Purchase')) {
            return 'purchase';
        }
        
        return null;
    }
}
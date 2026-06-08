<?php

namespace Workdo\RecurringInvoiceBill\Listeners;

use App\Events\CreatePurchaseInvoice;
use Workdo\RecurringInvoiceBill\Models\RecurringSalesPurchaseInvoice;
use DateTime;
use DateInterval;

class CreateRecurringPurchaseInvoiceListener
{
    public function handle(CreatePurchaseInvoice $event)
    {
        if(Module_is_active('RecurringInvoiceBill'))
        {
            $request = $event->request;
            $invoice = $event->purchaseInvoice;

            if (empty($request->recurring_duration) || $request->recurring_duration === 'no') {
                return;
            }

            if ($request->recurring_duration !== 'custom') {
                $parts      = explode(' ', $request->recurring_duration);
                $count      = $parts[0];
                $dayType    = $parts[1];
            } else {
                $count      = $request->count;
                $dayType    = $request->day_type;
            }

            $date       = new DateTime($invoice->invoice_date);
            $dueDate    = new DateTime($invoice->due_date);

            $interval = match ($dayType) {
                'day'       => new DateInterval('P' . $count . 'D'),
                'week'      => new DateInterval('P' . $count . 'W'),
                'month'     => new DateInterval('P' . $count . 'M'),
                'year'      => new DateInterval('P' . $count . 'Y'),
                default     => new DateInterval('P1M')
            };

            $date->add($interval);
            $dueDate->add($interval);

            $cycles = !empty($request->unlimited_cycles) ? 9999 : $request->cycles;

            $recurringInvoice                       = new RecurringSalesPurchaseInvoice();
            $recurringInvoice->invoice_id           = $invoice->id;
            $recurringInvoice->invoice_type         = 'purchase';
            $recurringInvoice->recurring_duration   = $request->recurring_duration;
            $recurringInvoice->cycles               = $cycles;
            $recurringInvoice->day_type             = $dayType;
            $recurringInvoice->count                = $count;
            $recurringInvoice->pending_cycle        = $cycles;
            $recurringInvoice->modify_date          = $date->format('Y-m-d');
            $recurringInvoice->modify_due_date      = $dueDate->format('Y-m-d');
            $recurringInvoice->creator_id           = creatorId();
            $recurringInvoice->created_by           = creatorId();
            $recurringInvoice->save();
        }
    }
}

<?php

namespace Workdo\RecurringInvoiceBill\Listeners;

use App\Events\UpdatePurchaseInvoice;
use Workdo\RecurringInvoiceBill\Models\RecurringSalesPurchaseInvoice;
use DateTime;
use DateInterval;

class UpdateRecurringPurchaseInvoiceListener
{
    public function handle(UpdatePurchaseInvoice $event)
    {
        if(Module_is_active('RecurringInvoiceBill'))
        {
            $request = $event->request;
            $invoice = $event->purchaseInvoice;

            $existing = RecurringSalesPurchaseInvoice::where('invoice_id', $invoice->id)
                ->where('invoice_type', 'purchase')
                ->first();

            if (empty($request->recurring_duration) || $request->recurring_duration === 'no') {
                if ($existing) {
                    $existing->delete();
                }
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

            $data = [
                'recurring_duration'    => $request->recurring_duration,
                'cycles'                => $cycles,
                'day_type'              => $dayType,
                'count'                 => $count,
                'pending_cycle'         => $cycles,
                'modify_date'           => $date->format('Y-m-d'),
                'modify_due_date'       => $dueDate->format('Y-m-d'),
            ];

            if ($existing) {
                $existing->update($data);
            } else {
                $recurringInvoice                       = new RecurringSalesPurchaseInvoice();
                $recurringInvoice->invoice_id           = $invoice->id;
                $recurringInvoice->invoice_type         = 'purchase';
                $recurringInvoice->recurring_duration   = $data['recurring_duration'];
                $recurringInvoice->cycles               = $data['cycles'];
                $recurringInvoice->day_type             = $data['day_type'];
                $recurringInvoice->count                = $data['count'];
                $recurringInvoice->pending_cycle        = $data['pending_cycle'];
                $recurringInvoice->modify_date          = $data['modify_date'];
                $recurringInvoice->modify_due_date      = $data['modify_due_date'];
                $recurringInvoice->creator_id           = creatorId();
                $recurringInvoice->created_by           = creatorId();
                $recurringInvoice->save();
            }
        }
    }
}

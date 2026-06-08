<?php

namespace Workdo\RecurringInvoiceBill\Console;

use Illuminate\Console\Command;
use Workdo\RecurringInvoiceBill\Models\RecurringSalesPurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use DateTime;
use DateInterval;

class RecurringSalesPurchaseCommand extends Command
{
    protected $signature    = 'recurring:sales-purchase-invoices';
    protected $description  = 'Generate recurring sales and purchase invoices';

    public function handle()
    {
        $superadmin = getAdminAllSetting();
        if (empty($superadmin['recurring_sales_purchase_invoices']) || $superadmin['recurring_sales_purchase_invoices'] !== 'on') {
            return;
        }

        $recurringData = RecurringSalesPurchaseInvoice::where('modify_date', '<=', date('Y-m-d'))
            ->where('pending_cycle', '>', 0)
            ->get();

        foreach ($recurringData as $data) {
            $setting = getCompanyAllSetting($data->created_by);
            if (empty($setting['recurring_sales_purchase_invoices']) || $setting['recurring_sales_purchase_invoices'] !== 'on') {
                continue;
            }

            if ($data->invoice_type === 'sales') {
                $this->generateSalesInvoice($data);
            } else {
                $this->generatePurchaseInvoice($data);
            }
        }
    }

    private function generateSalesInvoice($data)
    {
        $originalInvoice = SalesInvoice::where('id', $data->invoice_id)
            ->where('created_by', $data->created_by)
            ->first();

        if (!$originalInvoice) return;

        try {
            $newInvoice                 = $originalInvoice->replicate();
            $newInvoice->invoice_date   = $data->modify_date;
            $newInvoice->due_date       = $data->modify_due_date;
            $newInvoice->status         = 'draft';
            $newInvoice->save();

            foreach ($originalInvoice->items as $item) {
                $newItem                = $item->replicate();
                $newItem->invoice_id    = $newInvoice->id;
                $newItem->save();
            }

            $this->updateRecurringData($data, $newInvoice->id);

        } catch (\Exception $e) {
            \Log::error('Failed to generate recurring sales invoice: ' . $e->getMessage());
        }
    }

    private function generatePurchaseInvoice($data)
    {
        $originalInvoice = PurchaseInvoice::where('id', $data->invoice_id)
            ->where('created_by', $data->created_by)
            ->first();

        if (!$originalInvoice) return;

        try {
            $newInvoice                 = $originalInvoice->replicate();
            $newInvoice->invoice_date   = $data->modify_date;
            $newInvoice->due_date       = $data->modify_due_date;
            $newInvoice->status         = 'draft';
            $newInvoice->save();

            foreach ($originalInvoice->items as $item) {
                $newItem                = $item->replicate();
                $newItem->invoice_id    = $newInvoice->id;
                $newItem->save();
            }

            $this->updateRecurringData($data, $newInvoice->id);

        } catch (\Exception $e) {
            \Log::error('Failed to generate recurring purchase invoice: ' . $e->getMessage());
        }
    }

    private function updateRecurringData($data, $newInvoiceId)
    {
        $date       = new DateTime($data->modify_date);
        $dueDate    = new DateTime($data->modify_due_date);

        $interval = match ($data->day_type) {
            'day' => new DateInterval('P' . $data->count . 'D'),
            'week' => new DateInterval('P' . $data->count . 'W'),
            'month' => new DateInterval('P' . $data->count . 'M'),
            'year' => new DateInterval('P' . $data->count . 'Y'),
            default => new DateInterval('P1M')
        };

        $date->add($interval);
        $dueDate->add($interval);

        $duplicateIds   = $data->duplicate_invoices ? explode(',', $data->duplicate_invoices) : [];
        $duplicateIds[] = $newInvoiceId;

        $data->update([
            'pending_cycle'         => $data->pending_cycle - 1,
            'modify_date'           => $date->format('Y-m-d'),
            'modify_due_date'       => $dueDate->format('Y-m-d'),
            'duplicate_invoices'    => implode(',', $duplicateIds)
        ]);
    }
}

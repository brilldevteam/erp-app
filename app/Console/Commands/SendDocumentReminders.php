<?php

namespace App\Console\Commands;

use App\Models\DocumentReminder;
use App\Models\SalesInvoice;
use App\Services\Documents\DocumentDeliveryService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SendDocumentReminders extends Command
{
    protected $signature = 'documents:send-reminders {--date= : Date to process (YYYY-MM-DD)}';
    protected $description = 'Send scheduled invoice reminders based on company reminder offsets';

    public function handle(DocumentDeliveryService $deliveries): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date'))->startOfDay() : today();
        $sent = 0;

        SalesInvoice::with('customer')
            ->where('balance_amount', '>', 0)
            ->whereIn('status', ['posted', 'partial'])
            ->whereNotNull('due_date')
            ->chunkById(100, function ($invoices) use ($today, $deliveries, &$sent) {
                foreach ($invoices as $invoice) {
                    $offsets = array_unique(array_map('intval', explode(',', company_setting('invoice_reminder_offsets', $invoice->created_by) ?: '-3,0,3,7')));
                    $daysFromDue = (int) $invoice->due_date->startOfDay()->diffInDays($today, false);
                    if (!in_array($daysFromDue, $offsets, true) || !$invoice->customer?->email) {
                        continue;
                    }

                    $reminder = DocumentReminder::firstOrCreate([
                        'invoice_id' => $invoice->id,
                        'days_offset' => $daysFromDue,
                    ], [
                        'is_active' => true,
                        'created_by' => $invoice->created_by,
                    ]);
                    if (!$reminder->is_active || $reminder->last_sent_at?->isSameDay($today)) {
                        continue;
                    }

                    $delivery = $deliveries->send('invoice', $invoice, [
                        'recipient' => $invoice->customer->email,
                        'subject' => __('Payment reminder: invoice :number', ['number' => $invoice->invoice_number]),
                        'message' => __('Invoice :number has an outstanding balance of :amount. View and pay securely: {document_link}', [
                            'number' => $invoice->invoice_number,
                            'amount' => number_format((float) $invoice->balance_amount, 2),
                        ]),
                    ], true);

                    if ($delivery->status === 'sent') {
                        $reminder->update(['last_sent_at' => now()]);
                        $sent++;
                    }
                }
            });

        $this->info(__('Sent :count invoice reminder(s).', ['count' => $sent]));

        return self::SUCCESS;
    }
}

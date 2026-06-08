<?php

namespace Workdo\Retainer\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\Retainer\Models\SalesRetainer;
use App\Models\SalesInvoice;

class ConvertSalesRetainer
{
    use Dispatchable;
    
    public function __construct(
        public SalesRetainer $retainer,
        public SalesInvoice $invoice
    ) {}
}
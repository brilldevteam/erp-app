<?php

namespace Workdo\Retainer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Workdo\Retainer\Models\SalesRetainerPayment;

class UpdateRetainerPaymentStatus
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public SalesRetainerPayment $retainerPayment
    ) {}
}
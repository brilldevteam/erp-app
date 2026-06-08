<?php

namespace Workdo\Retainer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\Retainer\Models\SalesRetainerPayment;

class DestroyRetainerPayment
{
    use Dispatchable;

    public function __construct(
        public SalesRetainerPayment $retainerPayment
    ) {}
}
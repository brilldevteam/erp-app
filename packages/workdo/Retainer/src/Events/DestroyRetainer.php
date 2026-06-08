<?php

namespace Workdo\Retainer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Retainer\Models\SalesRetainer;

class DestroyRetainer
{
    use Dispatchable;

    public function __construct(
        public SalesRetainer $retainer
    ) {}
}
<?php

namespace Workdo\Retainer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Retainer\Models\SalesRetainer;
use Illuminate\Http\Request;

class CreateRetainer
{

    use Dispatchable;

    public function __construct(
        public Request $request,
        public SalesRetainer $retainer
    ) {}
}
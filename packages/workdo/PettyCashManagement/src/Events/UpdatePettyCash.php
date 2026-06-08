<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCash;
use Illuminate\Http\Request;

class UpdatePettyCash
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Request $request,
        public PettyCash $pettycash
    )
    {}
}
<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\PettyCashManagement\Models\PettyCash;

class ApprovePettyCash
{
    use Dispatchable;

    public function __construct(
        public PettyCash $pettycash
    )
    {}
}

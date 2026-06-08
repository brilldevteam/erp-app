<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCash;

class DestroyPettyCash
{
    use Dispatchable, SerializesModels;

    public function __construct(public PettyCash $pettycash)
    {}
}

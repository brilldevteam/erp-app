<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCashRequest;

class UpdateStatusPettyCashRequest
{
    use Dispatchable, SerializesModels;

    public function __construct(public PettyCashRequest $pettycashrequest)
    {}
}

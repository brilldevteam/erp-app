<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCashRequest;
use Illuminate\Http\Request;

class CreatePettyCashRequest
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Request $request,
        public PettyCashRequest $pettycashrequest
    )
    {}
}
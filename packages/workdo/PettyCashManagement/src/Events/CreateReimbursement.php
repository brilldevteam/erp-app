<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCashReimbursement;
use Illuminate\Http\Request;

class CreateReimbursement
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Request $request,
        public PettyCashReimbursement $reimbursement
    )
    {}
}

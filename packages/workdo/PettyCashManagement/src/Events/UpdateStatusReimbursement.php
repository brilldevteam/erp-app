<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCashReimbursement;

class UpdateStatusReimbursement
{
    use Dispatchable, SerializesModels;

    public function __construct(public PettyCashReimbursement $reimbursement)
    {}
}

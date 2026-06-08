<?php

namespace Workdo\ContractTemplate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Workdo\Contract\Models\Contract;

class UpdateContractTemplate
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Contract $template
    ) {}
}
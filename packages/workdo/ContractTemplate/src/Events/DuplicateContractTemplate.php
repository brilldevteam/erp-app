<?php

namespace Workdo\ContractTemplate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Workdo\Contract\Models\Contract;

class DuplicateContractTemplate
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Contract $originalTemplate,
        public Contract $newTemplate
    ) {}
}
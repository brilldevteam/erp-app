<?php

namespace Workdo\ContractTemplate\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Contract\Models\Contract;

class DestroyContractTemplate
{
    use Dispatchable;

    public function __construct(
        public Contract $template
    ) {}
}
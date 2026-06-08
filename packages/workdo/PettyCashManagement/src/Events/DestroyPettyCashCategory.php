<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCashCategory;

class DestroyPettyCashCategory
{
    use Dispatchable, SerializesModels;

    public function __construct(public PettyCashCategory $pettycashcategory)
    {}
}

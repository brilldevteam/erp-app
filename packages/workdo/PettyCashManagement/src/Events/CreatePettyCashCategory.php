<?php

namespace Workdo\PettyCashManagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Workdo\PettyCashManagement\Models\PettyCashCategory;
use Illuminate\Http\Request;

class CreatePettyCashCategory
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Request $request,
        public PettyCashCategory $pettycashcategory
    )
    {}
}
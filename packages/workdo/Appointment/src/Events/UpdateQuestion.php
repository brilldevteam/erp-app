<?php

namespace Workdo\Appointment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Workdo\Appointment\Models\Question;

class UpdateQuestion
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Question $question
    ) {}
}
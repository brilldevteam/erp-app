<?php

namespace Workdo\Appointment\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Appointment\Models\Question;

class DestroyQuestion
{
    use Dispatchable;

    public function __construct(
        public Question $question
    ) {}
}
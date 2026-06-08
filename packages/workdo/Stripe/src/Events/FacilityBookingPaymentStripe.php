<?php

namespace Workdo\Stripe\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Facilities\Models\FacilityBooking;

class FacilityBookingPaymentStripe
{
    use Dispatchable;

    public function __construct(
        public FacilityBooking $booking,
    ) {
    }
}

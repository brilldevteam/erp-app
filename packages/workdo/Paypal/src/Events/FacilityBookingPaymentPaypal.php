<?php

namespace Workdo\Paypal\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Workdo\Facilities\Models\FacilityBooking;

class FacilityBookingPaymentPaypal
{
    use Dispatchable;

    public function __construct(
        public FacilityBooking $booking
    ) {
    }
}

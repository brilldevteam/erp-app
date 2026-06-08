<?php

namespace Workdo\Workflow\Listeners;

use Workdo\Holidayz\Events\CreateHolidayzRoomBooking;
use Workdo\Workflow\Services\WorkflowActionService;
use Carbon\Carbon;

class CreateHolidayzRoomBookingLis
{
    public function __construct()
    {
        //
    }

    public function handle(CreateHolidayzRoomBooking $event)
    {
        $booking = $event->booking;

        // Load items to get check-in and check-out dates
        $booking->load('items');

        // Calculate total days from all booking items
        $days = 0;
        if ($booking->items->isNotEmpty()) {
            // Get earliest check-in and latest check-out from all items
            $checkInDate = $booking->items->min('check_in_date');
            $checkOutDate = $booking->items->max('check_out_date');
            
            if ($checkInDate && $checkOutDate) {
                $checkIn = Carbon::parse($checkInDate);
                $checkOut = Carbon::parse($checkOutDate);
                $days = $checkIn->diffInDays($checkOut);
            }
        }

        $data = [
            'Customer' => $booking->customer_id,
            'Price' => $booking->total_amount,
            'Days' => $days,
        ];
        WorkflowActionService::processWorkflow('Holidayz', 'Room Booking', $data, $booking->created_by);
    }
}

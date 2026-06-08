<?php

namespace Workdo\Stripe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Order;
use Illuminate\Support\Facades\Session;
use Workdo\Stripe\Events\StripePaymentStatus;
use Workdo\Bookings\Models\BookingAppointment;
use Workdo\Bookings\Models\BookingPackage;
use Workdo\Bookings\Models\BookingCustomer;
use Workdo\LaundryManagement\Models\LaundryRequest;
use Workdo\Holidayz\Models\HolidayzCart;
use Workdo\Holidayz\Models\HolidayzRoomBooking;
use Workdo\Holidayz\Models\HolidayzRoomBookingItem;
use Workdo\Holidayz\Models\HolidayzCoupon;
use Workdo\Holidayz\Models\HolidayzCouponUsage;
use Workdo\Holidayz\Helpers\HolidayzAvailabilityHelper;

use Workdo\LMS\Models\LMSCart;
use Workdo\LMS\Models\LMSOrder;
use Workdo\LMS\Models\LMSOrderItem;
use Workdo\LMS\Models\LMSCoupon;
use Inertia\Inertia;
use Stripe\Stripe;
use Workdo\BeautySpaManagement\Models\BeautyBooking;
use Workdo\BeautySpaManagement\Models\BeautyService;
use Workdo\BeautySpaManagement\Models\BeautyBookingReceipt;
use Stripe\StripeClient;
use Workdo\ArtShowcase\Events\CreateArtWorkOrderPayment;
use Workdo\ArtShowcase\Models\ArtShowcaseArtWork;
use Workdo\ArtShowcase\Models\ArtShowcaseArtWorkOrder;
use Workdo\BeautySpaManagement\Events\BeautyBookingPayments;
use Workdo\BeautySpaManagement\Models\BeautyServiceOffer;
use Workdo\Bookings\Events\BookingAppointmentPayments;
use Workdo\CoworkingSpaceManagement\Events\CoworkingBookingPayments;
use Workdo\CoworkingSpaceManagement\Events\CoworkingMembershipPayments;
use Workdo\CoworkingSpaceManagement\Http\Controllers\CoworkingMembershipController;
use Workdo\CoworkingSpaceManagement\Models\CoworkingBooking;
use Workdo\CoworkingSpaceManagement\Models\CoworkingMembership;
use Workdo\CoworkingSpaceManagement\Models\CoworkingMembershipPlan;
use Workdo\EventsManagement\Events\EventBookingPayments;
use Workdo\ParkingManagement\Models\ParkingBooking;
use Workdo\LaundryManagement\Events\LaundryBookingPayments;
use Workdo\EventsManagement\Models\Event;
use Workdo\EventsManagement\Models\EventBooking;
use Workdo\EventsManagement\Models\EventBookingPayment;

use Workdo\Facilities\Services\FacilitiesBookingService;
use Workdo\Holidayz\Events\HolidayzBookingPayments;
use Workdo\InfluencerMarketing\Models\InfluencerMarketingDeposit;
use Workdo\InfluencerMarketing\Events\InfluencerMarketingPayment;
use Workdo\LMS\Events\LMSOrderPayments;
use Workdo\NGOManagment\Events\CreateNgoDonation;
use Workdo\NGOManagment\Http\Controllers\DonationController;
use Workdo\NGOManagment\Models\NgoCampaign;
use Workdo\NGOManagment\Models\NgoDonation;
use Workdo\NGOManagment\Models\NgoDonor;
use Workdo\MovieShowBookingSystem\Events\MovieBookingPayments;
use Workdo\MovieShowBookingSystem\Models\MovieBooking;
use Workdo\ParkingManagement\Events\ParkingBookingPayments;
use Workdo\SportsClubAndAcademyManagement\Events\SportsClubBookingPayments;
use Workdo\SportsClubAndAcademyManagement\Events\SportsClubPlanPayments;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubAndGroundOrder;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubAssignedMembership;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubBookingFacility;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubFacility;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubGround;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubMember;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubMembershipPlan;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubMembershipPlanPayment;
use Workdo\Stripe\Events\FacilityBookingPaymentStripe;

use Workdo\VehicleBookingManagement\Events\VehicleBookingPayments;
use Workdo\VehicleBookingManagement\Models\VehicleBooking;
use Workdo\WaterParkManagement\Events\WaterParkBookingPaymentStripe;
use Workdo\WaterParkManagement\Models\WaterParkBooking;

use Workdo\TVStudio\Services\TVStudioCheckoutService;
use Workdo\TattooStudioManagement\Models\TattooAppointment;
use Workdo\TattooStudioManagement\Events\TattooAppointmentPaymentStripe;

use Workdo\PhotoStudioManagement\Models\PhotoStudioAppointment;
use Workdo\PhotoStudioManagement\Models\PhotoStudioAppointmentPayment;
use Workdo\PhotoStudioManagement\Models\PhotoStudioService;
use Workdo\PhotoStudioManagement\Events\PhotoStudioAppointmentPayments;
use Workdo\Ebook\Models\EbookBookOrder;
use Workdo\Ebook\Events\EbookPayment;

use Workdo\YogaClasses\Models\YogaClassesCart;
use Workdo\YogaClasses\Models\YogaClassesOrder;
use Workdo\YogaClasses\Models\YogaClassesPurchasedCourse;
use Workdo\YogaClasses\Events\YogaClassesOrderPayments;

use Workdo\HairAndCareStudio\Models\HairCareAppointment;
use Workdo\HairAndCareStudio\Models\HairCarePayment;
use Workdo\HairAndCareStudio\Events\HairCareStudioOrderPayments;

use Workdo\PetCare\Models\PetCareGroomingPackage;
use Workdo\PetCare\Models\PetCareMembership;
use Workdo\PetCare\Events\PetCareMembershipPayments;
use Workdo\PetCare\Models\PetCareService;
use Workdo\PetCare\Models\PetCareBooking;
use Workdo\PetCare\Events\PetCareBookingPayment;

use Workdo\BoutiqueAndDesignerStudio\Models\BoutiqueBooking;
use Workdo\BoutiqueAndDesignerStudio\Events\BoutiqueBookingPaymentStripe;
use Workdo\LaundryManagement\Models\LaundryInvoice;
use Workdo\LaundryManagement\Models\LaundryPayment;

use Workdo\InvestmentSystem\Models\InvestorDeposit;
use Workdo\InvestmentSystem\Models\InvestmentPlan;
use Workdo\InvestmentSystem\Models\InvestorTransaction;
use Workdo\InvestmentSystem\Events\InvestorDepositPayment;

class StripeController extends Controller
{
    public function planPayWithStripe(Request $request)
    {
        $plan = Plan::find($request->plan_id);
        $user = User::find($request->user_id);
        $admin_settings = getAdminAllSetting();
        $admin_currancy = !empty($admin_settings['defaultCurrency']) ? $admin_settings['defaultCurrency'] : '';
        $user_counter = !empty($request->user_counter_input) ? $request->user_counter_input : 0;
        $user_module = !empty($request->user_module_input) ? $request->user_module_input : '';
        $storage_limit = !empty($request->storage_limit_input) ? $request->storage_limit_input : 0;
        $duration = !empty($request->time_period) ? $request->time_period : 'Month';
        $user_module_price = 0;

        if (!empty($user_module) && $plan->custom_plan == 1) {
            $user_module_array = explode(',', $user_module);
            foreach ($user_module_array as $key => $value) {
                $temp = ($duration == 'Year') ? ModulePriceByName($value)['yearly_price'] : ModulePriceByName($value)['monthly_price'];
                $user_module_price = $user_module_price + $temp;
            }
        }
        $user_price = 0;
        if ($user_counter > 0) {
            $temp = ($duration == 'Year') ? $plan->price_per_user_yearly : $plan->price_per_user_monthly;
            $user_price = $user_counter * $temp;
        }

        $storage_price = 0;
        if ($storage_limit > 0 && $plan->custom_plan == 1) {
            $temp = ($duration == 'Year') ? $plan->price_per_storage_yearly : $plan->price_per_storage_monthly;
            $storage_price = $storage_limit * $temp;
        }

        $plan_price = ($duration == 'Year') ? $plan->package_price_yearly : $plan->package_price_monthly;
        $counter = [
            'user_counter' => $user_counter,
            'storage_limit' => $storage_limit,
        ];

        $stripe_session = '';
        $orderID = strtoupper(substr(uniqid(), -12));

        if ($plan) {
            /* Check for code usage */
            $plan->discounted_price = false;
            $payment_frequency = $plan->duration;
            $price = $plan_price + $user_module_price + $user_price + $storage_price;

            if ($request->coupon_code) {
                $validation = applyCouponDiscount($request->coupon_code, $price, auth()->id());
                if ($validation['valid']) {
                    $price = $validation['final_amount'];
                }
            }
            if ($price <= 0) {
                $assignPlan = assignPlan($plan->id, $duration, $user_module, $counter, $request->user_id);
                if ($assignPlan['is_success']) {
                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully!'));
                } else {
                    return redirect()->route('plans.index')->with('error', __('Something went wrong, Please try again,'));
                }
            }

            try {

                $payment_plan = $duration;
                $payment_type = 'onetime';
                /* Payment details */
                $code = '';

                /* Final price */
                $stripe_formatted_price = in_array(
                    $admin_currancy,
                    [
                        'MGA',
                        'BIF',
                        'CLP',
                        'PYG',
                        'DJF',
                        'RWF',
                        'GNF',
                        'UGX',
                        'JPY',
                        'VND',
                        'VUV',
                        'XAF',
                        'KMF',
                        'KRW',
                        'XOF',
                        'XPF',
                        'BRL'
                    ]
                ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;
                $return_url_parameters = function ($return_type) use ($payment_frequency, $payment_type) {
                    return '&return_type=' . $return_type . '&payment_processor=stripe&payment_frequency=' . $payment_frequency . '&payment_type=' . $payment_type;
                };
                /* Initiate Stripe */
                $stripe_session = $this->createStripeSession([
                    'api_key' => $admin_settings['stripe_secret'] ?? '',
                    'currency' => $admin_currancy,
                    'amount' => $stripe_formatted_price,
                    'product_name' => $plan->name ?? 'Basic Package',
                    'description' => $payment_plan,
                    'metadata' => [
                        'user_id' => $user->id,
                        'package_id' => $plan->id,
                        'payment_frequency' => $payment_frequency,
                        'code' => $code,
                    ],
                    'success_url' => route('payment.stripe.status', [
                        'order_id' => $orderID,
                        'plan_id' => $plan->id,
                        'user_module' => $user_module,
                        'duration' => $duration,
                        'counter' => $counter,
                        'coupon_code' => $request->coupon_code,
                        'user_id' => $user->id,
                        $return_url_parameters('success'),
                    ]),
                    'cancel_url' => route('payment.stripe.status', [
                        'plan_id' => $plan->id,
                        'order_id' => $orderID,
                        $return_url_parameters('cancel'),
                    ]),
                ]);

                $order = new Order();
                $order->order_id = $orderID;
                $order->name = $user->name ?? '';
                $order->email = $user->email ?? '';
                $order->card_number = null;
                $order->card_exp_month = null;
                $order->card_exp_year = null;
                $order->plan_name = !empty($plan->name) ? $plan->name : 'Basic Package';
                $order->plan_id = $plan->id;
                $order->price = !empty($price) ? $price : 0;
                $order->currency = $admin_currancy;
                $order->txn_id = '';
                $order->payment_type = 'Stripe';
                $order->payment_status = 'pending';
                $order->receipt = null;
                $order->created_by = $user->id;
                $order->save();

                Session::put('stripe_session', $stripe_session);
                $stripe_session = $stripe_session ?? false;
            } catch (\Exception $e) {
                return redirect()->route('plans.index')->with('error', $e->getMessage());
            }
            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $admin_settings['stripe_key'] ?? ''
            ]);
        } else {
            return redirect()->route('plans.index')->with('error', __('The Plan has been deleted.'));
        }
    }

    public function planGetStripeStatus(Request $request)
    {
        $admin_settings = getAdminAllSetting();
        try {
            $stripe = new StripeClient(!empty($admin_settings['stripe_secret']) ? $admin_settings['stripe_secret'] : '');
            $stripe_session = Session::get('stripe_session');
            if ($stripe_session && isset($stripe_session->payment_intent)) {
                $paymentIntents = $stripe->paymentIntents->retrieve(
                    $stripe_session->payment_intent,
                    []
                );
                $receipt_url = $paymentIntents->charges->data[0]->receipt_url;
            } else {
                $receipt_url = "";
            }
        } catch (\Exception $exception) {
            $receipt_url = "";
        }
        Session::forget('stripe_session');
        try {
            if ($request->return_type == 'success') {
                $Order = Order::where('order_id', $request->order_id)->first();
                $Order->payment_status = 'succeeded';
                $Order->receipt = $receipt_url;
                $Order->save();

                $plan = Plan::find($request->plan_id);
                $counter = [
                    'user_counter' => $request->counter['user_counter'] ?: 0,
                    'storage_limit' => $request->counter['storage_limit'] ?: 0,
                ];
                $assignPlan = assignPlan($plan->id, $request->duration, $request->user_module, $counter, $request->user_id);
                if ($assignPlan['is_success']) {
                    if ($request->coupon_code) {
                        $coupon = Coupon::where('code', $request->coupon_code)->first();
                        if ($coupon) {
                            recordCouponUsage($coupon->id, $request->user_id, $request->order_id);
                        }
                    }
                    $type = 'Subscription';

                    try {
                        StripePaymentStatus::dispatch($plan, $type, $Order);
                    } catch (\Exception $e) {
                        return redirect()->back()->with('error', $e->getMessage());
                    }

                    return redirect()->route('plans.index')->with('success', __('Plan activated Successfully!'));
                } else {
                    $Order = Order::where('order_id', $request->order_id)->first();
                    $Order->payment_status = 'failed';
                    $Order->save();
                    return redirect()->route('plans.index')->with('error', __('Something went wrong, Please try again,'));
                }
            } else {
                return redirect()->route('plans.index')->with('error', __('Your Payment has failed!'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('plans.index')->with('error', $exception->getMessage());
        }
    }

    /**
     * Create Stripe checkout session - Dynamic for both plans and invoices
     */
    private function createStripeSession($params)
    {
        $api_key = $params['api_key'] ??
            $params['admin_settings']['stripe_secret'] ??
            ($params['company_settings'] ? company_setting('stripe_secret', $params['user_id'] ?? null) : null) ??
            '';
        Stripe::setApiKey($api_key);

        // Build session data
        $session_data = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $params['currency'],
                    'unit_amount' => (int) $params['amount'],
                    'product_data' => [
                        'name' => $params['product_name'],
                        'description' => $params['description'] ?? '',
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'metadata' => $params['metadata'],
            'success_url' => $params['success_url'],
            'cancel_url' => $params['cancel_url'],
        ];

        return \Stripe\Checkout\Session::create($session_data);
    }

    public function bookingPayWithStripe(Request $request, $userSlug = null)
    {
        try {
            $user = User::where('slug', $userSlug)->first();
            if ($user) {
                // Get booking data from request (same structure as booking.store)
                $selectedTimeSlot = [
                    'start_time' => $request->input('selectedTimeSlot.start_time'),
                    'end_time' => $request->input('selectedTimeSlot.end_time'),
                    'label' => $request->input('selectedTimeSlot.label')
                ];

                $bookingData = [
                    'selectedDate' => $request->selectedDate,
                    'selectedItem' => $request->selectedItem,
                    'selectedPackageItem' => $request->selectedPackageItem,
                    'selectedTimeSlot' => $selectedTimeSlot,
                    'formData' => [
                        'firstName' => $request->input('formData.firstName'),
                        'lastName' => $request->input('formData.lastName'),
                        'email' => $request->input('formData.email'),
                        'phone' => $request->input('formData.phone'),
                        'description' => $request->input('formData.description'),
                        'paymentOption' => $request->input('formData.paymentOption')
                    ]
                ];

                // Store booking data and userSlug in session for after payment
                Session::put('booking_data', $bookingData);
                Session::put('booking_user_slug', $request->route('userSlug'));

                $package = BookingPackage::find($request->selectedPackageItem);
                if (!$package) {
                    return redirect()->back()->with('error', __('Package not found.'));
                }

                $company_settings = getCompanyAllSetting($package->created_by);
                $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

                $price = $package->price ?? 0;
                if ($price <= 0) {
                    return redirect()->back()->with('error', __('Invalid payment amount.'));
                }

                $stripe_formatted_price = in_array(
                    $company_currancy,
                    ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
                ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

                $stripe_session = $this->createStripeSession([
                    'api_key' => $company_settings['stripe_secret'] ?? '',
                    'currency' => $company_currancy,
                    'amount' => $stripe_formatted_price,
                    'product_name' => $package->name ?? 'Booking Service',
                    'description' => 'Booking Service Payment',
                    'metadata' => [
                        'package_id' => $package->id,
                        'customer_name' => $bookingData['formData']['firstName'] . ' ' . $bookingData['formData']['lastName'],
                        'customer_email' => $bookingData['formData']['email'],
                    ],
                    'success_url' => route('booking.payment.stripe.status', [
                        'return_type' => 'success',
                        'userSlug' => $request->route('userSlug')
                    ]),
                    'cancel_url' => route('booking.payment.stripe.status', [
                        'return_type' => 'cancel',
                        'userSlug' => $request->route('userSlug')
                    ]),
                ]);

                Session::put('booking_stripe_session', $stripe_session);

                return Inertia::render('Stripe/StripePayment', [
                    'stripe_session' => $stripe_session,
                    'stripe_key' => $company_settings['stripe_key'] ?? ''
                ]);
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function bookingGetStripeStatus(Request $request, $userSlug = null)
    {
        try {
            $user = User::where('slug', $userSlug)->first();
            if ($user) {
                $bookingData = Session::get('booking_data');
                $stripe_session = Session::get('booking_stripe_session');
                $userSlug = Session::get('booking_user_slug');
                if (!$bookingData) {
                    return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
                }

                $package = BookingPackage::find($bookingData['selectedPackageItem']);
                $company_settings = getCompanyAllSetting($package->created_by ?? 1);

                try {
                    $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
                    $payment_intent = null;
                    $receipt_url = "";
                    if ($stripe_session && isset($stripe_session['id'])) {
                        // Retrieve fresh session from Stripe API
                        $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                        if (isset($checkoutSession->payment_intent)) {
                            $payment_intent = $checkoutSession->payment_intent;
                            $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                            if (!empty($paymentIntents->latest_charge)) {
                                $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                                $receipt_url = $charge->receipt_url ?? '';
                            }
                        }
                    }
                } catch (\Exception $exception) {
                    $receipt_url = "";
                }

                Session::forget('booking_stripe_session');
                Session::forget('booking_data');

                if ($request->return_type == 'success') {
                    // Create appointment after successful payment
                    $timeSlot = $bookingData['selectedTimeSlot'];
                    $userId = $package->created_by ?? null;

                    // Find or create customer (same as BookingController)
                    $customer = BookingCustomer::where('email', $bookingData['formData']['email'])
                        ->where('created_by', $userId)
                        ->first();

                    if (!$customer) {
                        $customer = new BookingCustomer();
                        $customer->first_name = $bookingData['formData']['firstName'];
                        $customer->last_name = $bookingData['formData']['lastName'];
                        $customer->email = $bookingData['formData']['email'];
                        $customer->mobile_number = $bookingData['formData']['phone'];
                        $customer->description = $bookingData['formData']['description'] ?? null;
                        $customer->created_by = $userId;
                        $customer->creator_id = $userId;
                        $customer->save();
                    }

                    // Generate appointment number (same as BookingController)
                    $currentYear = date('Y');
                    $lastAppointment = BookingAppointment::where('created_by', $userId)
                        ->where('appointment_number', 'like', 'APT-' . $currentYear . '-' . $userId . '-%')
                        ->orderBy('appointment_number', 'desc')
                        ->first();

                    if ($lastAppointment) {
                        $lastNumber = (int) substr($lastAppointment->appointment_number, -4);
                        $nextNumber = $lastNumber + 1;
                    } else {
                        $nextNumber = 1;
                    }

                    $appointmentNumber = 'APT-' . $currentYear . '-' . $userId . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

                    // Create appointment (same structure as BookingController)
                    $appointment = new BookingAppointment();
                    $appointment->appointment_number = $appointmentNumber;
                    $appointment->date = $bookingData['selectedDate'];
                    $appointment->item_id = $bookingData['selectedItem'];
                    $appointment->package_id = $bookingData['selectedPackageItem'];
                    $appointment->customer_id = $customer->id;
                    $appointment->start_time = $timeSlot['start_time'];
                    $appointment->end_time = $timeSlot['end_time'];
                    $appointment->payment = 'Stripe';
                    $appointment->status = 'confirmed';
                    $appointment->payment_status = 'paid';
                    $appointment->payment_receipt = $receipt_url;
                    $appointment->online_payment_id = $payment_intent ?? null;
                    $appointment->created_by = $userId;
                    $appointment->creator_id = $userId;
                    $appointment->save();

                    try {
                        BookingAppointmentPayments::dispatch($appointment);
                    } catch (\Throwable $th) {
                        return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    // Get userSlug from session
                    Session::forget('booking_user_slug');

                    return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('success', __('Payment completed and appointment created successfully!'));
                } else {
                    Session::forget('booking_user_slug');
                    return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
                }
            }
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong. Please try again.'));
        } catch (\Exception $exception) {
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }


    public function beautySpaPayWithStripe(Request $request)
    {
        // Store booking data in session
        $bookingData = [
            'service' => $request->service,
            'date' => $request->date,
            'time_slot' => $request->time_slot,
            'person' => $request->person,
            'gender' => $request->gender,
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'reference' => $request->reference,
            'additional_notes' => $request->additional_notes,
            'payment_option' => $request->payment_option
        ];

        Session::put('beauty_booking_data', $bookingData);
        Session::put('beauty_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $service = BeautyService::where('id', $request->service)
            ->where('created_by', $userId)
            ->firstOrFail();

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        // Check for active offers
        $offers = BeautyServiceOffer::where('beauty_service_id', $service->id)
            ->where('start_date', '<=', $request->date)
            ->where('end_date', '>=', $request->date)
            ->where('created_by', $userId)
            ->get();
        $price = ($offers->isNotEmpty() ? $offers->sum('offer_price') : $service->price) * $request->person;
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => $service->name ?? 'Beauty Service',
                'description' => 'Beauty Service Payment',
                'metadata' => [
                    'service_id' => $service->id,
                    'customer_name' => $request->name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('beauty-spa.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('beauty-spa.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('beauty_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function beautySpaGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('beauty_booking_data');
        $stripe_session = Session::get('beauty_stripe_session');
        $userSlug = Session::get('beauty_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $service = BeautyService::where('id', $bookingData['service'])
            ->where('created_by', $userId)
            ->first();

        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('beauty_stripe_session');
        Session::forget('beauty_booking_data');
        Session::forget('beauty_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Check for active offers to get correct price
                $offers = BeautyServiceOffer::where('beauty_service_id', $service->id)
                    ->where('start_date', '<=', $bookingData['date'])
                    ->where('end_date', '>=', $bookingData['date'])
                    ->where('created_by', $userId)
                    ->get();

                $servicePrice = ($offers->isNotEmpty() ? $offers->sum('offer_price') : $service->price) * $bookingData['person'];
                $times = explode('-', $bookingData['time_slot']);

                $booking = new BeautyBooking();
                $booking->name = $bookingData['name'];
                $booking->email = $bookingData['email'];
                $booking->phone_number = $bookingData['phone_number'];
                $booking->service = $bookingData['service'];
                $booking->date = $bookingData['date'];
                $booking->start_time = $times[0];
                $booking->end_time = $times[1];
                $booking->person = $bookingData['person'];
                $booking->price = $servicePrice;
                $booking->gender = $bookingData['gender'];
                $booking->reference = $bookingData['reference'];
                $booking->notes = $bookingData['additional_notes'];
                $booking->payment_option = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->stage_id = 0;
                $booking->creator_id = null;
                $booking->created_by = $userId;
                $booking->save();

                $beautyreceipt                  = new BeautyBookingReceipt();
                $beautyreceipt->beauty_booking_id      = $booking->id;
                $beautyreceipt->name            = $booking->name;
                $beautyreceipt->service         = $booking->service;
                $beautyreceipt->number          = $booking->number;
                $beautyreceipt->gender          = $booking->gender;
                $beautyreceipt->start_time      = $booking->start_time;
                $beautyreceipt->end_time        = $booking->end_time;
                $beautyreceipt->price           = $booking->price;
                $beautyreceipt->payment_type    = 'Stripe';
                $beautyreceipt->created_by      = $booking->created_by;
                $beautyreceipt->save();

                try {
                    BeautyBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('beauty-spa.booking-success', ['userSlug' => $userSlug, 'id' => \Illuminate\Support\Facades\Crypt::encrypt($booking->id)])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function lmsPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $student = auth('lms_student')->user();
        if (!$student) {
            return redirect()->route('lms.frontend.login', ['userSlug' => $userSlug]);
        }

        // Get cart items
        $cartItems = LMSCart::where('created_by', $user->id)
            ->where('student_id', $student->id)
            ->with('course')
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('lms.frontend.cart', ['userSlug' => $userSlug])
                ->with('error', __('Your cart is empty'));
        }

        // Calculate totals (same logic as placeOrder)
        $originalTotal = $cartItems->sum('original_price');
        $subtotal = $cartItems->sum('price');
        $courseDiscount = $originalTotal - $subtotal;
        $couponDiscount = 0;
        $appliedCoupon = session('applied_coupon');

        if ($appliedCoupon) {
            $coupon = LMSCoupon::where('id', $appliedCoupon['id'])
                ->where('created_by', $user->id)
                ->first();

            if ($coupon && $coupon->isValid()) {
                if (!$coupon->minimum_amount || $subtotal >= $coupon->minimum_amount) {
                    if ($coupon->type === 'percentage') {
                        $couponDiscount = ($subtotal * $coupon->value) / 100;
                    } else {
                        $couponDiscount = $coupon->value;
                    }
                    $couponDiscount = min($couponDiscount, $subtotal);
                }
            }
        }

        $total = $subtotal - $couponDiscount;

        if ($total <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        // Store order data in session
        Session::put('lms_order_data', [
            'original_total' => $originalTotal,
            'payment_method' => $request->payment_method,
            'payment_note' => $request->payment_note,
            'subtotal' => $subtotal,
            'course_discount' => $courseDiscount,
            'coupon_discount' => $couponDiscount,
            'total' => $total,
            'applied_coupon' => $appliedCoupon
        ]);
        Session::put('lms_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($total, 2, '.', '') : number_format($total, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'LMS Course Purchase',
                'description' => 'Online Course Payment',
                'metadata' => [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'student_email' => $student->email,
                    'course_count' => $cartItems->count()
                ],
                'success_url' => route('lms.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('lms.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('lms_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function lmsGetStripeStatus(Request $request)
    {
        $orderData = Session::get('lms_order_data');
        $stripe_session = Session::get('lms_stripe_session');
        $userSlug = Session::get('lms_user_slug');

        if (!$orderData) {
            return redirect()->route('lms.frontend.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $student = auth('lms_student')->user();

        if (!$user || !$student) {
            return redirect()->route('lms.frontend.home', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;
            $receipt_url = "";

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            $receipt_url = "";
        }

        Session::forget('lms_stripe_session');
        Session::forget('lms_order_data');
        Session::forget('lms_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Get cart items
                $cartItems = LMSCart::where('created_by', $user->id)
                    ->where('student_id', $student->id)
                    ->with('course')
                    ->get();

                if ($cartItems->isEmpty()) {
                    return redirect()->route('lms.frontend.cart', ['userSlug' => $userSlug])
                        ->with('error', __('Your cart is empty'));
                }

                // Create order
                $order = new LMSOrder();
                $order->order_number = LMSOrder::generateOrderNumber($user->id);
                $order->student_id = $student->id;
                $order->payment_method = 'Stripe';
                $order->payment_status = 'paid';
                $order->original_total = $orderData['original_total'];
                $order->subtotal = $orderData['subtotal'];
                $order->discount_amount = $orderData['course_discount'];
                $order->coupon_discount = $orderData['coupon_discount'];
                $order->total_discount = $orderData['course_discount'] + $orderData['coupon_discount'];
                $order->total_amount = $orderData['total'];
                $order->coupon_id = $orderData['applied_coupon'] ? $orderData['applied_coupon']['id'] : null;
                $order->coupon_code = $orderData['applied_coupon'] ? $orderData['applied_coupon']['code'] : null;
                $order->status = 'completed';
                $order->receipt = $receipt_url;
                $order->notes = $orderData['payment_note'];
                $order->order_date = now();
                $order->payment_id = $payment_intent;
                $order->creator_id = $user->id;
                $order->created_by = $user->id;
                $order->save();

                // Create order items
                foreach ($cartItems as $cartItem) {
                    $orderItem = new LMSOrderItem();
                    $orderItem->order_id = $order->id;
                    $orderItem->course_id = $cartItem->course_id;
                    $orderItem->quantity = $cartItem->quantity;
                    $orderItem->unit_price = $cartItem->price;
                    $orderItem->total_price = $cartItem->price * $cartItem->quantity;
                    $orderItem->save();
                }

                // Clear cart and coupon
                $cartItems->each->delete();
                session()->forget('applied_coupon');

                try {
                    LMSOrderPayments::dispatch($order);
                } catch (\Throwable $th) {
                    return redirect()->route('lms.frontend.home', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('lms.frontend.home', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed successfully! Order #:number', ['number' => $order->order_number]));
            } else {
                return redirect()->route('lms.frontend.checkout', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('lms.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function laundryPayWithStripe(Request $request)
    {
        $bookingData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'location' => $request->location,
            'numberOfItems' => $request->cloth_no,
            'specialInstructions' => $request->instructions,
            'pickupDate' => $request->pickup_date,
            'pickupTime' => $request->pickupTime,
            'deliveryDate' => $request->delivery_date,
            'deliveryTime' => $request->deliveryTime,
            'services' => json_decode($request->services, true) ?? [],
            'total' => $request->total
        ];

        Session::put('laundry_booking_data', $bookingData);
        Session::put('laundry_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->total ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Laundry Service',
                'description' => 'Laundry Service Payment',
                'metadata' => [
                    'customer_name' => $request->name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('laundry.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('laundry.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('laundry_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function laundryGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('laundry_booking_data');
        $stripe_session = Session::get('laundry_stripe_session');
        $userSlug = Session::get('laundry_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('laundry_stripe_session');
        Session::forget('laundry_booking_data');
        Session::forget('laundry_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $booking = new LaundryRequest();
                $booking->name = $bookingData['name'];
                $booking->email = $bookingData['email'];
                $booking->phone = $bookingData['phone'];
                $booking->address = $bookingData['address'];
                $booking->location = $bookingData['location'];
                $booking->cloth_no = $bookingData['numberOfItems'];
                $booking->instructions = $bookingData['specialInstructions'];
                $booking->pickup_date = $bookingData['pickupDate'] . ' ' . $bookingData['pickupTime'];
                $booking->delivery_date = $bookingData['deliveryDate'] . ' ' . $bookingData['deliveryTime'];
                $booking->services = $bookingData['services'];
                $booking->payment_method = 'Stripe';
                $booking->payment_id = $payment_intent;
                $booking->status = 2;
                $booking->total = $bookingData['total'];
                $booking->created_by = $userId;
                $booking->creator_id = $userId;
                $booking->save();

                $invoice = new LaundryInvoice();
                $invoice->laundry_request_id = $booking->id;
                $invoice->amount = $booking->total;
                $invoice->status = 1;
                $invoice->creator_id = $userId;
                $invoice->created_by = $userId;
                $invoice->save();

                if ($invoice->status == 1) {
                    $payment = new LaundryPayment();
                    $payment->payment_amount = $invoice->amount;
                    $payment->invoice_id = $invoice->id;
                    $payment->payment_date = date('Y-m-d H:i:s');
                    $payment->status = 'cleared';
                    $payment->creator_id = $userId;
                    $payment->created_by = $userId;
                    $payment->save();
                }
                try {
                    LaundryBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('laundry-management.frontend.booking-success', [
                    'userSlug' => $userSlug,
                    'requestId' => encrypt($booking->id)
                ]);
            } else {
                return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function parkingPayWithStripe(Request $request)
    {
        $bookingData = [
            'slot_name'      => $request->slot_name,
            'slot_type_id'   => $request->slot_type_id,
            'date'           => $request->date,
            'start_time'     => $request->start_time,
            'end_time'       => $request->end_time,
            'customer_name'  => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'vehicle_name'   => $request->vehicle_name,
            'vehicle_number' => $request->vehicle_number,
            'payment_option' => $request->payment_option,
            'total_amount'   => $request->total_amount
        ];

        Session::put('parking_booking_data', $bookingData);
        Session::put('parking_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Parking Slot - ' . $request->slot_name,
                'description' => 'Parking Management Payment',
                'metadata' => [
                    'slot_name' => $request->slot_name,
                    'customer_name' => $request->customer_name,
                    'customer_email' => $request->customer_email,
                ],
                'success_url' => route('parking.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('parking.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('parking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function parkingGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('parking_booking_data');
        $stripe_session = Session::get('parking_stripe_session');
        $userSlug = Session::get('parking_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('parking_stripe_session');
        Session::forget('parking_booking_data');
        Session::forget('parking_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $booking = new ParkingBooking();
                $booking->slot_name = $bookingData['slot_name'];
                $booking->slot_type_id = $bookingData['slot_type_id'];
                $booking->booking_date = $bookingData['date'];
                $booking->start_time = $bookingData['start_time'];
                $booking->end_time = $bookingData['end_time'];
                $booking->customer_name = $bookingData['customer_name'];
                $booking->customer_email = $bookingData['customer_email'];
                $booking->customer_phone = $bookingData['customer_phone'];
                $booking->vehicle_name = $bookingData['vehicle_name'];
                $booking->vehicle_number = $bookingData['vehicle_number'];
                $booking->total_amount = $bookingData['total_amount'];
                $booking->payment_method = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->booking_status = 'confirmed';
                $booking->creator_id = $userId;
                $booking->created_by = $userId;
                $booking->save();

                try {
                    ParkingBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('parking-management.frontend.booking-success', ['userSlug' => $userSlug, 'id' => \Illuminate\Support\Facades\Crypt::encrypt($booking->id)])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function eventsPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $eventId = $request->event_id;
        $event = Event::where('id', $eventId)
            ->where('created_by', $user->id)
            ->firstOrFail();

        // Store booking data in session
        $bookingData = [
            'event_id' => $eventId,
            'fullName' => $request->fullName,
            'email' => $request->email,
            'phone' => $request->phone,
            'persons' => $request->persons,
            'total' => $request->total,
            'ticket_type_id' => $request->ticket_type_id,
            'time_slot' => $request->time_slot,
            'selected_date' => $request->selected_date
        ];

        Session::put('events_booking_data', $bookingData);
        Session::put('events_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->total);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => $event->title ?? 'Event Booking',
                'description' => 'Event Booking Payment',
                'metadata' => [
                    'event_id' => $eventId,
                    'customer_name' => $request->fullName,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('events-management.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('events-management.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('events_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function eventsGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('events_booking_data');
        $stripe_session = Session::get('events_stripe_session');
        $userSlug = Session::get('events_user_slug');
        if (!$bookingData) {
            return redirect()->route('events-management.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $event = Event::where('id', $bookingData['event_id'])
            ->where('created_by', $user->id)
            ->first();

        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('events_stripe_session');
        Session::forget('events_booking_data');
        Session::forget('events_user_slug');
        try {
            if ($request->return_type == 'success') {
                // Create event booking
                $eventbooking = new EventBooking();
                $eventbooking->event_id = $bookingData['event_id'];
                $eventbooking->ticket_type_id = $bookingData['ticket_type_id'];
                $eventbooking->time_slot = $bookingData['time_slot'];
                $eventbooking->name = $bookingData['fullName'];
                $eventbooking->email = $bookingData['email'];
                $eventbooking->mobile = $bookingData['phone'];
                $eventbooking->person = $bookingData['persons'];
                $eventbooking->date = $bookingData['selected_date'];
                $eventbooking->total_price = $bookingData['total'];
                $eventbooking->price = $bookingData['total'] / $bookingData['persons'];
                $eventbooking->status = 'confirmed';
                $eventbooking->created_by = $user->id;
                $eventbooking->creator_id = $user->id;
                $eventbooking->save();

                // Create payment record
                $eventBookingPayment = new EventBookingPayment();
                $eventBookingPayment->event_booking_id = $eventbooking->id;
                $eventBookingPayment->booking_number = $eventbooking->booking_number;
                $eventBookingPayment->event_name = $event->title;
                $eventBookingPayment->customer_name = $bookingData['fullName'];
                $eventBookingPayment->payment_date = now();
                $eventBookingPayment->amount = $bookingData['total'];
                $eventBookingPayment->payment_status = 'cleared';
                $eventBookingPayment->payment_type = 'Stripe';
                $eventBookingPayment->description = 'Payment via Stripe';
                $eventBookingPayment->created_by = $user->id;
                $eventBookingPayment->creator_id = $user->id;
                $eventBookingPayment->save();

                try {
                    EventBookingPayments::dispatch($eventbooking, $eventBookingPayment);
                } catch (\Throwable $th) {
                    return redirect()->route('events-management.frontend.ticket', ['userSlug' => $userSlug, 'id' => $bookingData['event_id']])->with('error', $th->getMessage());
                }

                return redirect()->route('events-management.frontend.ticket', ['userSlug' => $userSlug, 'id' => $eventbooking->id, 'paymentId' => $eventBookingPayment->id])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('events-management.frontend.payment', ['userSlug' => $userSlug, 'id' => $bookingData['event_id']])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('events-management.frontend.payment', ['userSlug' => $userSlug, 'id' => $bookingData['event_id']])->with('error', $exception->getMessage());
        }
    }

    // Room Booking Stripe Payment
    public function holidayzPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $customer = auth('holidayz_customer')->user();
        if (!$customer) {
            return redirect()->route('hotel.frontend.login', ['userSlug' => $userSlug]);
        }

        // Get cart items with relationships
        $cart = HolidayzCart::where('created_by', $user->id)
            ->where('customer_id', $customer->id)
            ->with(['items.room', 'items.facilities', 'items.taxes'])
            ->first();

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('hotel.frontend.cart', ['userSlug' => $userSlug])
                ->with('error', __('Your cart is empty'));
        }

        // Check room availability for all cart items before payment
        foreach ($cart->items as $cartItem) {
            $availableRooms = HolidayzAvailabilityHelper::getAvailableRoomCount(
                $cartItem->room_id,
                $cartItem->check_in_date->format('Y-m-d'),
                $cartItem->check_out_date->format('Y-m-d'),
                null,
                $user->id
            );

            if ($cartItem->quantity > $availableRooms) {
                return redirect()->route('hotel.frontend.cart', ['userSlug' => $userSlug])
                    ->with('error', __('Room ":room" is no longer available for the selected dates. Only :available rooms available.', [
                        'room' => $cartItem->room->room_type,
                        'available' => $availableRooms
                    ]));
            }
        }

        // Calculate totals with proper coupon handling
        $subtotal = $cart->items->sum(function ($item) {
            return $item->rent_per_night * $item->nights * $item->quantity;
        });

        $tax_amount = $cart->items->sum(function ($item) {
            return $item->taxes->sum('pivot.tax_amount');
        });

        $facilities_amount = $cart->items->sum(function ($item) {
            return $item->facilities->sum('pivot.total_amount');
        });

        $coupon_discount = 0;
        $applied_coupon = session('applied_coupon');

        if ($applied_coupon && isset($applied_coupon['id'])) {
            $coupon = HolidayzCoupon::find($applied_coupon['id']);

            if ($coupon && $coupon->created_by == $user->id && $coupon->isValid()) {
                $coupon_discount = $applied_coupon['discount'] ?? 0;
                $coupon_discount = min($coupon_discount, $subtotal);
            } else {
                session()->forget('applied_coupon');
                $applied_coupon = null;
            }
        }

        $total = $subtotal + $tax_amount + $facilities_amount - $coupon_discount;

        if ($total <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        // Store order data in session
        Session::put('holidayz_order_data', [
            'payment_method' => 'Stripe',
            'subtotal' => $subtotal,
            'tax_amount' => $tax_amount,
            'facilities_amount' => $facilities_amount,
            'coupon_discount' => $coupon_discount,
            'total' => $total,
            'applied_coupon' => $applied_coupon,
            'special_requests' => $request->special_requests
        ]);
        Session::put('holidayz_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($total, 2, '.', '') : number_format($total, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Hotel Booking - ' . $cart->items->count() . ' room(s)',
                'description' => 'Hotel Room Reservation Payment for ' . $cart->items->sum('nights') . ' night(s)',
                'metadata' => [
                    'customer_id' => $customer->id,
                    'customer_name' => ($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''),
                    'customer_email' => $customer->email ?? '',
                    'room_count' => $cart->items->sum('quantity'),
                    'total_nights' => $cart->items->sum('nights'),
                    'check_in' => $cart->items->first()->check_in_date ?? '',
                    'check_out' => $cart->items->first()->check_out_date ?? ''
                ],
                'success_url' => route('holidayz.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('holidayz.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('holidayz_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function holidayzGetStripeStatus(Request $request)
    {
        $orderData = Session::get('holidayz_order_data');
        $stripe_session = Session::get('holidayz_stripe_session');
        $userSlug = Session::get('holidayz_user_slug');

        if (!$orderData) {
            return redirect()->route('hotel.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $customer = auth('holidayz_customer')->user();
        if (!$user || !$customer) {
            return redirect()->route('hotel.frontend.index', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('holidayz_stripe_session');
        Session::forget('holidayz_order_data');
        Session::forget('holidayz_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Create booking after successful payment
                $cart = HolidayzCart::where('created_by', $user->id)
                    ->where('customer_id', $customer->id)
                    ->with(['items.room', 'items.facilities', 'items.taxes'])
                    ->first();

                $booking = new HolidayzRoomBooking();
                $booking->booking_date = now();
                $booking->customer_id = $customer->id;
                $booking->adults = $cart->items->sum('adults');
                $booking->children = $cart->items->sum('children');
                $booking->total_guests = $cart->items->sum('adults') + $cart->items->sum('children');
                $booking->subtotal = $orderData['subtotal'];
                $booking->tax_amount = $orderData['tax_amount'];
                $booking->coupon_id = $orderData['applied_coupon']['id'] ?? null;
                $booking->discount_amount = $orderData['coupon_discount'];
                $booking->total_amount = $orderData['total'];
                $booking->paid_amount = $orderData['total'];
                $booking->balance_amount = 0;
                $booking->payment_method = 'Stripe';
                $booking->status = 'paid';
                $booking->special_requests = $orderData['special_requests'];
                $booking->creator_id = $user->id;
                $booking->created_by = $user->id;
                $booking->save();

                foreach ($cart->items as $cartItem) {
                    $bookingItem = new HolidayzRoomBookingItem();
                    $bookingItem->booking_id = $booking->id;
                    $bookingItem->room_id = $cartItem->room_id;
                    $bookingItem->check_in_date = $cartItem->check_in_date;
                    $bookingItem->check_out_date = $cartItem->check_out_date;
                    $bookingItem->quantity = $cartItem->quantity;
                    $bookingItem->adults = $cartItem->adults;
                    $bookingItem->children = $cartItem->children;
                    $bookingItem->rent_per_night = $cartItem->rent_per_night;
                    $bookingItem->nights = $cartItem->nights;
                    $bookingItem->discount_percentage = 0;
                    $bookingItem->discount_amount = 0;
                    $bookingItem->total_amount = $cartItem->rent_per_night * $cartItem->nights * $cartItem->quantity;
                    $bookingItem->save();

                    foreach ($cartItem->facilities as $facility) {
                        $bookingItem->facilities()->attach($facility->id, [
                            'price' => $facility->pivot->price,
                            'quantity' => $facility->pivot->quantity,
                            'total_amount' => $facility->pivot->total_amount
                        ]);
                    }

                    foreach ($cartItem->taxes as $tax) {
                        $bookingItem->taxes()->attach($tax->id, [
                            'tax_name' => $tax->pivot->tax_name ?? $tax->name,
                            'tax_rate' => $tax->pivot->tax_rate ?? $tax->rate,
                            'tax_amount' => $tax->pivot->tax_amount
                        ]);
                    }
                }

                // Record coupon usage if applicable
                if ($orderData['applied_coupon']) {
                    $couponId = $orderData['applied_coupon']['id'];
                    $coupon = HolidayzCoupon::find($couponId);
                    if ($coupon) {
                        // Check if already recorded (prevent duplicates)
                        $existingUsage = HolidayzCouponUsage::where('coupon_id', $couponId)
                            ->where('customer_id', $customer->id)
                            ->exists();

                        if (!$existingUsage) {
                            $couponUsage = new HolidayzCouponUsage();
                            $couponUsage->coupon_id = $couponId;
                            $couponUsage->customer_id = $customer->id;
                            $couponUsage->used_at = now();
                            $couponUsage->creator_id = $coupon->creator_id;
                            $couponUsage->created_by = $coupon->created_by;
                            $couponUsage->save();

                            $coupon->increment('used_count');
                        }
                    }
                }

                // Clear cart and sessions
                HolidayzCart::where('created_by', $user->id)
                    ->where('customer_id', $customer->id)
                    ->delete();

                session()->forget('applied_coupon');

                try {
                    HolidayzBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return redirect()->route('hotel.frontend.index', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('hotel.frontend.booking-confirm', [
                    'userSlug' => $userSlug,
                    'encryptedBooking' => encrypt($booking->id)
                ])->with('success', __('Payment completed successfully! Booking #:number', ['number' => $booking->booking_number]));
            } else {
                return redirect()->route('hotel.frontend.checkout', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('hotel.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function facilitiesPaymentWithStripe(Request $request)
    {
        try {
            $userSlug = $request->route('userSlug');

            $user     = User::where('slug', $userSlug)->first();
            if (!$user) {
                return redirect()->back()->with('error', __('User not found.'));
            }

            $company_settings = getCompanyAllSetting($user->id);
            $currency         = $company_settings['defult_currancy'] ?? 'USD';

            // Get booking data from service
            $bookingData = FacilitiesBookingService::prepareBookingData($request, $user->id);

            if (!$bookingData) {
                return redirect()->back()->with('error', __('Invalid booking data.'));
            }

            $totalAmount = $bookingData['total_amount'];

            if ($totalAmount <= 0) {
                return redirect()->back()->with('error', __('Invalid booking amount.'));
            }

            $orderID = 'FB-' . strtoupper(substr(uniqid(), -8));

            // Format price for Stripe
            $stripe_formatted_price = in_array($currency, ['JPY', 'BIF', 'CLP', 'DJF', 'GNF', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'])
                ? number_format($totalAmount, 2, '.', '')
                : number_format($totalAmount, 2, '.', '') * 100;

            // Create Stripe session
            $stripe_session = $this->createStripeSession([
                'api_key'      => $company_settings['stripe_secret'] ?? '',
                'currency'     => $currency,
                'amount'       => $stripe_formatted_price,
                'product_name' => 'Facility Booking',
                'description'  => 'Facility booking payment',
                'metadata'     => [
                    'booking_type' => 'facility',
                    'user_id'      => $user->id,
                    'order_id'     => $orderID,
                ],
                'success_url'  => route('facilities.payment.stripe.status', [
                    'userSlug'    => $userSlug,
                    'order_id'    => $orderID,
                    'return_type' => 'success'
                ]),
                'cancel_url'   => route('facilities.payment.stripe.status', [
                    'userSlug'    => $userSlug,
                    'order_id'    => $orderID,
                    'return_type' => 'cancel'
                ]),
            ]);

            Session::put('facility_booking_' . $orderID, $bookingData);
            Session::put('facilities_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key'     => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Payment processing failed. Please try again.'));
        }
    }

    public function facilitiesGetStripeStatus(Request $request)
    {
        try {
            $userSlug    = $request->route('userSlug');
            $orderID     = $request->get('order_id');
            $return_type = $request->get('return_type');

            if ($return_type === 'cancel') {
                return redirect()->route('facilities.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }

            $user = User::where('slug', $userSlug)->first();
            if (!$user) {
                return redirect()->back()->with('error', __('User not found.'));
            }

            $company_settings = getCompanyAllSetting($user->id);
            $stripe           = new StripeClient($company_settings['stripe_secret'] ?? '');
            $stripe_session   = Session::get('facilities_stripe_session');

            if ($stripe_session) {
                $session = $stripe->checkout->sessions->retrieve($stripe_session->id);

                if ($session->payment_status === 'paid') {
                    // Get booking data
                    $bookingData = Session::get('facility_booking_' . $orderID);

                    if ($bookingData) {
                        // Create facility booking using Service
                        $booking = FacilitiesBookingService::createBooking($bookingData, $user->id, 'Stripe');

                        // Create payment entry
                        FacilitiesBookingService::createPaymentEntry($booking, $user->id, [
                            'method' => 'Stripe',
                            'transaction_id' => $session->payment_intent ?? null,
                            'currency' => $company_settings['defult_currancy'] ?? 'USD',
                            'receipt_url' => $session->url ?? null,
                        ]);

                        try {
                            FacilityBookingPaymentStripe::dispatch($booking);
                        } catch (\Throwable $th) {
                            return back()->with('error', $th->getMessage());
                        }

                        // Clean up session
                        Session::forget('facility_booking_' . $orderID);
                        Session::forget('facilities_stripe_session');

                        return redirect()->route('facilities.frontend.booking-success', [
                            'userSlug'       => $userSlug,
                            'booking_number' => $booking->booking_number
                        ])->with('success', __('Payment successful! Booking confirmed: ') . $booking->booking_number);
                    }
                }
            }

            return redirect()->route('facilities.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment verification failed.'));
        } catch (\Exception $e) {

            return redirect()->route('facilities.frontend.booking', ['userSlug' => $request->route('userSlug')])->with('error', __('Payment verification failed.'));
        }
    }

    // vehicle Booking Stripe Payment
    public function vehicleBookingPayWithStripe(Request $request)
    {
        $bookingData = [
            'email' => $request->email,
            'selected_seats' => $request->selectedSeats,
            'passengers' => $request->passengers,
            'route_id' => $request->route_id,
            'vehicle_id' => $request->vehicle_id,
            'booking_date' => $request->booking_date,
            'total_amount' => $request->total_amount,
            'special_requests' => $request->special_requests,
            'payment_method' => 'Stripe'
        ];

        Session::put('vehicle_booking_data', $bookingData);
        Session::put('vehicle_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Vehicle Booking',
                'description' => 'Vehicle Booking Payment',
                'metadata' => [
                    'customer_email' => $request->email,
                    'route_id' => $request->route_id,
                ],
                'success_url' => route('vehicle-booking.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('vehicle-booking.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('vehicle_booking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function vehicleBookingGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('vehicle_booking_data');
        $stripe_session = Session::get('vehicle_booking_stripe_session');
        $userSlug = Session::get('vehicle_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('vehicle_booking_stripe_session');
        Session::forget('vehicle_booking_data');
        Session::forget('vehicle_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $booking = new VehicleBooking();
                $booking->booking_number = VehicleBooking::generateBookingNumber($userId);
                $booking->email = $bookingData['email'];
                $booking->selected_seats = $bookingData['selected_seats'];
                $booking->passengers = $bookingData['passengers'];
                $booking->route_id = $bookingData['route_id'];
                $booking->vehicle_id = $bookingData['vehicle_id'];
                $booking->booking_date = $bookingData['booking_date'];
                $booking->total_amount = $bookingData['total_amount'];
                $booking->payment_method = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->booking_status = 'confirmed';
                $booking->special_requests = $bookingData['special_requests'];
                $booking->transaction_id = $payment_intent;
                $booking->creator_id = $userId;
                $booking->created_by = $userId;
                $booking->save();

                try {
                    VehicleBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('vehicle-booking.frontend.success', ['userSlug' => $userSlug, 'id' => \Illuminate\Support\Facades\Crypt::encrypt($booking->id)])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function movieBookingPayWithStripe(Request $request)
    {
        $bookingData = session('booking_data');
        if (!$bookingData) {
            return redirect()->back()->with('error', __('Something went wrong, Please try again,'));
        }

        $bookingData['customer'] = [
            'name'  => $request->name,
            'email' => $request->email,
            'phone' => $request->phone
        ];

        Session::put('movie_booking_data', $bookingData);
        Session::put('movie_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user     = User::where('slug', $userSlug)->first();
        $userId   = $user ? $user->id : '';

        $company_settings     = getCompanyAllSetting($userId);
        $company_currancy     = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key'      => $company_settings['stripe_secret'] ?? '',
                'currency'     => $company_currancy,
                'amount'       => $stripe_formatted_price,
                'product_name' => 'Movie Ticket Booking',
                'description'  => 'Movie Show Booking Stripe Payment',
                'metadata'     => [
                    'customer_name'  => $request->name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('movie-booking.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug'    => $userSlug
                ]),
                'cancel_url' => route('movie-booking.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug'    => $userSlug
                ]),
            ]);

            Session::put('movie_booking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key'     => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function movieBookingGetStripeStatus(Request $request)
    {
        $bookingData    = Session::get('movie_booking_data');
        $stripe_session = Session::get('movie_booking_stripe_session');
        $userSlug       = Session::get('movie_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('movie-booking.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user             = User::where('slug', $userSlug)->first();
        $userId           = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('movie_booking_stripe_session');
        Session::forget('movie_booking_data');
        Session::forget('movie_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $bookedSeats = array_map(function ($seat) {
                    return [
                        'seat'  => $seat['seat'],
                        'price' => $seat['price']
                    ];
                }, $bookingData['seats'] ?? []);

                $bookedFoods = array_map(function ($food) {
                    return [
                        'id' => $food['id'],
                        'price' => $food['price'],
                        'quantity' => $food['quantity']
                    ];
                }, $bookingData['foods'] ?? []);

                $booking                 = new MovieBooking();
                $booking->booking_id     = strtoupper(uniqid());
                $booking->movie_id       = $bookingData['movie_id'];
                $booking->movie_show_id  = $bookingData['show_id'];
                $booking->screen_id      = $bookingData['screen_id'];
                $booking->customer_name  = $bookingData['customer']['name'] ?? '';
                $booking->customer_email = $bookingData['customer']['email'] ?? '';
                $booking->customer_phone = $bookingData['customer']['phone'] ?? '';
                $booking->booking_date   = $bookingData['date'] ?? '';
                $booking->show_time      = $bookingData['time'];
                $booking->total_seats    = $bookingData['pricing']['tickets'] ?? 0;
                $booking->booked_seats   = $bookedSeats;
                $booking->booked_foods   = $bookedFoods;
                $booking->subtotal       = $bookingData['pricing']['subtotal'] ?? 0;
                $booking->taxes          = $bookingData['pricing']['taxes'] ?? [];
                $booking->tax_amount     = $bookingData['pricing']['taxAmount'] ?? 0;
                $booking->total_amount   = $bookingData['pricing']['total'] ?? 0;
                $booking->payment_method = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->booking_status = 'confirmed';
                $booking->creator_id     = $userId;
                $booking->created_by     = $userId;
                $booking->save();
                try {
                    MovieBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }
                return redirect()->route('movie-booking.confirmation', ['userSlug' => $userSlug, 'id' => $booking->booking_id])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('movie-booking.home', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('movie-booking.home', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function ngoDonationPayWithStripe(Request $request)
    {
        // Store donation data in session
        $donationData = [
            'amount' => $request->amount,
            'campaign_id' => $request->campaign_id,
            'donor_name' => $request->donor_name,
            'donor_email' => $request->donor_email,
            'donor_message' => $request->donor_message,
            'payment_method' => 'Stripe'
        ];

        Session::put('ngo_donation_data', $donationData);
        Session::put('ngo_donation_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid donation amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'NGO Donation',
                'description' => 'Donation Payment',
                'metadata' => [
                    'donor_name' => $request->donor_name,
                    'donor_email' => $request->donor_email,
                    'campaign_id' => $request->campaign_id,
                ],
                'success_url' => route('ngo.donation.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('ngo.donation.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('ngo_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function ngoDonationGetStripeStatus(Request $request)
    {
        $donationData = Session::get('ngo_donation_data');
        $stripe_session = Session::get('ngo_stripe_session');
        $userSlug = Session::get('ngo_donation_user_slug');

        if (!$donationData) {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', __('User not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('ngo_stripe_session');
        Session::forget('ngo_donation_data');
        Session::forget('ngo_donation_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Find or create donor
                $donor = NgoDonor::where('email', $donationData['donor_email'])
                    ->where('created_by', $user->id)
                    ->first();

                if (!$donor) {
                    $donor = new NgoDonor();
                    $donor->name = $donationData['donor_name'];
                    $donor->email = $donationData['donor_email'];
                    $donor->created_by = $user->id;
                    $donor->creator_id = $user->id;
                    $donor->save();
                }

                // Create donation record
                $donation = new NgoDonation();
                $donation->donor_id = $donor->id;
                $donation->campaign_id = ($donationData['campaign_id'] === 'general' || !$donationData['campaign_id']) ? null : $donationData['campaign_id'];
                $donation->amount = $donationData['amount'];
                $donation->payment_method = 'Stripe';
                $donation->status = 'paid';
                $donation->transaction_id = $checkoutSession->payment_intent ?? null;
                $donation->donation_date = now();
                $donation->notes = $donationData['donor_message'];
                $donation->created_by = $user->id;
                $donation->creator_id = $user->id;
                $donation->save();

                // Update donor total donations
                $donor->increment('total_donations', $donationData['amount']);

                // Update campaign current amount if specific campaign
                if ($donation->campaign_id) {
                    $campaign = NgoCampaign::find($donation->campaign_id);
                    if ($campaign) {
                        $campaign->increment('current_amount', $donationData['amount']);
                    }
                }

                try {
                    CreateNgoDonation::dispatch(new Request($donationData), $donation);
                } catch (\Throwable $th) {
                    return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])
                    ->with('success', __('Thank you for your donation! Your payment has been processed successfully.'));
            } else {
                return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])
                    ->with('error', __('Donation was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function coworkingSpacePayWithStripe(Request $request)
    {
        $paymentType = $request->input('type', 'membership');

        if ($paymentType === 'booking') {
            $bookingData = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'phone' => $request->phone,
                'company' => $request->company,
                'specialRequests' => $request->specialRequests,
                'startDate' => $request->startDate,
                'endDate' => $request->endDate,
                'selectedAmenities' => json_decode($request->selectedAmenities, true) ?? [],
                'totalAmount' => $request->totalAmount,
                'duration' => $request->duration,
                'payment_method' => 'Stripe',
                'type' => 'booking'
            ];

            Session::put('coworking_booking_data', $bookingData);
            Session::put('coworking_booking_user_slug', $request->route('userSlug'));

            $userSlug = $request->route('userSlug');
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : '';

            $company_settings = getCompanyAllSetting($userId);
            $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

            $price = floatval($request->totalAmount);
            if ($price <= 0) {
                return redirect()->back()->with('error', __('Invalid payment amount.'));
            }

            try {
                $stripe_formatted_price = in_array(
                    $company_currancy,
                    ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
                ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

                $stripe_session = $this->createStripeSession([
                    'api_key' => $company_settings['stripe_secret'] ?? '',
                    'currency' => $company_currancy,
                    'amount' => $stripe_formatted_price,
                    'product_name' => 'Coworking Space Booking',
                    'description' => 'Coworking Space Booking Payment',
                    'metadata' => [
                        'customer_name' => $request->firstName . ' ' . $request->lastName,
                        'customer_email' => $request->email,
                        'type' => 'booking'
                    ],
                    'success_url' => route('coworking-space.payment.stripe.status', [
                        'return_type' => 'success',
                        'userSlug' => $userSlug
                    ]),
                    'cancel_url' => route('coworking-space.payment.stripe.status', [
                        'return_type' => 'cancel',
                        'userSlug' => $userSlug
                    ]),
                ]);

                Session::put('coworking_stripe_session', $stripe_session);

                return Inertia::render('Stripe/StripePayment', [
                    'stripe_session' => $stripe_session,
                    'stripe_key' => $company_settings['stripe_key'] ?? ''
                ]);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        } else {
            $bookingData = [
                'member_name' => $request->member_name,
                'email' => $request->email,
                'phone_no' => $request->phone_no,
                'plan_id' => $request->plan_id,
                'payment_method' => 'Stripe',
                'type' => 'membership'

            ];

            if (!$bookingData) {
                return redirect()->back()->with('error', __('Something went wrong, Please try again,'));
            }

            Session::put('coworking_booking_data', $bookingData);
            Session::put('coworking_booking_user_slug', $request->route('userSlug'));

            $userSlug = $request->route('userSlug');
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : '';

            // Get plan details
            $plan = CoworkingMembershipPlan::find($request->plan_id);
            if (!$plan) {
                return redirect()->back()->with('error', __('Plan not found.'));
            }

            $company_settings = getCompanyAllSetting($userId);
            $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

            $price = floatval($plan->plan_price);
            if ($price <= 0) {
                return redirect()->back()->with('error', __('Invalid payment amount.'));
            }

            try {
                $stripe_formatted_price = in_array(
                    $company_currancy,
                    ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
                ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

                $stripe_session = $this->createStripeSession([
                    'api_key' => $company_settings['stripe_secret'] ?? '',
                    'currency' => $company_currancy,
                    'amount' => $stripe_formatted_price,
                    'product_name' => $plan->plan_name ?? 'Coworking Membership',
                    'description' => 'Coworking Space Membership Payment',
                    'metadata' => [
                        'plan_id' => $plan->id,
                        'member_name' => $request->member_name,
                        'member_email' => $request->email,
                        'type' => 'membership'
                    ],
                    'success_url' => route('coworking-space.payment.stripe.status', [
                        'return_type' => 'success',
                        'userSlug' => $userSlug
                    ]),
                    'cancel_url' => route('coworking-space.payment.stripe.status', [
                        'return_type' => 'cancel',
                        'userSlug' => $userSlug
                    ]),
                ]);

                Session::put('coworking_stripe_session', $stripe_session);

                return Inertia::render('Stripe/StripePayment', [
                    'stripe_session' => $stripe_session,
                    'stripe_key' => $company_settings['stripe_key'] ?? ''
                ]);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }
    }

    public function coworkingSpaceGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('coworking_booking_data');
        $stripe_session = Session::get('coworking_stripe_session');
        $userSlug = Session::get('coworking_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $paymentType = $bookingData['type'] ?? 'membership';

        if ($paymentType === 'booking') {
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : null;
            $company_settings = getCompanyAllSetting($userId);

            try {
                $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

                if ($stripe_session && isset($stripe_session['id'])) {
                    $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                    if (isset($checkoutSession->payment_intent)) {
                        $payment_intent = $checkoutSession->payment_intent;
                    }
                }
            } catch (\Exception $exception) {
                return back()->with('error', $exception->getMessage());
            }

            Session::forget('coworking_stripe_session');
            Session::forget('coworking_booking_data');
            Session::forget('coworking_booking_user_slug');

            try {
                if ($request->return_type == 'success') {
                    // Create coworking booking after successful payment
                    $booking = new CoworkingBooking();
                    $booking->first_name = $bookingData['firstName'];
                    $booking->last_name = $bookingData['lastName'];
                    $booking->email = $bookingData['email'];
                    $booking->phone_no = $bookingData['phone'];
                    $booking->amenities = $bookingData['selectedAmenities'];
                    $booking->start_date_time = $bookingData['startDate'];
                    $booking->end_date_time = $bookingData['endDate'];
                    $booking->amount = $bookingData['totalAmount'];
                    $booking->booking_duration = $bookingData['duration'];
                    $booking->payment_status = 'paid';
                    $booking->payment_method = 'Stripe';
                    $booking->special_requests = $bookingData['specialRequests'];
                    $booking->creator_id = $userId;
                    $booking->created_by = $userId;
                    $booking->save();

                    try {
                        CoworkingBookingPayments::dispatch($booking);
                    } catch (\Throwable $th) {
                        return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('coworking-space.home', ['userSlug' => $userSlug])
                        ->with('success', __('Payment completed and booking confirmed successfully! Booking #:number', ['number' => $booking->booking_number]));
                } else {
                    return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])
                        ->with('error', __('Payment was cancelled.'));
                }
            } catch (\Exception $exception) {
                return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])
                    ->with('error', $exception->getMessage());
            }
        } else {
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : null;
            $company_settings = getCompanyAllSetting($userId);

            try {
                $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');

                if ($stripe_session && isset($stripe_session['id'])) {
                    $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                    if (isset($checkoutSession->payment_intent)) {
                        $payment_intent = $checkoutSession->payment_intent;
                    }
                }
            } catch (\Exception $exception) {
                return back()->with('error', $exception->getMessage());
            }

            Session::forget('coworking_stripe_session');
            Session::forget('coworking_booking_data');
            Session::forget('coworking_booking_user_slug');

            try {
                if ($request->return_type == 'success') {
                    $plan = CoworkingMembershipPlan::find($bookingData['plan_id']);
                    if (!$plan) {
                        return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])
                            ->with('error', __('Plan not found.'));
                    }

                    // Create coworking membership after successful payment
                    $membership = new CoworkingMembership();
                    $membership->member_name = $bookingData['member_name'];
                    $membership->email = $bookingData['email'];
                    $membership->phone_no = $bookingData['phone_no'];
                    $membership->membership_plan_id = $bookingData['plan_id'];
                    $membership->duration = $plan->duration;
                    $membership->price = $plan->plan_price;
                    $membershipController = new CoworkingMembershipController();
                    $membership->plan_expiry_date  = $membershipController->calculateExpiryDate($plan->duration);
                    $membership->plan_status = 'Active';
                    $membership->payment_method = 'Stripe';
                    $membership->payment_status = 'paid';
                    $membership->creator_id = $userId;
                    $membership->created_by = $userId;
                    $membership->save();

                    try {
                        CoworkingMembershipPayments::dispatch($membership);
                    } catch (\Throwable $th) {
                        return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])
                        ->with('success', __('Payment completed and membership activated successfully!'));
                } else {
                    return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])
                        ->with('error', __('Payment was cancelled.'));
                }
            } catch (\Exception $exception) {
                return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])
                    ->with('error', $exception->getMessage());
            }
        }
    }

    public function sportsClubPayWithStripe(Request $request)
    {
        $bookingData = [
            'ground_id' => $request->ground_id,
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
            'booked_by' => $request->booked_by,
            'booking_date' => $request->booking_date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'facilities' => $request->facilities ?? [],
            'special_requirements' => $request->special_requirements,
            'purpose' => $request->purpose,
            'total_amount' => $request->total_amount
        ];

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $ground = SportsClubGround::findOrFail($request->ground_id);
        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        // Calculate total amount
        $totalAmount = floatval($request->total_amount);

        if ($totalAmount <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        Session::put('sports_club_booking_data', $bookingData);
        Session::put('sports_club_booking_user_slug', $request->route('userSlug'));

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($totalAmount, 2, '.', '') : number_format($totalAmount, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => $ground->name ?? 'Sports Ground Booking',
                'description' => 'Sports Club Ground Booking Payment',
                'metadata' => [
                    'ground_id' => $ground->id,
                    'customer_name' => $request->name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('sports-club.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('sports-club.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('sports_club_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function sportsClubGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('sports_club_booking_data');
        $stripe_session = Session::get('sports_club_stripe_session');
        $userSlug = Session::get('sports_club_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;
            $receipt_url = "";

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            $receipt_url = "";
        }

        Session::forget('sports_club_stripe_session');
        Session::forget('sports_club_booking_data');
        Session::forget('sports_club_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Create booking
                $booking = new SportsClubAndGroundOrder();
                $booking->name = $bookingData['name'];
                $booking->email = $bookingData['email'];
                $booking->mobile_no = $bookingData['mobile_number'];
                $booking->booked_by = $bookingData['booked_by'];
                $booking->sports_club_id = $bookingData['ground_id'];
                $booking->date = $bookingData['booking_date'];
                $booking->start_date = $bookingData['start_date'] ?? null;
                $booking->end_date = $bookingData['end_date'] ?? null;
                $booking->start_time = $bookingData['start_time'] ?? null;
                $booking->end_time = $bookingData['end_time'] ?? null;
                $booking->total_amount = $bookingData['total_amount'];
                $booking->notes = $bookingData['special_requirements'];
                $booking->purpose = $bookingData['purpose'];
                $booking->transaction_id = $payment_intent;
                $booking->payment_type = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->creator_id = $userId;
                $booking->created_by = $userId;
                $booking->save();

                // Store selected facilities
                if (!empty($bookingData['facilities']) && is_array($bookingData['facilities'])) {
                    foreach ($bookingData['facilities'] as $facilityId) {
                        $facility = SportsClubFacility::find($facilityId);
                        if ($facility) {
                            $bookingFacility = new SportsClubBookingFacility();
                            $bookingFacility->booking_id = $booking->id;
                            $bookingFacility->facility_id = $facilityId;
                            $bookingFacility->facility_name = $facility->name;
                            $bookingFacility->facility_amount = $facility->amount;
                            $bookingFacility->creator_id = $userId;
                            $bookingFacility->created_by = $userId;
                            $bookingFacility->save();
                        }
                    }
                }

                try {
                    SportsClubBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                // Redirect back to booking page with success and booking data
                $encryptedBookingId = encrypt($booking->id);
                $redirectUrl = route('sports-academy.booking', ['userSlug' => $userSlug]) . '?step=4&booking_id=' . $encryptedBookingId;
                return redirect($redirectUrl)->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function sportsClubPlanPayWithStripe(Request $request)
    {
        $planData = [
            'plan_id' => $request->plan_id,
            'user_email' => $request->user_email,
        ];

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $plan = SportsClubMembershipPlan::findOrFail($request->plan_id);
        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $totalAmount = floatval($plan->price);

        if ($totalAmount <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        Session::put('sports_club_plan_data', $planData);
        Session::put('sports_club_plan_user_slug', $request->route('userSlug'));

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($totalAmount, 2, '.', '') : number_format($totalAmount, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => $plan->name ?? 'Sports Club Membership Plan',
                'description' => 'Sports Club Membership Plan Payment',
                'metadata' => [
                    'plan_id' => $plan->id,
                    'user_email' => $request->user_email,
                ],
                'success_url' => route('sports-club-plan.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('sports-club-plan.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('sports_club_plan_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function sportsClubPlanGetStripeStatus(Request $request)
    {
        $planData = Session::get('sports_club_plan_data');
        $stripe_session = Session::get('sports_club_plan_stripe_session');
        $userSlug = Session::get('sports_club_plan_user_slug');

        if (!$planData) {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;
            $receipt_url = "";

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            $receipt_url = "";
        }

        Session::forget('sports_club_plan_stripe_session');
        Session::forget('sports_club_plan_data');
        Session::forget('sports_club_plan_user_slug');

        try {
            if ($request->return_type == 'success') {
                $plan = SportsClubMembershipPlan::findOrFail($planData['plan_id']);
                $member = SportsClubMember::where('created_by', $userId)
                    ->where('email', $planData['user_email'])
                    ->first();

                if (!$member) {
                    return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Member not found.'));
                }

                // Create plan payment
                $planPayment = new SportsClubMembershipPlanPayment();
                $planPayment->member_id = $member->id;
                $planPayment->membershipplan_id = $plan->id;
                $planPayment->fee = $plan->price;
                $planPayment->duration = $plan->duration;
                $planPayment->date = now()->toDateString();
                $planPayment->start_date = now()->toDateString();
                $planPayment->end_date = $plan->calculateEndDate()->toDateString();
                $planPayment->reference_number = $payment_intent;
                $planPayment->status = 'accepted';
                $planPayment->creator_id = $userId;
                $planPayment->created_by = $userId;
                $planPayment->save();

                // Create assignment record
                $assignment = new SportsClubAssignedMembership();
                $assignment->member_id = $member->id;
                $assignment->membershipplan_id = $plan->id;
                $assignment->start_date = now()->toDateString();
                $assignment->end_date = $plan->calculateEndDate()->toDateString();
                $assignment->status = 'accepted';
                $assignment->duration = $plan->duration;
                $assignment->fee = $plan->price;
                $assignment->payment_type = 'Stripe';
                $assignment->creator_id = $userId;
                $assignment->created_by = $userId;
                $assignment->save();

                try {
                    SportsClubPlanPayments::dispatch($request, $assignment);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('success', __('Payment completed and plan subscription confirmed successfully!'));
            } else {
                return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }


    // Influencer Marketing Deposit Stripe Payment
    public function influencerMarketingPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }
        $amount = floatval($request->amount ?? 0);
        $brandId = $request->brand_id;

        if ($amount <= 0) {
            return redirect()->back()->with('error', __('Invalid deposit amount.'));
        }

        Session::put('influencer_marketing_deposit_data', [
            'amount' => $amount,
            'brand_id' => $brandId,
            'payment_method' => 'Stripe'
        ]);
        Session::put('influencer_marketing_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($amount, 2, '.', '') : number_format($amount, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'NGO Donation',
                'description' => 'Donation Payment',
                'metadata' => [
                    'donor_name' => $request->donor_name,
                    'donor_email' => $request->donor_email,
                    'campaign_id' => $request->campaign_id,
                ],
                'success_url' => route('influencer-marketing.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('influencer-marketing.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('influencer_marketing_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function influencerMarketingGetStripeStatus(Request $request)
    {
        try {
            $depositData = Session::get('influencer_marketing_deposit_data');
            $stripe_session = Session::get('influencer_marketing_stripe_session');
            $userSlug = Session::get('influencer_marketing_user_slug');

            if (!$depositData) {
                return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                    ->with('error', __('Something went wrong, Please try again,'));
            }

            Session::forget('influencer_marketing_stripe_session');
            Session::forget('influencer_marketing_deposit_data');
            Session::forget('influencer_marketing_user_slug');

            $user = User::where('slug', $userSlug)->first();
            $company_settings = getCompanyAllSetting($user->id);

            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;
            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }

            if ($request->return_type == 'success') {
                $deposit = new InfluencerMarketingDeposit();
                $deposit->brand_id = $depositData['brand_id'];
                $deposit->amount = $depositData['amount'];
                $deposit->payment_type = 'Stripe';
                $deposit->payment_status = 'paid';
                $deposit->transaction_id = $payment_intent;
                $deposit->created_by = $user->id;
                $deposit->save();

                try {
                    InfluencerMarketingPayment::dispatch($deposit);
                } catch (\Exception $th) {
                    return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                        ->with('error', $th->getMessage());
                }

                return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                    ->with('success', __('Deposit completed successfully!'));
            } else {
                return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function waterParkBookingPayWithStripe(Request $request)
    {
        $bookingData = [
            'email' => $request->email,
            'full_name' => $request->full_name,
            'phone' => $request->phone,
            'adults' => $request->adults,
            'children' => $request->children,
            'booking_date' => $request->booking_date,
            'event_id' => $request->event_id,
            'total_amount' => $request->total_amount,
            'payment_method' => 'stripe'
        ];

        Session::put('water_park_booking_data', $bookingData);
        Session::put('water_park_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Water Park Booking',
                'description' => 'Water Park Booking Payment',
                'metadata' => [
                    'customer_email' => $request->email,
                    'event_id' => $request->event_id,
                ],
                'success_url' => route('water-park.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('water-park.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('water_park_booking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function waterParkBookingGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('water_park_booking_data');
        $stripe_session = Session::get('water_park_booking_stripe_session');
        $userSlug = Session::get('water_park_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('water_park_booking_stripe_session');
        Session::forget('water_park_booking_data');
        Session::forget('water_park_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $booking = new WaterParkBooking();
                $booking->email = $bookingData['email'];
                $booking->full_name = $bookingData['full_name'];
                $booking->phone = $bookingData['phone'];
                $booking->adults = $bookingData['adults'];
                $booking->children = $bookingData['children'];
                $booking->booking_date = $bookingData['booking_date'];
                $booking->event_id = $bookingData['event_id'];
                $booking->total_amount = $bookingData['total_amount'];
                $booking->payment_method = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->booking_status = 'confirmed';
                $booking->transaction_id = $payment_intent;
                $booking->creator_id = $userId;
                $booking->created_by = $userId;
                $booking->save();

                try {
                    WaterParkBookingPaymentStripe::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function tvStudioPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user     = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $customer = auth('tvstudio_customer')->user();
        if (!$customer) {
            return redirect()->route('tvstudio.frontend.login', ['userSlug' => $userSlug]);
        }

        try {
            // Get order data from cart
            $orderData = TVStudioCheckoutService::prepareOrderData($customer->id, $user->id);

            $total = $orderData['total'];

            if ($total <= 0) {
                throw new \Exception(__('Invalid payment amount.'));
            }
        } catch (\Throwable $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        Session::put('tvstudio_order_data', $orderData);
        Session::put('tvstudio_user_slug', $userSlug);

        $company_settings     = getCompanyAllSetting($user->id);
        $company_currancy     = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $total = number_format($total, 2, '.', '');

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? $total : $total * 100;

            $stripe_session = $this->createStripeSession([
                'api_key'      => $company_settings['stripe_secret'] ?? '',
                'currency'     => $company_currancy,
                'amount'       => $stripe_formatted_price,
                'product_name' => 'TV Studio Content Purchase',
                'description'  => 'Movies & TV Shows Purchase',
                'metadata'     => [
                    'customer_id'    => $customer->id,
                    'customer_name'  => $customer->first_name . ' ' . $customer->last_name,
                    'customer_email' => $customer->email,
                    'items_count'    => count($orderData['cart_items'])
                ],
                'success_url'  => route('tvstudio.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug'    => $userSlug
                ]),
                'cancel_url'   => route('tvstudio.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug'    => $userSlug
                ]),
            ]);

            Session::put('tvstudio_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key'     => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function tvStudioGetStripeStatus(Request $request)
    {
        $userSlug       = $request->route('userSlug') ?? Session::get('tvstudio_user_slug');
        $orderData      = Session::get('tvstudio_order_data');
        $stripe_session = Session::get('tvstudio_stripe_session');

        if (!$orderData) {
            return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user     = User::where('slug', $userSlug)->first();
        $customer = auth('tvstudio_customer')->user();

        if (!$user || !$customer) {
            return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe         = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('tvstudio_stripe_session');
        Session::forget('tvstudio_order_data');
        Session::forget('tvstudio_user_slug');

        try {
            if ($request->return_type == 'success') {
                $order = TVStudioCheckoutService::createOrder(
                    $orderData,
                    $customer->id,
                    $user->id,
                    'Stripe',
                    $payment_intent
                );

                return redirect()->route('tvstudio.frontend.order-complete', ['userSlug' => $userSlug]);
            } else {
                return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Throwable $exception) {
            return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    // ArtShowcase Stripe Payment
    public function artShowcasePayWithStripe(Request $request)
    {
        $purchaseData = [
            'art_work_id' => $request->art_work_id,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'payment_method' => 'Stripe'
        ];

        Session::put('art_showcase_purchase_data', $purchaseData);
        Session::put('art_showcase_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        // Get artwork details
        $artwork = ArtShowcaseArtWork::where('id', $request->art_work_id)
            ->where('created_by', $user->id)
            ->first();

        if (!$artwork) {
            return redirect()->back()->with('error', __('Artwork not found.'));
        }

        if ($artwork->status !== 'available') {
            return redirect()->back()->with('error', __('This artwork is no longer available for purchase.'));
        }

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($artwork->price ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid artwork price.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => $artwork->name ?? 'Artwork Purchase',
                'description' => 'Art Showcase Artwork Purchase',
                'metadata' => [
                    'artwork_id' => $artwork->id,
                    'customer_name' => $request->full_name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('art-showcase.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('art-showcase.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('art_showcase_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function artShowcaseGetStripeStatus(Request $request)
    {
        $purchaseData = Session::get('art_showcase_purchase_data');
        $stripe_session = Session::get('art_showcase_stripe_session');
        $userSlug = Session::get('art_showcase_user_slug');

        if (!$purchaseData) {
            return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                ->with('error', __('Purchase Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                ->with('error', __('User not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;
            $receipt_url = "";

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            $receipt_url = "";
        }

        Session::forget('art_showcase_stripe_session');
        Session::forget('art_showcase_purchase_data');
        Session::forget('art_showcase_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Get artwork
                $artwork = ArtShowcaseArtWork::where('id', $purchaseData['art_work_id'])
                    ->where('created_by', $user->id)
                    ->first();

                if (!$artwork) {
                    return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                        ->with('error', __('Artwork not found.'));
                }

                if ($artwork->status !== 'available') {
                    return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                        ->with('error', __('This artwork is no longer available for purchase.'));
                }

                // Create purchase record
                $purchase = new ArtShowcaseArtWorkOrder();
                $purchase->art_work_id = $artwork->id;
                $purchase->customer_full_name = $purchaseData['full_name'];
                $purchase->customer_email = $purchaseData['email'];
                $purchase->contact_number = $purchaseData['phone'];
                $purchase->address = $purchaseData['address'];
                $purchase->total_amount = $artwork->price;
                $purchase->payment_type = 'Stripe';
                $purchase->payment_status = 'paid';
                $purchase->created_by = $user->id;
                $purchase->creator_id = $user->id;
                $purchase->save();

                // Update artwork status to sold
                $artwork->status = 'sold';
                $artwork->save();

                try {
                    CreateArtWorkOrderPayment::dispatch($request, $purchase);
                } catch (\Throwable $th) {
                    return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                        ->with('error', $th->getMessage());
                }

                return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed successfully! Your artwork purchase has been confirmed.'));
            } else {
                return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function tattooStudioBookingPayWithStripe(Request $request)
    {
        $bookingData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'instagram' => $request->instagram,
            'date' => $request->date,
            'time' => $request->time,
            'duration' => $request->duration,
            'placement' => $request->placement,
            'inch' => $request->inch,
            'details' => $request->details,
            'tattoo_type' => $request->tattoo_type,
            'selected_tattoo_id' => $request->selected_tattoo_id,
            'custom_price' => $request->custom_price,
            'total_amount' => $request->total_amount
        ];

        Session::put('tattoo_studio_booking_data', $bookingData);
        Session::put('tattoo_studio_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Tattoo Studio Booking',
                'description' => 'Tattoo Studio Booking Payment',
                'metadata' => [
                    'customer_email' => $request->email,
                    'customer_name' => $request->name,
                ],
                'success_url' => route('tattoo-studio.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('tattoo-studio.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('tattoo_studio_booking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function tattooStudioBookingGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('tattoo_studio_booking_data');
        $stripe_session = Session::get('tattoo_studio_booking_stripe_session');
        $userSlug = Session::get('tattoo_studio_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('tattoo_studio_booking_stripe_session');
        Session::forget('tattoo_studio_booking_data');
        Session::forget('tattoo_studio_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $booking                     = new TattooAppointment();
                $booking->name               = $bookingData['name'];
                $booking->email              = $bookingData['email'];
                $booking->phone              = $bookingData['phone'];
                $booking->instagram          = $bookingData['instagram'];
                $booking->date               = $bookingData['date'];
                $booking->time               = $bookingData['time'];
                $booking->duration           = $bookingData['duration'];
                $booking->placement          = $bookingData['placement'];
                $booking->inch               = $bookingData['inch'];
                $booking->details            = $bookingData['details'];
                $booking->tattoo_type        = $bookingData['tattoo_type'];
                $booking->selected_tattoo_id = $bookingData['selected_tattoo_id'];
                $booking->custom_price       = $bookingData['custom_price'];
                $booking->total_amount       = $bookingData['total_amount'];
                $booking->payment_method     = 'Stripe';
                $booking->payment_status     = 'paid';
                $booking->appointment_status = 'confirmed';
                $booking->transaction_id     = $payment_intent;
                $booking->creator_id         = $user->id;
                $booking->created_by         = $user->id;
                $booking->save();

                try {
                    TattooAppointmentPaymentStripe::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed and appointment confirmed successfully!'));
            } else {
                return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function photoStudioPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $bookingData = [
            'name'               => $request->name,
            'email'              => $request->email,
            'mobile_no'          => $request->mobile_no,
            'service_id'         => $request->service_id,
            'price'              => $request->price,
            'booking_start_date' => $request->booking_start_date,
            'booking_end_date'   => $request->booking_end_date,
        ];

        Session::put('photo_studio_booking_data', $bookingData);
        Session::put('photo_studio_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->price);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        $service = PhotoStudioService::find($request->service_id);

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key'      => $company_settings['stripe_secret'] ?? '',
                'currency'     => $company_currancy,
                'amount'       => $stripe_formatted_price,
                'product_name' => $service->name ?? 'Photo Studio Appointment',
                'description'  => 'Photo Studio Appointment Booking',
                'metadata'     => [
                    'service_id'     => $request->service_id,
                    'customer_name'  => $request->name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('photo-studio.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug'    => $userSlug
                ]),
                'cancel_url' => route('photo-studio.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug'    => $userSlug
                ]),
            ]);

            Session::put('photo_studio_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key'     => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function photoStudioGetStripeStatus(Request $request)
    {
        $bookingData    = Session::get('photo_studio_booking_data');
        $stripe_session = Session::get('photo_studio_stripe_session');
        $userSlug       = Session::get('photo_studio_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }
        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('photo_studio_stripe_session');
        Session::forget('photo_studio_booking_data');
        Session::forget('photo_studio_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $service = PhotoStudioService::find($bookingData['service_id']);

                $appointment = new PhotoStudioAppointment();
                $appointment->name               = $bookingData['name'];
                $appointment->email              = $bookingData['email'];
                $appointment->mobile_no          = $bookingData['mobile_no'];
                $appointment->service_id         = $bookingData['service_id'];
                $appointment->price              = $bookingData['price'];
                $appointment->booking_start_date = $bookingData['booking_start_date'];
                $appointment->booking_end_date   = $bookingData['booking_end_date'];
                $appointment->status             = 'pending';
                $appointment->payment_status     = 'confirmed';
                $appointment->creator_id         = $user->id;
                $appointment->created_by         = $user->id;
                $appointment->save();

                $payment = new PhotoStudioAppointmentPayment();
                $payment->appointment_id     = $appointment->id;
                $payment->appointment_number = $appointment->appointment_number;
                $payment->customer_name      = $bookingData['name'];
                $payment->service_name       = $service->name ?? '';
                $payment->payment_date       = now();
                $payment->amount             = $bookingData['price'];
                $payment->payment_status     = 'cleared';
                $payment->payment_type       = 'Stripe';
                $payment->description        = 'Payment via Stripe';
                $payment->creator_id         = $user->id;
                $payment->created_by         = $user->id;
                $payment->save();

                try {
                    PhotoStudioAppointmentPayments::dispatch($appointment, $payment);
                } catch (\Throwable $th) {
                    return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed and appointment booked successfully!'));
            } else {
                return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function ebookPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $customer = auth('ebook')->user();

        if ($user && $customer) {
            try {

                $company_settings = getCompanyAllSetting($user->id);
                $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

                $check =  EbookBookOrder::CheckPreOrder($user, $customer);

                if ($check['success']) {

                    $total = $request->total ?? 0;
                    $stripe_formatted_price = in_array(
                        $company_currancy,
                        ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
                    ) ? number_format($total, 2, '.', '') : number_format($total, 2, '.', '') * 100;

                    $stripe_session = $this->createStripeSession([
                        'api_key' => $company_settings['stripe_secret'] ?? '',
                        'currency' => $company_currancy,
                        'amount' => $stripe_formatted_price,
                        'product_name' => 'Ebook Purchase',
                        'description' => 'Digital Book Purchase',
                        'metadata' => [
                            'customer_id' => $customer->id,
                            'customer_name' => $customer->full_name,
                            'customer_email' => $customer->email,
                        ],
                        'success_url' => route('ebook.payment.stripe.status', [
                            'return_type' => 'success',
                            'userSlug' => $userSlug,
                            'customerId' => $customer->id
                        ]),
                        'cancel_url' => route('ebook.payment.stripe.status', [
                            'return_type' => 'cancel',
                            'userSlug' => $userSlug,
                            'customerId' => $customer->id
                        ]),
                    ]);

                    Session::put('ebook_stripe_session', $stripe_session);

                    return Inertia::render('Stripe/StripePayment', [
                        'stripe_session' => $stripe_session,
                        'stripe_key' => $company_settings['stripe_key'] ?? ''
                    ]);
                }
                throw new \Exception($check['message']);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }
        return redirect()->route('ebook.frontend.login', ['userSlug' => $userSlug]);
    }

    public function ebookGetStripeStatus(Request $request, $userSlug)
    {
        $stripe_session = Session::get('ebook_stripe_session');
        Session::forget('ebook_stripe_session');

        $user = User::where('slug', $userSlug)->first();
        $customer = auth('ebook')->user();

        try {
            $status = $request->return_type == 'success';

            if ($status) {

                $order = EbookBookOrder::MakeOrder(
                    "Stripe",
                    $user,
                    $request->customerId ?? null,
                    $status,
                    $stripe_session->id ?? null
                );

                EbookPayment::dispatch($order);

                return redirect()->route('ebook.frontend.index', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed successfully'));
            } else {
                return redirect()->route('ebook.frontend.checkout', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('ebook.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }


    public function yogaClassesPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $member = auth('yoga_member')->user();
        $instructor = auth('yoga_instructor')->user();

        if (!$member && !$instructor) {
            return redirect()->route('yoga-classes.frontend.login', ['userSlug' => $userSlug]);
        }

        $companySettings = getCompanyAllSetting($user->id);
        $companyCurrency = !empty($companySettings['defaultCurrency']) ? $companySettings['defaultCurrency'] : 'USD';

        $cartQuery = YogaClassesCart::where('created_by', $user->id)
            ->with(['course', 'course.instructors']);

        if ($member) {
            $cartQuery->where('member_id', $member->id);
        } else {
            $cartQuery->where('instructor_id', $instructor->id);
        }

        $cartItems = $cartQuery->get()->filter(function ($item) {
            $course = $item->course;

            return $course
                && (string) $course->status === '1'
                && (string) $course->approved_by_owner === '1';
        })->values();

        if ($cartItems->isEmpty()) {
            return redirect()->route('yoga-classes.frontend.cart', ['userSlug' => $userSlug])
                ->with('error', __('Your cart is empty'));
        }

        $subtotal = 0;
        $discount = 0;
        foreach ($cartItems as $cartItem) {
            $course = $cartItem->course;
            $showLatestPrice = in_array($course->show_latest_price, [true, 1, '1', 'true'], true);
            $currentPrice = $showLatestPrice
                ? (float) ($course->latest_price ?? $cartItem->price ?? 0)
                : (float) ($course->regular_price ?? $cartItem->price ?? 0);
            $comparePrice = (float) ($course->regular_price ?? 0);
            $shouldShowOriginalPrice = $showLatestPrice && $comparePrice > 0 && $currentPrice > 0 && $comparePrice > $currentPrice;

            $subtotal += $shouldShowOriginalPrice ? $comparePrice : $currentPrice;
            $discount += $shouldShowOriginalPrice ? ($comparePrice - $currentPrice) : 0;
        }

        $total = $subtotal - $discount;

        if ($total <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        Session::put('yoga_classes_order_data', [
            'payment_method' => 'Stripe',
            'payment_note' => $request->payment_note,
            'total' => $total,
        ]);
        Session::put('yoga_classes_user_slug', $userSlug);

        try {
            $stripeFormattedPrice = in_array(
                $companyCurrency,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($total, 2, '.', '') : number_format($total, 2, '.', '') * 100;

            $stripeSession = $this->createStripeSession([
                'api_key' => $companySettings['stripe_secret'] ?? '',
                'currency' => $companyCurrency,
                'amount' => $stripeFormattedPrice,
                'product_name' => 'Yoga Classes Purchase',
                'description' => 'Yoga course payment',
                'metadata' => [
                    'actor_type' => $member ? 'member' : 'instructor',
                    'actor_name' => $member?->name ?? $instructor?->name ?? '',
                    'actor_email' => $member?->email ?? $instructor?->email ?? '',
                    'course_count' => $cartItems->count(),
                ],
                'success_url' => route('yoga-classes.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug,
                ]),
                'cancel_url' => route('yoga-classes.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug,
                ]),
            ]);

            Session::put('yoga_classes_stripe_session', $stripeSession);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripeSession,
                'stripe_key' => $companySettings['stripe_key'] ?? '',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function yogaClassesGetStripeStatus(Request $request)
    {
        $orderData = Session::get('yoga_classes_order_data');
        $stripeSession = Session::get('yoga_classes_stripe_session');
        $userSlug = Session::get('yoga_classes_user_slug');

        if (!$orderData) {
            return redirect()->route('yoga-classes.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $member = auth('yoga_member')->user();
        $instructor = auth('yoga_instructor')->user();

        if (!$user || (!$member && !$instructor)) {
            return redirect()->route('yoga-classes.frontend.index', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }

        $companySettings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($companySettings['stripe_secret']) ? $companySettings['stripe_secret'] : '');
            $paymentIntent = null;
            $receiptUrl = '';

            if ($stripeSession && isset($stripeSession['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripeSession['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntent = $checkoutSession->payment_intent;
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receiptUrl = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            $paymentIntent = null;
            $receiptUrl = '';
        }

        Session::forget('yoga_classes_stripe_session');
        Session::forget('yoga_classes_order_data');
        Session::forget('yoga_classes_user_slug');

        try {
            if ($request->return_type == 'success') {
                $cartQuery = YogaClassesCart::where('created_by', $user->id)
                    ->with(['course', 'course.instructors']);

                if ($member) {
                    $cartQuery->where('member_id', $member->id);
                } else {
                    $cartQuery->where('instructor_id', $instructor->id);
                }

                $cartItems = $cartQuery->get()->filter(function ($item) {
                    $course = $item->course;

                    return $course
                        && (string) $course->status === '1'
                        && (string) $course->approved_by_owner === '1';
                })->values();

                if ($cartItems->isEmpty()) {
                    return redirect()->route('yoga-classes.frontend.cart', ['userSlug' => $userSlug])
                        ->with('error', __('Your cart is empty'));
                }

                $transactionReference = $paymentIntent ?: ($stripeSession['id'] ?? null);
                $courseSnapshots = [];
                $subtotal = 0;
                $discountAmount = 0;

                foreach ($cartItems as $cartItem) {
                    $course = $cartItem->course;
                    $showLatestPrice = in_array($course->show_latest_price, [true, 1, '1', 'true'], true);
                    $currentPrice = $showLatestPrice
                        ? (float) ($course->latest_price ?? $cartItem->price ?? 0)
                        : (float) ($course->regular_price ?? $cartItem->price ?? 0);
                    $comparePrice = (float) ($course->regular_price ?? 0);
                    $shouldShowOriginalPrice = $showLatestPrice && $comparePrice > 0 && $currentPrice > 0 && $comparePrice > $currentPrice;
                    $lineSubtotal = $shouldShowOriginalPrice ? $comparePrice : $currentPrice;
                    $lineDiscount = $shouldShowOriginalPrice ? ($comparePrice - $currentPrice) : 0;

                    $subtotal += $lineSubtotal;
                    $discountAmount += $lineDiscount;

                    $courseSnapshots[] = [
                        'id' => $course->id,
                        'encrypted_id' => $course->getEncryptedId(),
                        'title' => $course->title,
                        'image' => $course->course_thumbnail,
                        'lessons' => $course->number_of_lessons,
                        'duration' => $course->duration,
                        'regular_price' => $course->regular_price,
                        'latest_price' => $course->latest_price,
                        'show_latest_price' => $course->show_latest_price,
                        'instructor_name' => $course?->instructors?->name ?? $instructor?->name,
                        'amount' => $lineSubtotal,
                        'discount_amount' => $lineDiscount,
                        'total_amount' => $currentPrice,
                    ];
                }

                $course_order = new YogaClassesOrder();
                $course_order->order_number = YogaClassesOrder::generateOrderNumber($user->id);
                $course_order->name = $member?->name ?? $instructor?->name ?? null;
                $course_order->course = $courseSnapshots;
                $course_order->instructor_id = $instructor?->id;
                $course_order->member_id = $member?->id;
                $course_order->amount = $subtotal;
                $course_order->currency = $cartItems->first()?->currency ?: 'USD';
                $course_order->payment_method = 'Stripe';
                $course_order->payment_status = 'paid';
                $course_order->transaction_id = $transactionReference;
                $course_order->receipt = $receiptUrl;
                $course_order->order_date = now();
                $course_order->notes = $orderData['payment_note'] ?? null;
                $course_order->discount_amount = $discountAmount;
                $course_order->tax_amount = 0;
                $course_order->total_amount = $subtotal - $discountAmount;
                $course_order->created_by = $user->id;
                $course_order->save();

                // Add purchased courses to table
                foreach ($courseSnapshots as $courseSnapshot) {
                    $purchased_courses = new YogaClassesPurchasedCourse();
                    $purchased_courses->member_id = $member?->id;
                    $purchased_courses->instructor_id = $instructor?->id;
                    $purchased_courses->course_id = $courseSnapshot['id'];
                    $purchased_courses->order_id = $course_order->id;
                    $purchased_courses->purchase_price = $courseSnapshot['total_amount'];
                    $purchased_courses->currency = $cartItems->first()?->currency ?: 'USD';
                    $purchased_courses->purchased_at = now();
                    $purchased_courses->created_by = $user->id;
                    $purchased_courses->save();
                }

                $cartItems->each->delete();

                try {
                    YogaClassesOrderPayments::dispatch($course_order);
                } catch (\Throwable $th) {
                    return redirect()->route('yoga-classes.frontend.index', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('yoga-classes.frontend.order-success', ['userSlug' => $userSlug, 'reference' => $transactionReference])
                    ->with('success', __('Payment completed successfully! Order #:number', ['number' => $course_order->order_number]));
            }

            return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', __('Payment was cancelled.'));
        } catch (\Exception $exception) {
            return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function hairCareStudioPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user     = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $bookingData = [
            'full_name' => $request->full_name,
            'email' => $request->email,
            'mobile_no' => $request->mobile_no,
            'service_id' => $request->service_id,
            'preferred_date' => $request->preferred_date,
            'preferred_time' => $request->preferred_time,
            'stylist_type' => $request->stylist_type,
            'charges' => $request->charges,
            'special_request' => $request->special_request,
            'total_amount' => $request->total_amount
        ];

        Session::put('hair_care_studio_booking_data', $bookingData);
        Session::put('hair_care_studio_booking_user_slug', $request->route('userSlug'));

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key' => $company_settings['stripe_secret'] ?? '',
                'currency' => $company_currancy,
                'amount' => $stripe_formatted_price,
                'product_name' => 'Hair Care Studio Booking',
                'description' => 'Hair Care Studio Booking Payment',
                'metadata' => [
                    'customer_email' => $request->email,
                    'customer_name' => $request->full_name,
                ],
                'success_url' => route('hair-care-studio.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ]),
                'cancel_url' => route('hair-care-studio.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug' => $userSlug
                ]),
            ]);

            Session::put('hair_care_studio_booking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key' => $company_settings['stripe_key'] ?? ''
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function hairCareStudioGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('hair_care_studio_booking_data');
        $stripe_session = Session::get('hair_care_studio_booking_stripe_session');
        $userSlug = Session::get('hair_care_studio_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }
        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('hair_care_studio_booking_stripe_session');
        Session::forget('hair_care_studio_booking_data');
        Session::forget('hair_care_studio_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                // Create appointment after successful payment
                $appointment = new HairCareAppointment();
                $appointment->full_name = $bookingData['full_name'];
                $appointment->email = $bookingData['email'];
                $appointment->mobile_no = $bookingData['mobile_no'];
                $appointment->service_id = $bookingData['service_id'];
                $appointment->preferred_date = $bookingData['preferred_date'];
                $appointment->preferred_time = $bookingData['preferred_time'];
                $appointment->stylist_type = $bookingData['stylist_type'];
                $appointment->charges = $bookingData['charges'];
                $appointment->special_request = $bookingData['special_request'] ?? null;
                $appointment->payment_status = 'paid';
                $appointment->creator_id = $user->id;
                $appointment->created_by = $user->id;
                $appointment->save();

                // Create payment record
                $haircarepayment = new HairCarePayment();
                $haircarepayment->appointment_id = $appointment->id;
                $haircarepayment->payment_date = now();
                $haircarepayment->amount = $bookingData['charges'];
                $haircarepayment->transaction_id = $payment_intent;
                $haircarepayment->payment_method = 'Stripe';
                $haircarepayment->payment_status = 'cleared';
                $haircarepayment->notes = 'Payment via Stripe';
                $haircarepayment->creator_id = $user->id;
                $haircarepayment->created_by = $user->id;
                $haircarepayment->save();

                try {
                    HairCareStudioOrderPayments::dispatch($haircarepayment);
                } catch (\Throwable $th) {
                    return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                }

                return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed and appointment booked successfully!'));
            } else {
                return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function petCarePayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $paymentType = $request->input('type', 'membership');

        if ($user && $paymentType) {
            try {
                $company_settings = getCompanyAllSetting($user->id);
                $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';


                if ($paymentType === 'service') {
                    // Service Booking Payment
                    $service = PetCareService::find($request->service);
                    if (!$service) {
                        return redirect()->back()->with('error', __('Service not found.'));
                    }

                    $total = floatval($request->price ?? 0);
                    if ($total <= 0) {
                        return redirect()->back()->with('error', __('Invalid payment amount.'));
                    }

                    $bookingData = [
                        'name' => $request->name,
                        'email' => $request->email,
                        'phone_number' => $request->phone_number,
                        'address' => $request->address,
                        'service' => $request->service,
                        'date' => $request->date,
                        'time_slot' => $request->time_slot,
                        'price' => $request->price,
                        'note' => $request->note,
                        'pet_name' => $request->pet_name,
                        'species_breed' => $request->species_breed,
                        'date_of_birth' => $request->date_of_birth,
                        'gender' => $request->gender,
                        'payment_method' => 'Stripe',
                        'type' => $paymentType,
                        'amount' => $total,
                    ];

                    $productName = $service->name ?? 'Pet Care Service';
                    $description = 'Pet Care Service Booking';
                } else {
                    // Membership Payment
                    $package = PetCareGroomingPackage::find($request->package_id);
                    if (!$package) {
                        return redirect()->back()->with('error', __('Package not found.'));
                    }

                    $total = floatval($package->price ?? 0);
                    if ($total <= 0) {
                        return redirect()->back()->with('error', __('Invalid payment amount.'));
                    }

                    $bookingData = [
                        'name' => $request->name,
                        'phone_no' => $request->phone_no,
                        'email' => $request->email,
                        'package_id' => $request->package_id,
                        'pet_name' => $request->pet_name,
                        'breed' => $request->breed,
                        'date_of_birth' => $request->date_of_birth,
                        'gender' => $request->gender,
                        'address' => $request->address,
                        'notes' => $request->notes,
                        'payment_method' => 'Stripe',
                        'type' => $paymentType,
                        'amount' => $total,
                    ];

                    $productName = $package->name ?? 'Pet Care Package';
                    $description = 'Pet Care Membership Subscription';
                }

                Session::put('pet_care_booking_data', $bookingData);
                Session::put('pet_care_booking_user_slug', $request->route('userSlug'));

                $stripe_formatted_price = in_array(
                    $company_currancy,
                    ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
                ) ? number_format($total, 2, '.', '') : number_format($total, 2, '.', '') * 100;

                $stripe_session = $this->createStripeSession([
                    'api_key' => $company_settings['stripe_secret'] ?? '',
                    'currency' => $company_currancy,
                    'amount' => $stripe_formatted_price,
                    'product_name' => $productName,
                    'description' => $description,
                    'metadata' => [
                        'customer_name' => $request->name,
                        'customer_email' => $request->email,
                        'pet_name' => $request->pet_name,
                        'type' => $paymentType,
                    ],
                    'success_url' => route('pet-care.payment.stripe.status', [
                        'return_type' => 'success',
                        'userSlug' => $userSlug
                    ]),
                    'cancel_url' => route('pet-care.payment.stripe.status', [
                        'return_type' => 'cancel',
                        'userSlug' => $userSlug
                    ]),
                ]);

                Session::put('pet_care_stripe_session', $stripe_session);

                return Inertia::render('Stripe/StripePayment', [
                    'stripe_session' => $stripe_session,
                    'stripe_key' => $company_settings['stripe_key'] ?? ''
                ]);
            } catch (\Exception $e) {
                return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])->with('error', $e->getMessage());
            }
        }
        return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug]);
    }

    public function petCareGetStripeStatus(Request $request)
    {
        $bookingData = Session::get('pet_care_booking_data');
        $stripe_session = Session::get('pet_care_stripe_session');
        $userSlug = Session::get('pet_care_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $paymentType = $bookingData['type'] ?? 'membership';
        $user = User::where('slug', $userSlug)->first();
        $userId = $user->id;

        Session::forget('pet_care_stripe_session');
        Session::forget('pet_care_booking_data');
        Session::forget('pet_care_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                if ($paymentType === 'service') {
                    // Service Booking Payment Success
                    $service = PetCareService::find($bookingData['service']);
                    if (!$service) {
                        return redirect()->route('pet-care.frontend.services', ['userSlug' => $userSlug])
                            ->with('error', __('Service not found.'));
                    }

                    $times = explode('-', $bookingData['time_slot']);

                    // Create service booking
                    $booking = new PetCareBooking();
                    $booking->name = $bookingData['name'];
                    $booking->email = $bookingData['email'];
                    $booking->phone_number = $bookingData['phone_number'];
                    $booking->address = $bookingData['address'];
                    $booking->service = $bookingData['service'];
                    $booking->date = $bookingData['date'];
                    $booking->start_time = $times[0];
                    $booking->end_time = $times[1];
                    $booking->price = $bookingData['price'];
                    $booking->note = $bookingData['note'];
                    $booking->pet_name = $bookingData['pet_name'];
                    $booking->species_breed = $bookingData['species_breed'];
                    $booking->date_of_birth = $bookingData['date_of_birth'];
                    $booking->gender = $bookingData['gender'];
                    $booking->payment_method = 'Stripe';
                    $booking->payment_status = 'paid';
                    $booking->booking_status = 'completed';
                    $booking->creator_id = $userId;
                    $booking->created_by = $userId;
                    $booking->save();

                    try {
                        PetCareBookingPayment::dispatch($booking);
                    } catch (\Throwable $th) {
                        return redirect()->route('pet-care.frontend.services', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('pet-care.frontend.services', ['userSlug' => $userSlug])
                        ->with('success', __('Payment completed successfully! Your service booking has been confirmed.'));
                } else {
                    // Membership Payment Success
                    $membership = new PetCareMembership();
                    $membership->name = $bookingData['name'];
                    $membership->phone_no = $bookingData['phone_no'];
                    $membership->email = $bookingData['email'];
                    $membership->grooming_package_id = $bookingData['package_id'];
                    $membership->amount = $bookingData['amount'];
                    $membership->pet_name = $bookingData['pet_name'];
                    $membership->breed_species = $bookingData['breed'];
                    $membership->date_of_birth = $bookingData['date_of_birth'];
                    $membership->gender = $bookingData['gender'];
                    $membership->address = $bookingData['address'];
                    $membership->special_request = $bookingData['notes'];
                    $membership->payment_method = 'Stripe';
                    $membership->payment_status = 'paid';
                    $membership->membership_status = 'approved';
                    $membership->created_by = $userId;
                    $membership->creator_id = $userId;
                    $membership->save();

                    try {
                        PetCareMembershipPayments::dispatch($membership);
                    } catch (\Throwable $th) {
                        return redirect()->route('pet-care.frontend.pricing', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('pet-care.frontend.pricing', ['userSlug' => $userSlug])
                        ->with('success',  __('Payment completed successfully! Order #:number', ['number' => $membership->membership_id]));
                }
            } else {
                $redirectRoute = $paymentType === 'service' ? 'pet-care.frontend.services' : 'pet-care.frontend.pricing';
                return redirect()->route($redirectRoute, ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function boutiqueStudioPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $bookingData = [
            'outfit_id'    => $request->outfit_id,
            'pricing_type' => $request->pricing_type,
            'size'         => $request->size,
            'outfit_price' => $request->outfit_price,
            'first_name'   => $request->first_name,
            'last_name'    => $request->last_name,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'booking_date' => $request->booking_date,
            'pickup_date'  => $request->pickup_date,
            'return_date'  => $request->return_date,
            'rental_days'  => $request->rental_days,
            'address'      => $request->address,
            'notes'        => $request->notes,
            'services'     => json_decode($request->services ?? '[]', true),
            'service_total' => $request->service_total,
            'total_amount' => $request->total_amount,
        ];

        Session::put('boutique_booking_data', $bookingData);
        Session::put('boutique_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        try {
            $stripe_formatted_price = in_array(
                $company_currancy,
                ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
            ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

            $stripe_session = $this->createStripeSession([
                'api_key'      => $company_settings['stripe_secret'] ?? '',
                'currency'     => $company_currancy,
                'amount'       => $stripe_formatted_price,
                'product_name' => 'Boutique Studio Booking',
                'description'  => 'Boutique & Designer Studio Booking Payment',
                'metadata'     => [
                    'customer_name'  => $request->first_name . ' ' . $request->last_name,
                    'customer_email' => $request->email,
                ],
                'success_url' => route('boutique-studio.payment.stripe.status', [
                    'return_type' => 'success',
                    'userSlug'    => $userSlug,
                ]),
                'cancel_url' => route('boutique-studio.payment.stripe.status', [
                    'return_type' => 'cancel',
                    'userSlug'    => $userSlug,
                ]),
            ]);

            Session::put('boutique_booking_stripe_session', $stripe_session);

            return Inertia::render('Stripe/StripePayment', [
                'stripe_session' => $stripe_session,
                'stripe_key'     => $company_settings['stripe_key'] ?? '',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function boutiqueStudioGetStripeStatus(Request $request)
    {
        $bookingData    = Session::get('boutique_booking_data');
        $stripe_session = Session::get('boutique_booking_stripe_session');
        $userSlug       = Session::get('boutique_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $company_settings = getCompanyAllSetting($user->id);

        try {
            $stripe         = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $payment_intent = null;

            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $payment_intent = $checkoutSession->payment_intent;
                }
            }
        } catch (\Exception $exception) {
            return back()->with('error', $exception->getMessage());
        }

        Session::forget('boutique_booking_stripe_session');
        Session::forget('boutique_booking_data');
        Session::forget('boutique_booking_user_slug');

        try {
            if ($request->return_type == 'success') {
                $booking                 = new BoutiqueBooking();
                $booking->outfit_id      = $bookingData['outfit_id'];
                $booking->pricing_type   = $bookingData['pricing_type'];
                $booking->size           = $bookingData['size'];
                $booking->outfit_price   = $bookingData['outfit_price'];
                $booking->first_name     = $bookingData['first_name'];
                $booking->last_name      = $bookingData['last_name'];
                $booking->email          = $bookingData['email'];
                $booking->phone          = $bookingData['phone'];
                $booking->booking_date   = $bookingData['booking_date'];
                $booking->pickup_date    = $bookingData['pickup_date'];
                $booking->return_date    = $bookingData['return_date'];
                $booking->rental_days    = $bookingData['rental_days'];
                $booking->address        = $bookingData['address'];
                $booking->notes          = $bookingData['notes'];
                $booking->services       = $bookingData['services'];
                $booking->service_total  = $bookingData['service_total'];
                $booking->total_amount   = $bookingData['total_amount'];
                $booking->payment_method = 'Stripe';
                $booking->payment_status = 'paid';
                $booking->booking_status = 'confirmed';
                $booking->transaction_id = $payment_intent;
                $booking->creator_id     = $user->id;
                $booking->created_by     = $user->id;
                $booking->save();

                try {
                    BoutiqueBookingPaymentStripe::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('boutique-and-designer-studio.frontend.booking-success', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function investmentSystemPayWithStripe(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if ($user) {
            try {
                $plan = InvestmentPlan::where('created_by', $user->id)
                    ->where('plan_status', '0')
                    ->find($request->plan_id);

                if (!$plan) {
                    return redirect()->back()->with('error', __('Investment plan not found.'));
                }

                $company_settings = getCompanyAllSetting($user->id);
                $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

                $price = (float) ($request->amount ?? 0);
                if ($price <= 0) {
                    return redirect()->back()->with('error', __('Invalid payment amount.'));
                }

                Session::put('investment_system_deposit_data', [
                    'investor_id'     => $request['investor_id'],
                    'plan_id'         => $request->plan_id,
                    'amount'          => $request->amount,
                    'plan_duration'   => $request->plan_duration,
                    'annual_return'   => $request->annual_return,
                    'expected_return' => $request->expected_return,
                    'purchase_date'   => $request->purchase_date,
                    'expiry_date'     => $request->expiry_date,
                ]);

                $stripe_formatted_price = in_array(
                    $company_currancy,
                    ['MGA', 'BIF', 'CLP', 'PYG', 'DJF', 'RWF', 'GNF', 'UGX', 'JPY', 'VND', 'VUV', 'XAF', 'KMF', 'KRW', 'XOF', 'XPF', 'BRL']
                ) ? number_format($price, 2, '.', '') : number_format($price, 2, '.', '') * 100;

                $stripe_session = $this->createStripeSession([
                    'api_key'      => $company_settings['stripe_secret'] ?? '',
                    'currency'     => $company_currancy,
                    'amount'       => $stripe_formatted_price,
                    'product_name' => $plan->plan_name ?? 'Investment Plan Deposit',
                    'description'  => 'Investment System Stripe Payment',
                    'metadata'     => [
                        'investor_id' => $request['investor_id'],
                        'plan_id'     => $plan->id,
                    ],
                    'success_url'  => route('investment-system.payment.stripe.status', [
                        'return_type' => 'success',
                        'userSlug'    => $userSlug,
                        'investor_id' => $request['investor_id'],
                    ]),
                    'cancel_url'   => route('investment-system.payment.stripe.status', [
                        'return_type' => 'cancel',
                        'userSlug'    => $userSlug,
                        'investor_id' => $request['investor_id'],
                    ]),
                ]);

                Session::put('investment_system_stripe_session', $stripe_session);

                return Inertia::render('Stripe/StripePayment', [
                    'stripe_session' => $stripe_session,
                    'stripe_key'     => $company_settings['stripe_key'] ?? ''
                ]);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }

        return redirect()->route('investor.loginform', ['userSlug' => $userSlug]);
    }

    public function investmentSystemGetStripeStatus(Request $request)
    {

        $userSlug       = $request->route('userSlug');
        $user           = User::where('slug', $userSlug)->first();
        $depositData    = Session::get('investment_system_deposit_data');

        try {
            $company_settings = getCompanyAllSetting($user->id);
            $stripe = new StripeClient(!empty($company_settings['stripe_secret']) ? $company_settings['stripe_secret'] : '');
            $receipt_url = "";
            $stripe_session = Session::get('investment_system_stripe_session');
            if ($stripe_session && isset($stripe_session['id'])) {
                $checkoutSession = $stripe->checkout->sessions->retrieve($stripe_session['id'], []);

                if (isset($checkoutSession->payment_intent)) {
                    $paymentIntents = $stripe->paymentIntents->retrieve($checkoutSession->payment_intent, []);
                    if (!empty($paymentIntents->latest_charge)) {
                        $charge = $stripe->charges->retrieve($paymentIntents->latest_charge, []);
                        $receipt_url = $charge->receipt_url ?? '';
                    }
                }
            }
        } catch (\Exception $exception) {
            $receipt_url = "";
        }
        Session::forget('investment_system_deposit_data');
        Session::forget('investment_system_stripe_session');

        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        if (!$depositData) {
            return redirect()->route('investor.plans', ['userSlug' => $userSlug])->with('error', __('Deposit data not found.'));
        }
        try {
            $status = $request->return_type == 'success';

            if ($status) {
                $plan = InvestmentPlan::where('created_by', $user->id)
                    ->where('plan_status', '0')
                    ->find($depositData['plan_id']);

                if (!$plan) {
                    return redirect()->route('investor.plans', ['userSlug' => $userSlug])
                        ->with('error', __('Investment plan not found.'));
                }
                $existingInvestment = InvestorDeposit::where('investor_id', $depositData['investor_id'])->where('plan_id', $depositData['plan_id'])->first();
                if ($existingInvestment) {
                    $existingInvestment->plan_duration   = $depositData['plan_duration'];
                    $existingInvestment->invested_amount = $depositData['amount'];
                    $existingInvestment->amount          = $depositData['amount'];
                    $existingInvestment->annual_return   = $depositData['annual_return'];
                    $existingInvestment->expected_return = $depositData['expected_return'];
                    $existingInvestment->payment_type    = 'Stripe';
                    $existingInvestment->status          = '1';
                    $existingInvestment->receipt         = $receipt_url ?? null;
                    $existingInvestment->purchase_date   = $depositData['purchase_date'];
                    $existingInvestment->expiry_date     = $depositData['expiry_date'];
                    $existingInvestment->save();
                    $investment = $existingInvestment;
                } else {
                    $investment                  = new InvestorDeposit();
                    $investment->investor_id     = $depositData['investor_id'];
                    $investment->plan_id         = $depositData['plan_id'];
                    $investment->created_by      = $user->id ?? '';
                    $investment->plan_duration   = $depositData['plan_duration'];
                    $investment->invested_amount = $depositData['amount'];
                    $investment->amount          = $depositData['amount'];
                    $investment->annual_return   = $depositData['annual_return'];
                    $investment->expected_return = $depositData['expected_return'];
                    $investment->payment_type    = 'Stripe';
                    $investment->status          = '1';
                    $investment->receipt         = $receipt_url ?? null;
                    $investment->purchase_date   = $depositData['purchase_date'];
                    $investment->expiry_date     = $depositData['expiry_date'];
                    $investment->save();
                }

                // Create investor transaction
                $transaction              = new InvestorTransaction();
                $transaction->plan_id     = $investment->plan_id;
                $transaction->investor_id = $investment->investor_id;
                $transaction->trx_id      = 'TXN-' . strtoupper(uniqid());
                $transaction->amount      = $investment->amount;
                $transaction->type        = 'credit';
                $transaction->detail      = 'Deposit Via - Stripe';
                $transaction->created_by  = $user->id;
                $transaction->save();

                try {
                    InvestorDepositPayment::dispatch($investment, $transaction);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }
                return redirect()->route('investor.transaction', ['userSlug' => $userSlug])->with('success', __('Payment completed and deposit created successfully!'));
            }

            return redirect()->route('investor.deposit', [
                'userSlug' => $userSlug,
                'id'       => $depositData['investor_id'],
                'PlanId'   => $depositData['plan_id'],
            ])->with('error', __('Payment was failed.'));
        } catch (\Exception $exception) {
            return redirect()->route('investor.deposit', [
                'userSlug' => $userSlug,
                'id'       => $depositData['investor_id'],
                'PlanId'   => $depositData['plan_id'],
            ])->with('error', $exception->getMessage());
        }
    }
}

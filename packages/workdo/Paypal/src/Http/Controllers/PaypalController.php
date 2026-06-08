<?php

namespace Workdo\Paypal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Workdo\Paypal\Events\PaypalPaymentStatus;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Workdo\ArtShowcase\Events\CreateArtWorkOrderPayment;
use Workdo\ArtShowcase\Models\ArtShowcaseArtWork;
use Workdo\ArtShowcase\Models\ArtShowcaseArtWorkOrder;
use Workdo\Bookings\Models\BookingAppointment;
use Workdo\Bookings\Models\BookingPackage;
use Workdo\Bookings\Models\BookingCustomer;

use Workdo\Holidayz\Helpers\HolidayzAvailabilityHelper;
use Workdo\Holidayz\Models\HolidayzCart;
use Workdo\Holidayz\Models\HolidayzRoomBooking;
use Workdo\Holidayz\Models\HolidayzRoomBookingItem;
use Workdo\Holidayz\Models\HolidayzCoupon;
use Workdo\Holidayz\Models\HolidayzCouponUsage;

use Workdo\LMS\Models\LMSCart;
use Workdo\LMS\Models\LMSOrder;
use Workdo\LMS\Models\LMSOrderItem;
use Workdo\LMS\Models\LMSCoupon;
use Workdo\BeautySpaManagement\Models\BeautyBooking;
use Workdo\BeautySpaManagement\Events\BeautyBookingPayments;
use Workdo\BeautySpaManagement\Models\BeautyService;
use Workdo\BeautySpaManagement\Models\BeautyBookingReceipt;
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
use Workdo\EventsManagement\Models\Event;
use Workdo\EventsManagement\Models\EventBooking;
use Workdo\EventsManagement\Models\EventBookingPayment;
use Workdo\Facilities\Services\FacilitiesBookingService;
use Workdo\Holidayz\Events\HolidayzBookingPayments;
use Workdo\InfluencerMarketing\Models\InfluencerMarketingDeposit;
use Workdo\InfluencerMarketing\Events\InfluencerMarketingPayment;
use Workdo\LaundryManagement\Events\LaundryBookingPayments;
use Workdo\LaundryManagement\Models\LaundryRequest;
use Workdo\LMS\Events\LMSOrderPayments;
use Workdo\MovieShowBookingSystem\Events\MovieBookingPayments;
use Workdo\MovieShowBookingSystem\Models\MovieBooking;
use Workdo\ParkingManagement\Events\ParkingBookingPayments;
use Workdo\Paypal\Events\FacilityBookingPaymentPaypal;
use Workdo\VehicleBookingManagement\Events\VehicleBookingPayments;
use Workdo\VehicleBookingManagement\Models\VehicleBooking;
use Workdo\NGOManagment\Events\CreateNgoDonation;
use Workdo\NGOManagment\Models\NgoCampaign;
use Workdo\NGOManagment\Models\NgoDonation;
use Workdo\NGOManagment\Models\NgoDonor;
use Workdo\SportsClubAndAcademyManagement\Events\SportsClubBookingPayments;
use Workdo\SportsClubAndAcademyManagement\Events\SportsClubPlanPayments;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubAndGroundOrder;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubAssignedMembership;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubBookingFacility;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubFacility;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubMember;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubMembershipPlan;
use Workdo\SportsClubAndAcademyManagement\Models\SportsClubMembershipPlanPayment;
use Workdo\WaterParkManagement\Events\WaterParkBookingPaymentPaypal;
use Workdo\WaterParkManagement\Models\WaterParkBooking;

use Workdo\TVStudio\Services\TVStudioCheckoutService;
use Workdo\TattooStudioManagement\Models\TattooAppointment;
use Workdo\TattooStudioManagement\Events\TattooAppointmentPaymentPaypal;

use Workdo\PhotoStudioManagement\Models\PhotoStudioService;
use Workdo\PhotoStudioManagement\Models\PhotoStudioAppointment;
use Workdo\PhotoStudioManagement\Models\PhotoStudioAppointmentPayment;
use Workdo\PhotoStudioManagement\Events\PhotoStudioAppointmentPayments;
use Workdo\YogaClasses\Models\YogaClassesCart;
use Workdo\YogaClasses\Models\YogaClassesOrder;
use Workdo\YogaClasses\Models\YogaClassesPurchasedCourse;
use Workdo\YogaClasses\Events\YogaClassesOrderPayments;

use Workdo\Ebook\Models\EbookBookOrder;
use Workdo\Ebook\Events\EbookPayment;
use Workdo\HairAndCareStudio\Events\HairCareStudioOrderPayments;
use Workdo\HairAndCareStudio\Models\HairCareAppointment;
use Workdo\HairAndCareStudio\Models\HairCarePayment;

use Workdo\PetCare\Models\PetCareGroomingPackage;
use Workdo\PetCare\Models\PetCareMembership;
use Workdo\PetCare\Events\PetCareMembershipPayments;
use Workdo\PetCare\Models\PetCareService;
use Workdo\PetCare\Models\PetCareBooking;
use Workdo\PetCare\Events\PetCareBookingPayment;

use Workdo\BoutiqueAndDesignerStudio\Models\BoutiqueBooking;
use Workdo\BoutiqueAndDesignerStudio\Events\BoutiqueBookingPaymentPaypal;
use Workdo\LaundryManagement\Models\LaundryInvoice;
use Workdo\LaundryManagement\Models\LaundryPayment;

use Workdo\InvestmentSystem\Models\InvestorDeposit;
use Workdo\InvestmentSystem\Models\InvestmentPlan;
use Workdo\InvestmentSystem\Models\InvestorTransaction;
use Workdo\InvestmentSystem\Events\InvestorDepositPayment;

class PaypalController extends Controller
{
    /**
     * Create PayPal order with reusable parameters
     */
    private function createPaypalOrder($provider, $routeParams, $currency, $price, $routeName)
    {
        return $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route($routeName, $routeParams),
                "cancel_url" => route($routeName, $routeParams),
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => $currency,
                        "value" => $price,
                    ]
                ]
            ]
        ]);
    }
    public function planPayWithPaypal(Request $request)
    {
        try {
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
                'user_counter' => $user_counter ?: 0,
                'storage_limit' => $storage_limit ?: 0,
            ];
            if ($admin_settings['paypal_mode'] == 'live') {
                config(
                    [
                        'paypal.live.client_id' => isset($admin_settings['paypal_client_id']) ? $admin_settings['paypal_client_id'] : '',
                        'paypal.live.client_secret' => isset($admin_settings['paypal_secret_key']) ? $admin_settings['paypal_secret_key'] : '',
                        'paypal.mode' => isset($admin_settings['paypal_mode']) ? $admin_settings['paypal_mode'] : '',
                    ]
                );
            } else {
                config(
                    [
                        'paypal.sandbox.client_id' => isset($admin_settings['paypal_client_id']) ? $admin_settings['paypal_client_id'] : '',
                        'paypal.sandbox.client_secret' => isset($admin_settings['paypal_secret_key']) ? $admin_settings['paypal_secret_key'] : '',
                        'paypal.mode' => isset($admin_settings['paypal_mode']) ? $admin_settings['paypal_mode'] : '',
                    ]
                );
            }
            $provider = app(PayPalClient::class);
            $provider->setApiCredentials(config('paypal'));

            if ($plan) {
                $plan->discounted_price = false;
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
                $provider->getAccessToken();
                $orderID = strtoupper(substr(uniqid(), -12));

                $routeParams = [
                    $plan->id,
                    'amount' => $price,
                    'user_module' => $user_module,
                    'counter' => $counter,
                    'duration' => $duration,
                    'coupon_code' => $request->coupon_code,
                    'order_id' => $orderID,
                ];
                $routeName = 'payment.paypal.status';
                $response = $this->createPaypalOrder($provider, $routeParams, $admin_currancy, $price, $routeName);

                if (isset($response['id']) && $response['id'] != null) {
                    // redirect to approve href
                    foreach ($response['links'] as $links) {
                        if ($links['rel'] == 'approve') {

                            $order = new Order();
                            $order->order_id = $orderID;
                            $order->name = $user->name ?? '';
                            $order->email = $user->email ?? '';
                            $order->plan_name = !empty($plan->name) ? $plan->name : 'Basic Package';
                            $order->plan_id = $plan->id;
                            $order->price = !empty($price) ? $price : 0;
                            $order->currency = $admin_currancy;
                            $order->txn_id = '';
                            $order->payment_type = 'Paypal';
                            $order->payment_status = 'pending';
                            $order->created_by = $user->id;
                            $order->save();

                            return redirect()->away($links['href']);
                        }
                    }
                    return redirect()
                        ->route('plans.index', Crypt::encrypt($plan->id))
                        ->with('error', __('Something went wrong. Please try again.'));
                }
                return redirect()
                    ->route('plans.index', Crypt::encrypt($plan->id))
                    ->with('error', $response['error']['details'][0]['description'] ?? __('Something went wrong. Please try again.'));
            }
            return redirect()->route('plans.index')->with('error', __('The plan has been deleted.'));
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', $e->getMessage());
        }
    }

    public function planGetPaypalStatus(Request $request, $plan_id)
    {
        try {

            $user = Auth::user();
            $plan = Plan::find($plan_id);
            $order = Order::where('order_id', $request->order_id)->first();

            if ($plan && $order) {
                $admin_settings = getAdminAllSetting();
                if ($admin_settings['paypal_mode'] == 'live') {
                    config(
                        [
                            'paypal.live.client_id' => isset($admin_settings['paypal_client_id']) ? $admin_settings['paypal_client_id'] : '',
                            'paypal.live.client_secret' => isset($admin_settings['paypal_secret_key']) ? $admin_settings['paypal_secret_key'] : '',
                            'paypal.mode' => isset($admin_settings['paypal_mode']) ? $admin_settings['paypal_mode'] : '',
                        ]
                    );
                } else {
                    config(
                        [
                            'paypal.sandbox.client_id' => isset($admin_settings['paypal_client_id']) ? $admin_settings['paypal_client_id'] : '',
                            'paypal.sandbox.client_secret' => isset($admin_settings['paypal_secret_key']) ? $admin_settings['paypal_secret_key'] : '',
                            'paypal.mode' => isset($admin_settings['paypal_mode']) ? $admin_settings['paypal_mode'] : '',
                        ]
                    );
                }
                $provider = app(PayPalClient::class);
                $provider->setApiCredentials(config('paypal'));
                $provider->getAccessToken();
                $response = $provider->capturePaymentOrder($request['token']);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $counter = [
                        'user_counter' => $request->counter['user_counter'] ?: 0,
                        'storage_limit' => $request->counter['storage_limit'] ?: 0,
                    ];

                    $assignPlan = assignPlan($plan->id, $request->duration, $request->user_module, $counter, $user->id);
                    if ($assignPlan['is_success']) {
                        if ($request->coupon_code) {
                            $coupon = Coupon::where('code', $request->coupon_code)->first();
                            if ($coupon) {
                                recordCouponUsage($coupon->id, $user->id, $request->order_id);
                            }
                        }

                        $type = 'Subscription';
                        try {
                            PaypalPaymentStatus::dispatch($plan, $type, $order);
                        } catch (\Exception $e) {
                        }

                        $order->payment_status = 'succeeded';
                        $order->save();

                        return redirect()->route('plans.index')->with('success', __('Plan activated Successfully.'));
                    }
                }
                $order->payment_status = 'failed';
                $order->save();
            }
            return redirect()->route('plans.index')->with('error', $assignPlan['error'] ?? __('Payment failed.'));
        } catch (\Exception $e) {
            return redirect()->route('plans.index')->with('error', $e->getMessage() ?? __('Transaction has been failed.'));
        }
    }

    public function bookingPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
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
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Package not found.'));
        }

        $company_settings = getCompanyAllSetting($package->created_by);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = $package->price ?? 0;
        if ($price <= 0) {
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $request->route('userSlug')
        ];
        $routeName = 'booking.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            // Store PayPal order ID in session
            Session::put('paypal_order_id', $response['id']);

            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', 'Something went wrong. OR Unknown error occurred');
        } else {
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function bookingGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('booking_data');
        $userSlug = $request->route('userSlug');

        if (!$bookingData) {
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $package = BookingPackage::find($bookingData['selectedPackageItem']);
        $company_settings = getCompanyAllSetting($package->created_by ?? 1);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('booking_data');
        Session::forget('booking_user_slug');
        Session::forget('paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $appointment->payment = 'Paypal';
                    $appointment->payment_status = 'paid';
                    $appointment->payment_receipt = null;
                    $appointment->online_payment_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $appointment->status = 'confirmed';
                    $appointment->created_by = $userId;
                    $appointment->creator_id = $userId;
                    $appointment->save();

                    try {
                        BookingAppointmentPayments::dispatch($appointment);
                    } catch (\Throwable $th) {
                        return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('success', __('Payment completed and appointment created successfully!'));
                } else {
                    return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('booking.home', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }


    public function beautySpaPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

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
        Session::put('beauty_booking_user_slug', $userSlug);

        $service = BeautyService::where('id', $request->service)
            ->where('created_by', $userId)
            ->first();

        if (!$service) {
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Service not found.'));
        }

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        // Check for active offers
        $offers = BeautyServiceOffer::where('beauty_service_id', $service->id)
            ->where('start_date', '<=', $request->date)
            ->where('end_date', '>=', $request->date)
            ->where('created_by', $userId)
            ->get();

        $totalOfferPrice = $offers->isNotEmpty() ? $offers->sum('offer_price') : $service->price;
        $price = $totalOfferPrice * $request->person;

        if ($price <= 0) {
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'beauty-spa.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('beauty_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        } else {
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function beautySpaGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('beauty_booking_data');
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

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();
        $response = $provider->capturePaymentOrder($request['token']);
        try {
            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                // Check for active offers to get correct price
                $offers = BeautyServiceOffer::where('beauty_service_id', $service->id)
                    ->where('start_date', '<=', $bookingData['date'])
                    ->where('end_date', '>=', $bookingData['date'])
                    ->where('created_by', $userId)
                    ->get();

                $price = $offers->isNotEmpty() ? $offers->sum('offer_price') : $service->price;
                $servicePrice = $price * $bookingData['person'];

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
                $booking->payment_option = 'Paypal';
                $booking->payment_status = 'paid';
                $booking->stage_id = 0;
                $booking->creator_id = null;
                $booking->created_by = $userId;
                $booking->save();

                $beautyreceipt = new BeautyBookingReceipt();
                $beautyreceipt->beauty_booking_id = $booking->id;
                $beautyreceipt->name = $booking->name;
                $beautyreceipt->service = $booking->service;
                $beautyreceipt->number = $booking->phone_number;
                $beautyreceipt->gender = $booking->gender;
                $beautyreceipt->start_time = $booking->start_time;
                $beautyreceipt->end_time = $booking->end_time;
                $beautyreceipt->price = $booking->price;
                $beautyreceipt->payment_type = 'Paypal';
                $beautyreceipt->created_by = $booking->created_by;
                $beautyreceipt->save();

                try {
                    BeautyBookingPayments::dispatch($booking);
                } catch (\Throwable $th) {
                    return back()->with('error', $th->getMessage());
                }

                return redirect()->route('beauty-spa.booking-success', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));

                Session::forget('beauty_booking_data');
                Session::forget('beauty_booking_user_slug');
                Session::forget('beauty_paypal_order_id');

                return redirect()->route('beauty-spa.booking-success', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                    ->with('success', __('Payment completed and booking confirmed successfully!'));
            } else {
                return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('beauty-spa.booking', ['userSlug' => $userSlug])->with('error', __('Transaction has been failed.'));
        }
    }

    public function lmsPayWithPaypal(Request $request)
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

        Session::put('lms_order_data', [
            'payment_method' => $request->payment_method,
            'payment_note' => $request->payment_note,
            'original_total' => $originalTotal,
            'subtotal' => $subtotal,
            'course_discount' => $courseDiscount,
            'coupon_discount' => $couponDiscount,
            'total' => $total,
            'applied_coupon' => $appliedCoupon
        ]);
        Session::put('lms_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'lms.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $total, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('lms_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('lms.frontend.checkout', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        } else {
            return redirect()->route('lms.frontend.checkout', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function lmsGetPaypalStatus(Request $request)
    {
        $orderData = Session::get('lms_order_data');
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

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('lms_order_data');
        Session::forget('lms_user_slug');
        Session::forget('lms_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $order->payment_method = 'Paypal';
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
                    $order->notes = $orderData['payment_note'];
                    $order->order_date = now();
                    $order->payment_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
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
                }
            }
            return redirect()->route('lms.frontend.checkout', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled or failed.'));
        } catch (\Exception $exception) {
            return redirect()->route('lms.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function parkingPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

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
        Session::put('parking_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->total_amount);
        if ($price <= 0) {
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'parking.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('parking_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        } else {
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function parkingGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('parking_booking_data');
        $userSlug = Session::get('parking_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('parking_booking_data');
        Session::forget('parking_booking_user_slug');
        Session::forget('parking_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $booking->payment_method = 'Paypal';
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

                    return redirect()->route('parking-management.frontend.booking-success', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                        ->with('success', __('Payment completed and booking confirmed successfully!'));
                }
            }
        } catch (\Exception $exception) {
            return redirect()->route('parking-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Transaction has been failed.'));
        }
    }

    public function laundryPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

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
        Session::put('laundry_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->total ?? 0);
        if ($price <= 0) {
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'laundry.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('laundry_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        } else {
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function laundryGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('laundry_booking_data');
        $userSlug = Session::get('laundry_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('laundry_booking_data');
        Session::forget('laundry_booking_user_slug');
        Session::forget('laundry_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $booking->payment_method = 'Paypal';
                    $booking->payment_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
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
                }
            }
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled or failed.'));
        } catch (\Exception $exception) {
            return redirect()->route('laundry-management.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Transaction has been failed.'));
        }
    }

    public function eventsPayWithPaypal(Request $request)
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

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'events-management.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('events_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong, Please try again,'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function eventsGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('events_booking_data');
        $userSlug = Session::get('events_user_slug');
        $paypalOrderId = Session::get('events_paypal_order_id');

        if (!$bookingData) {
            return redirect()->route('events-management.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $event = Event::where('id', $bookingData['event_id'])
            ->where('created_by', $user->id)
            ->first();

        $company_settings = getCompanyAllSetting($user->id);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('events_booking_data');
        Session::forget('events_user_slug');
        Session::forget('events_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $eventBookingPayment->payment_type = 'PayPal';
                    $eventBookingPayment->description = 'Payment via PayPal';
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
                }
            }
            return redirect()->route('events-management.frontend.payment', ['userSlug' => $userSlug, 'id' => $bookingData['event_id']])->with('error', __('Payment was cancelled or failed.'));
        } catch (\Exception $exception) {
            return redirect()->route('events-management.frontend.payment', ['userSlug' => $userSlug, 'id' => $bookingData['event_id']])
                ->with('error', $exception->getMessage());
        }
    }

    // Room Booking PayPal Payment
    public function holidayzPayWithPaypal(Request $request)
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
            'payment_method' => 'Paypal',
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

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'holidayz.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $total, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('holidayz_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('hotel.frontend.checkout', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        } else {
            return redirect()->route('hotel.frontend.checkout', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
        }
    }

    public function holidayzGetPaypalStatus(Request $request)
    {
        $orderData = Session::get('holidayz_order_data');
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

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('holidayz_order_data');
        Session::forget('holidayz_user_slug');
        Session::forget('holidayz_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $booking->payment_method = 'Paypal';
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
                }
            }
            return redirect()->route('hotel.frontend.checkout', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled or failed.'));
        } catch (\Exception $exception) {
            return redirect()->route('hotel.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function facilitiesPaymentWithPaypal(Request $request)
    {
        try {
            $userSlug = $request->route('userSlug');
            $user = User::where('slug', $userSlug)->first();
            if (!$user) {
                return redirect()->back()->with('error', __('User not found.'));
            }

            $company_settings = getCompanyAllSetting($user->id);
            $currency = $company_settings['defult_currancy'] ?? 'USD';

            $bookingData = FacilitiesBookingService::prepareBookingData($request, $user->id);
            if (!$bookingData) {
                return redirect()->back()->with('error', __('Invalid booking data.'));
            }

            $totalAmount = $bookingData['total_amount'];
            if ($totalAmount <= 0) {
                return redirect()->back()->with('error', __('Invalid booking amount.'));
            }

            $orderID = 'FB-' . strtoupper(substr(uniqid(), -8));

            if ($company_settings['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $routeParams = [
                'return_type' => 'success',
                'userSlug' => $userSlug,
                'order_id' => $orderID
            ];
            $routeName = 'facilities.payment.paypal.status';
            $response = $this->createPaypalOrder($provider, $routeParams, $currency, $totalAmount, $routeName);

            if (isset($response['id']) && $response['id'] != null) {
                Session::put('facilities_paypal_order_id', $response['id']);
                Session::put('facility_booking_' . $orderID, $bookingData);

                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        return redirect()->away($links['href']);
                    }
                }
                return redirect()->back()->with('error', __('Something went wrong, Please try again,'));
            } else {
                return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong, Please try again,'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Payment processing failed. Please try again.'));
        }
    }

    public function facilitiesGetPaypalStatus(Request $request)
    {
        try {
            $userSlug = $request->route('userSlug');
            $orderID = $request->get('order_id');
            $return_type = $request->get('return_type');

            if ($return_type === 'cancel') {
                return redirect()->route('facilities.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }

            $user = User::where('slug', $userSlug)->first();
            if (!$user) {
                return redirect()->back()->with('error', __('User not found.'));
            }

            $company_settings = getCompanyAllSetting($user->id);

            if ($company_settings['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $bookingData = Session::get('facility_booking_' . $orderID);

                    if ($bookingData) {
                        $booking = FacilitiesBookingService::createBooking($bookingData, $user->id, 'Paypal');

                        // Create payment entry
                        FacilitiesBookingService::createPaymentEntry($booking, $user->id, [
                            'method' => 'Paypal',
                            'transaction_id' => $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                            'currency' => $company_settings['defult_currancy'] ?? 'USD',
                        ]);

                        try {
                            FacilityBookingPaymentPaypal::dispatch($booking);
                        } catch (\Throwable $th) {
                            return back()->with('error', $th->getMessage());
                        }

                        Session::forget('facility_booking_' . $orderID);
                        Session::forget('facilities_paypal_order_id');

                        return redirect()->route('facilities.frontend.booking-success', [
                            'userSlug' => $userSlug,
                            'booking_number' => $booking->booking_number
                        ])->with('success', __('Payment successful! Booking confirmed: ') . $booking->booking_number);
                    }
                }
            }
            return redirect()->route('facilities.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled or failed.'));
        } catch (\Exception $e) {
            return redirect()->route('facilities.frontend.booking', ['userSlug' => $request->route('userSlug')])->with('error', __('Payment verification failed.'));
        }
    }

    // Vehicle Booking
    public function vehicleBookingPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $bookingData = [
            'email' => $request->email,
            'selected_seats' => $request->selectedSeats,
            'passengers' => $request->passengers,
            'route_id' => $request->route_id,
            'vehicle_id' => $request->vehicle_id,
            'booking_date' => $request->booking_date,
            'total_amount' => $request->total_amount,
            'special_requests' => $request->special_requests,
            'payment_method' => 'Paypal'
        ];

        Session::put('vehicle_booking_data', $bookingData);
        Session::put('vehicle_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount ?? 0);
        if ($price <= 0) {
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'vehicle-booking.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('vehicle_booking_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', 'Something went wrong.');
        } else {
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function vehicleBookingGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('vehicle_booking_data');
        $userSlug = Session::get('vehicle_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('vehicle_booking_data');
        Session::forget('vehicle_booking_user_slug');
        Session::forget('vehicle_booking_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $booking = new VehicleBooking();
                    $booking->booking_number = VehicleBooking::generateBookingNumber($userId);
                    $booking->email = $bookingData['email'];
                    $booking->selected_seats = $bookingData['selected_seats'];
                    $booking->passengers = $bookingData['passengers'];
                    $booking->route_id = $bookingData['route_id'];
                    $booking->vehicle_id = $bookingData['vehicle_id'];
                    $booking->booking_date = $bookingData['booking_date'];
                    $booking->total_amount = $bookingData['total_amount'];
                    $booking->payment_method = 'Paypal';
                    $booking->payment_status = 'paid';
                    $booking->booking_status = 'confirmed';
                    $booking->special_requests = $bookingData['special_requests'];
                    $booking->transaction_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $booking->creator_id = $userId;
                    $booking->created_by = $userId;
                    $booking->save();

                    try {
                        VehicleBookingPayments::dispatch($booking);
                    } catch (\Throwable $th) {
                        return back()->with('error', $th->getMessage());
                    }

                    return redirect()->route('vehicle-booking.frontend.success', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                        ->with('success', __('Payment completed and booking confirmed successfully!'));
                } else {
                    return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('vehicle-booking.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Transaction has been failed.'));
        }
    }

    public function movieBookingPayWithPaypal(Request $request)
    {
        $bookingData = session('booking_data');
        if (!$bookingData) {
            return redirect()->back()->with('error', __('omething went wrong, Please try again,'));
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
        $userId   = $user ? $user->id : null;

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';
        $price            = floatval($request->amount ?? 0);

        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'               => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'                  => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug'    => $userSlug
        ];
        $routeName = 'movie-booking.payment.paypal.status';
        $response  = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('movie_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong.'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
        }
    }

    public function movieBookingGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('movie_booking_data');
        $userSlug    = Session::get('movie_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('movie-booking.home', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user   = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        Session::forget('movie_booking_paypal_session');
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
                $booking->payment_method = 'Paypal';
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


    public function ngoDonationPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $donationData = [
            'amount' => $request->amount,
            'campaign_id' => $request->campaign_id,
            'donor_name' => $request->donor_name,
            'donor_email' => $request->donor_email,
            'donor_message' => $request->donor_message,
            'payment_method' => 'Paypal'
        ];

        Session::put('ngo_donation_data', $donationData);
        Session::put('ngo_donation_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid donation amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'ngo.donation.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('ngo_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', 'Something went wrong.');
        } else {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function ngoDonationGetPaypalStatus(Request $request)
    {
        $donationData = Session::get('ngo_donation_data');
        $userSlug = Session::get('ngo_donation_user_slug');

        if (!$donationData) {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])->with('error', __('User not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('ngo_donation_data');
        Session::forget('ngo_donation_user_slug');
        Session::forget('ngo_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $donation->payment_method = 'Paypal';
                    $donation->status = 'paid';
                    $donation->transaction_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
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
                        ->with('error', __('Donation failed.'));
                }
            } else {
                return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])
                    ->with('error', __('Donation was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('ngo.frontend.index', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }

    public function coworkingSpacePayWithPaypal(Request $request)
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
                'payment_method' => 'Paypal',
                'type' => 'booking'
            ];

            Session::put('coworking_booking_data', $bookingData);
            Session::put('coworking_booking_user_slug', $request->route('userSlug'));

            $userSlug = $request->route('userSlug');
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : null;

            $company_settings = getCompanyAllSetting($userId);
            $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

            $price = floatval($request->totalAmount);
            if ($price <= 0) {
                return redirect()->back()->with('error', __('Invalid payment amount.'));
            }

            // Configure PayPal
            if ($company_settings['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $routeParams = [
                'return_type' => 'success',
                'userSlug' => $userSlug
            ];
            $routeName = 'coworking-space.payment.paypal.status';
            $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

            if (isset($response['id']) && $response['id'] != null) {
                Session::put('coworking_paypal_order_id', $response['id']);

                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        return redirect()->away($links['href']);
                    }
                }
                return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', __('Something went wrong.'));
            } else {
                return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong.'));
            }
        } else {
            $bookingData = [
                'member_name' => $request->member_name,
                'email' => $request->email,
                'phone_no' => $request->phone_no,
                'plan_id' => $request->plan_id,
                'payment_method' => 'Paypal',
                'type' => 'membership'
            ];

            if (!$bookingData) {
                return redirect()->back()->with('error', __('omething went wrong, Please try again,'));
            }

            $userSlug = $request->route('userSlug');
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : null;

            Session::put('coworking_booking_data', $bookingData);
            Session::put('coworking_booking_user_slug', $userSlug);

            $plan = CoworkingMembershipPlan::find($request->plan_id);
            if (!$plan) {
                return redirect()->back()->with('error', __('Plan not found.'));
            }

            $company_settings = getCompanyAllSetting($userId);
            $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';
            $price            = floatval($plan->plan_price ?? 0);

            if ($price <= 0) {
                return redirect()->back()->with('error', __('Invalid payment amount.'));
            }

            // Configure PayPal
            if ($company_settings['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $routeParams = [
                'return_type' => 'success',
                'userSlug' => $userSlug
            ];

            $routeName = 'coworking-space.payment.paypal.status';
            $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

            if (isset($response['id']) && $response['id'] != null) {
                Session::put('coworking_paypal_order_id', $response['id']);

                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        return redirect()->away($links['href']);
                    }
                }
                return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', __('Something went wrong.'));
            } else {
                return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong.'));
            }
        }
    }

    public function coworkingSpaceGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('coworking_booking_data');
        $userSlug = Session::get('coworking_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $paymentType = $bookingData['type'] ?? 'membership';

        if ($paymentType === 'booking') {
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : null;
            $company_settings = getCompanyAllSetting($userId);

            // Configure PayPal
            if ($company_settings['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            Session::forget('coworking_booking_data');
            Session::forget('coworking_booking_user_slug');
            Session::forget('coworking_paypal_order_id');

            try {
                if ($request->return_type == 'success' && $request->token) {
                    $response = $provider->capturePaymentOrder($request->token);

                    if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                        $booking->payment_method = 'Paypal';
                        $booking->special_requests = $bookingData['specialRequests'];
                        $booking->creator_id = $userId;
                        $booking->created_by = $userId;
                        $booking->save();

                        try {
                            CoworkingBookingPayments::dispatch($booking);
                        } catch (\Throwable $th) {
                            return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                        }

                        return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])
                            ->with('success', __('Payment completed and booking confirmed successfully! Booking #:number', ['number' => $booking->booking_number]));
                    } else {
                        return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                    }
                } else {
                    return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
                }
            } catch (\Exception $exception) {
                return redirect()->route('coworking-space.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
            }
        } else {
            $user = User::where('slug', $userSlug)->first();
            $userId = $user ? $user->id : null;
            $company_settings = getCompanyAllSetting($userId);

            // Configure PayPal
            if ($company_settings['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                    'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                    'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            Session::forget('coworking_booking_data');
            Session::forget('coworking_booking_user_slug');
            Session::forget('coworking_paypal_order_id');

            try {
                if ($request->return_type == 'success' && $request->token) {
                    $response = $provider->capturePaymentOrder($request->token);

                    if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                        // Get plan details
                        $plan = CoworkingMembershipPlan::find($bookingData['plan_id']);
                        if (!$plan) {
                            return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])
                                ->with('error', __('Plan not found.'));
                        }

                        $membership = new CoworkingMembership();
                        $membership->member_name = $bookingData['member_name'];
                        $membership->email = $bookingData['email'];
                        $membership->phone_no = $bookingData['phone_no'];
                        $membership->membership_plan_id = $bookingData['plan_id'];
                        $membership->duration = $plan->duration;
                        $membership->price = $plan->plan_price;
                        $membershipController = new CoworkingMembershipController();
                        $membership->plan_expiry_date = $membershipController->calculateExpiryDate($plan->duration);
                        $membership->plan_status = 'Active';
                        $membership->payment_method = 'Paypal';
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
                        return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                    }
                } else {
                    return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
                }
            } catch (\Exception $exception) {
                return redirect()->route('coworking-space.purchase', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
            }
        }
    }

    public function sportsClubPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        // Store booking data in session with all required fields
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
            'total_amount' => $request->total_amount,
        ];

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->total_amount ?? 0);
        if ($price <= 0) {
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        Session::put('sports_club_booking_data', $bookingData);
        Session::put('sports_club_booking_user_slug', $userSlug);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'sports-club.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('sports_club_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', 'Something went wrong.');
        } else {
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function sportsClubGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('sports_club_booking_data');
        $userSlug = Session::get('sports_club_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('sports_club_booking_data');
        Session::forget('sports_club_booking_user_slug');
        Session::forget('sports_club_paypal_order_id');
        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $booking = new SportsClubAndGroundOrder();
                    $booking->sports_club_id = $bookingData['ground_id'];
                    $booking->name = $bookingData['name'];
                    $booking->email = $bookingData['email'];
                    $booking->mobile_no = $bookingData['mobile_number'];
                    $booking->booked_by = $bookingData['booked_by'];
                    $booking->date = $bookingData['booking_date'];
                    $booking->start_time = $bookingData['start_time'];
                    $booking->end_time = $bookingData['end_time'];
                    $booking->start_date = $bookingData['start_date'];
                    $booking->end_date = $bookingData['end_date'];
                    $booking->requirements_desc = $bookingData['special_requirements'];
                    $booking->purpose = $bookingData['purpose'];
                    $booking->total_amount = $bookingData['total_amount'];
                    $booking->payment_type = 'Paypal';
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
                        return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    $encryptedBookingId = encrypt($booking->id);
                    $redirectUrl = route('sports-academy.booking', ['userSlug' => $userSlug]) . '?step=4&booking_id=' . $encryptedBookingId;
                    return redirect($redirectUrl)->with('success', __('Payment completed and booking confirmed successfully!'));
                } else {
                    return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('sports-academy.booking', ['userSlug' => $userSlug])->with('error', __('Transaction has been failed.'));
        }
    }

    public function sportsClubPlanPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $planPaymentData = [
            'user_email' => $request->user_email,
            'plan_id' => $request->plan_id,
        ];

        $plan = SportsClubMembershipPlan::find($request->plan_id);
        if (!$plan) {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Plan not found.'));
        }

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($plan->price ?? 0);
        if ($price <= 0) {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Invalid payment amount.'));
        }

        Session::put('sports_club_plan_payment_data', $planPaymentData);
        Session::put('sports_club_plan_user_slug', $userSlug);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'sports-club-plan.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('sports_club_plan_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', 'Something went wrong.');
        } else {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function sportsClubPlanGetPaypalStatus(Request $request)
    {
        $planPaymentData = Session::get('sports_club_plan_payment_data');
        $userSlug = Session::get('sports_club_plan_user_slug');

        if (!$planPaymentData) {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('sports_club_plan_payment_data');
        Session::forget('sports_club_plan_user_slug');
        Session::forget('sports_club_plan_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $plan = SportsClubMembershipPlan::findOrFail($planPaymentData['plan_id']);
                    $member = SportsClubMember::where('email', $planPaymentData['user_email'])
                        ->where('created_by', $userId)
                        ->first();

                    if (!$member) {
                        return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Member not found.'));
                    }

                    // Create membership plan payment record
                    $planPayment = new SportsClubMembershipPlanPayment();
                    $planPayment->member_id = $member->id;
                    $planPayment->membershipplan_id = $plan->id;
                    $planPayment->fee = $plan->price;
                    $planPayment->duration = $plan->duration;
                    $planPayment->date = now()->toDateString();
                    $planPayment->start_date = now()->toDateString();
                    $planPayment->end_date = $plan->calculateEndDate()->toDateString();
                    $planPayment->reference_number = $request->token;
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
                    $assignment->payment_type = 'Paypal';
                    $assignment->creator_id = $userId;
                    $assignment->created_by = $userId;
                    $assignment->save();

                    try {
                        SportsClubPlanPayments::dispatch($request, $assignment);
                    } catch (\Throwable $th) {
                        return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('success', __('Payment completed and plan subscription confirmed successfully!'));
                } else {
                    return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('sports-academy.plans', ['userSlug' => $userSlug])->with('error', __('Transaction has been failed.'));
        }
    }

    public function influencerMarketingPayWithPaypal(Request $request)
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
        ]);
        Session::put('influencer_marketing_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));

            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('influencer-marketing.payment.paypal.status', ['userSlug' => $userSlug, 'return_type' => 'success']),
                    "cancel_url" => route('influencer-marketing.payment.paypal.status', ['userSlug' => $userSlug, 'return_type' => 'cancel']),
                ],
                "purchase_units" => [[
                    "amount" => [
                        "currency_code" => $company_currancy,
                        "value" => number_format($amount, 2, '.', '')
                    ],
                    "description" => "Influencer Marketing Deposit"
                ]]
            ]);

            if (isset($response['id']) && $response['id'] != null) {
                Session::put('influencer_marketing_paypal_order_id', $response['id']);
                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        return redirect()->away($links['href']);
                    }
                }
            } else {
                return redirect()->back()->with('error', __('Something went wrong.'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function influencerMarketingGetPaypalStatus(Request $request)
    {
        $depositData = Session::get('influencer_marketing_deposit_data');
        $userSlug = Session::get('influencer_marketing_user_slug');

        if (!$depositData) {
            return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                ->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $company_settings = getCompanyAllSetting($user->id);

        Session::forget('influencer_marketing_deposit_data');
        Session::forget('influencer_marketing_user_slug');
        Session::forget('influencer_marketing_paypal_order_id');

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {

                    $deposit = new InfluencerMarketingDeposit();
                    $deposit->brand_id          = $depositData['brand_id'];
                    $deposit->amount            = $depositData['amount'];
                    $deposit->payment_type      = 'Paypal';
                    $deposit->payment_status    = 'paid';
                    $deposit->transaction_id    = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $deposit->created_by        = $user->id;
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
                        ->with('error', __('Transaction has been failed.'));
                }
            } else {
                return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('influencer-marketing.frontend.dashboard', ['userSlug' => $userSlug])
                ->with('error', __('Transaction has been failed.'));
        }
    }
    public function waterParkPayWithPaypal(Request $request)
    {

        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;

        $bookingData = [
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'booking_date' => $request->booking_date,
            'event_id' => $request->event_id,
            'adults' => $request->adults,
            'children' => $request->children,
            'total_amount' => $request->total_amount
        ];

        Session::put('water_park_booking_data', $bookingData);
        Session::put('water_park_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($userId);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        // Validate currency
        if (empty($company_currancy)) {
            return redirect()->back()->with('error', __('Currency not configured.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'water-park.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, (string)number_format($price, 2, '.', ''), $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('water_park_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong.'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
        }
    }

    public function waterParkGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('water_park_booking_data');
        $userSlug = Session::get('water_park_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $userId = $user ? $user->id : null;
        $company_settings = getCompanyAllSetting($userId);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('water_park_booking_data');
        Session::forget('water_park_booking_user_slug');
        Session::forget('water_park_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    // Create water park booking record
                    $booking = new WaterParkBooking();
                    $booking->full_name = $bookingData['full_name'];
                    $booking->email = $bookingData['email'];
                    $booking->phone = $bookingData['phone'];
                    $booking->booking_date = $bookingData['booking_date'];
                    $booking->event_id = $bookingData['event_id'];
                    $booking->adults = $bookingData['adults'];
                    $booking->children = $bookingData['children'];
                    $booking->total_amount = $bookingData['total_amount'];
                    $booking->payment_method = 'Paypal';
                    $booking->payment_status = 'paid';
                    $booking->booking_status = 'confirmed';
                    $booking->transaction_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $booking->creator_id = $userId;
                    $booking->created_by = $userId;
                    $booking->save();

                    try {
                        WaterParkBookingPaymentPaypal::dispatch($booking);
                    } catch (\Throwable $th) {
                        return back()->with('error', $th->getMessage());
                    }

                    return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                        ->with('success', __('Payment completed and booking confirmed successfully!'));
                } else {
                    return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('water-park.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function tvStudioPayWithPaypal(Request $request)
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

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'               => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'                  => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug'    => $userSlug
        ];
        $routeName   = 'tvstudio.payment.paypal.status';
        $total       = number_format($total, 2, '.', '');
        $response    = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $total, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('tvstudio_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong.'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
        }
    }

    public function tvStudioGetPaypalStatus(Request $request)
    {
        $userSlug  = $request->route('userSlug') ?? Session::get('tvstudio_user_slug');
        $orderData = Session::get('tvstudio_order_data');

        if (!$orderData) {
            return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user     = User::where('slug', $userSlug)->first();
        $customer = auth('tvstudio_customer')->user();

        if (!$user || !$customer) {
            return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'               => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'                  => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('tvstudio_order_data');
        Session::forget('tvstudio_user_slug');
        Session::forget('tvstudio_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $payment_intent = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;

                    $order = TVStudioCheckoutService::createOrder(
                        $orderData,
                        $customer->id,
                        $user->id,
                        'Paypal',
                        $payment_intent
                    );

                    return redirect()->route('tvstudio.frontend.order-complete', ['userSlug' => $userSlug]);
                } else {
                    return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('tvstudio.frontend.home', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    // ArtShowcase Paypal Payment
    public function artShowcasePayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        // Get artwork
        $artwork = ArtShowcaseArtWork::where('id', $request->art_work_id)
            ->where('created_by', $user->id)
            ->first();

        if (!$artwork) {
            return redirect()->back()->with('error', __('Artwork not found.'));
        }

        if ($artwork->status !== 'available') {
            return redirect()->back()->with('error', __('This artwork is no longer available for purchase.'));
        }

        // Store purchase data in session
        $purchaseData = [
            'art_work_id' => $request->art_work_id,
            'full_name' => $request->full_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'payment_method' => 'Paypal'
        ];

        Session::put('art_showcase_purchase_data', $purchaseData);
        Session::put('art_showcase_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($artwork->price ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid artwork price.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'art-showcase.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('art_showcase_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', 'Something went wrong.');
        } else {
            return redirect()->back()->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function artShowcaseGetPaypalStatus(Request $request)
    {
        $purchaseData = Session::get('art_showcase_purchase_data');
        $userSlug = Session::get('art_showcase_user_slug');

        if (!$purchaseData) {
            return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])->with('error', __('Something went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])->with('error', __('User not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('art_showcase_purchase_data');
        Session::forget('art_showcase_user_slug');
        Session::forget('art_showcase_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    // Get artwork again to ensure it's still available
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

                    // Create artwork order
                    $order = new ArtShowcaseArtWorkOrder();
                    $order->art_work_id = $artwork->id;
                    $order->customer_full_name = $purchaseData['full_name'];
                    $order->customer_email = $purchaseData['email'];
                    $order->contact_number = $purchaseData['phone'];
                    $order->address = $purchaseData['address'];
                    $order->total_amount = $artwork->price;
                    $order->payment_type = 'Paypal';
                    $order->payment_status = 'paid';
                    $order->creator_id = $user->id;
                    $order->created_by = $user->id;
                    $order->save();

                    // Update artwork status to sold
                    $artwork->status = 'sold';
                    $artwork->save();

                    try {
                        CreateArtWorkOrderPayment::dispatch($request, $order);
                    } catch (\Throwable $th) {
                        return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                    }

                    return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                        ->with('success', __('Payment completed successfully! Your artwork purchase has been confirmed.'));
                } else {
                    return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                        ->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('art-gallery.frontend.artworks', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function tattooStudioPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();

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
        Session::put('tattoo_studio_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'tattoo-studio.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('tattoo_studio_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong.'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
        }
    }

    public function tattooStudioGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('tattoo_studio_booking_data');
        $userSlug = Session::get('tattoo_studio_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $company_settings = getCompanyAllSetting($user->id);

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('tattoo_studio_booking_data');
        Session::forget('tattoo_studio_booking_user_slug');
        Session::forget('tattoo_studio_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $booking->payment_method     = 'Paypal';
                    $booking->payment_status     = 'paid';
                    $booking->appointment_status = 'confirmed';
                    $booking->transaction_id     = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $booking->creator_id         = $user->id;
                    $booking->created_by         = $user->id;
                    $booking->save();

                    try {
                        TattooAppointmentPaymentPaypal::dispatch($booking);
                    } catch (\Throwable $th) {
                        return back()->with('error', $th->getMessage());
                    }

                    return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])
                        ->with('success', __('Payment completed and appointment confirmed successfully!'));
                } else {
                    return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('tattoo-studio.frontend.appointment', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function photoStudioPayWithPaypal(Request $request)
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
        Session::put('photo_studio_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        $price = floatval($request->price);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'photo-studio.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('photo_studio_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong. OR Unknown error occurred'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function photoStudioGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('photo_studio_booking_data');
        $userSlug = Session::get('photo_studio_user_slug');
        $paypalOrderId = Session::get('photo_studio_paypal_order_id');

        if (!$bookingData) {
            return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }
        $company_settings = getCompanyAllSetting($user->id);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('photo_studio_booking_data');
        Session::forget('photo_studio_user_slug');
        Session::forget('photo_studio_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $payment->payment_type       = 'PayPal';
                    $payment->description        = 'Payment via PayPal';
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
                        ->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('photo-studio-management.frontend.appointment', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }


    public function ebookPayWithPaypal(Request $request)
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
                    // Configure PayPal
                    if ($company_settings['paypal_mode'] == 'live') {
                        config([
                            'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                            'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                            'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                        ]);
                    } else {
                        config([
                            'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                            'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                            'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                        ]);
                    }

                    $provider = new PayPalClient;
                    $provider->setApiCredentials(config('paypal'));
                    $provider->getAccessToken();

                    $routeParams = [
                        'return_type' => 'success',
                        'userSlug' => $userSlug,
                        'customerId' => $customer->id
                    ];
                    $routeName = 'ebook.payment.paypal.status';
                    $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $request->total ?? 0, $routeName);

                    if (isset($response['id']) && $response['id'] != null) {
                        foreach ($response['links'] as $links) {
                            if ($links['rel'] == 'approve') {
                                return redirect()->away($links['href']);
                            }
                        }
                        return redirect()->route('ebook.frontend.checkout', ['userSlug' => $userSlug])->with('error', __('Something went wrong.'));
                    } else {
                        return redirect()->route('ebook.frontend.checkout', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong.'));
                    }
                } else {
                    return redirect()->route('ebook.frontend.checkout', ['userSlug' => $userSlug])->with('error', $check['message'] ?? __('Something went wrong.'));
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }
        return redirect()->route('ebook.frontend.login', ['userSlug' => $userSlug]);
    }

    public function ebookGetPaypalStatus(Request $request, $userSlug)
    {
        try {
            $paypalToken = $request->token;
            $user = User::where('slug', $userSlug)->first();
            $status = false;

            if ($paypalToken) {

                $company_settings = getCompanyAllSetting($user->id);

                if ($company_settings['paypal_mode'] == 'live') {
                    config([
                        'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                        'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                        'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                    ]);
                } else {
                    config([
                        'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                        'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                        'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                    ]);
                }

                $provider = new PayPalClient;
                $provider->setApiCredentials(config('paypal'));
                $provider->getAccessToken();
                $response = $provider->capturePaymentOrder($paypalToken);

                $status  = isset($response['status']) && $response['status'] == 'COMPLETED';
            }

            if ($status) {
                $order = EbookBookOrder::MakeOrder(
                    "Paypal",
                    $user,
                    $request->customerId ?? null,
                    $status ?? false,
                    $request->PayerID ?? null
                );

                EbookPayment::dispatch($order);

                return redirect()->route('ebook.frontend.index', ['userSlug' => $userSlug])
                    ->with('success', __('Payment completed successfully'));
            }

            return redirect()->route('ebook.frontend.index', ['userSlug' => $userSlug])
                ->with('error', __('Payment completed faild'));
        } catch (\Exception $exception) {
            return redirect()->route('ebook.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function yogaClassesPayWithPaypal(Request $request)
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

        $companySettings = getCompanyAllSetting($user->id);
        $companyCurrency = !empty($companySettings['defaultCurrency']) ? $companySettings['defaultCurrency'] : 'USD';

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
            'payment_method' => 'Paypal',
            'payment_note' => $request->payment_note,
            'total' => $total,
        ]);
        Session::put('yoga_classes_user_slug', $userSlug);

        if (($companySettings['paypal_mode'] ?? '') == 'live') {
            config([
                'paypal.live.client_id' => $companySettings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $companySettings['paypal_secret_key'] ?? '',
                'paypal.mode' => $companySettings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $companySettings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $companySettings['paypal_secret_key'] ?? '',
                'paypal.mode' => $companySettings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug,
        ];
        $routeName = 'yoga-classes.payment.paypal.status';

        // Fix decimal precision issue - PayPal requires exactly 2 decimal places
        $formattedTotal = number_format($total, 2, '.', '');
        $response = $this->createPaypalOrder($provider, $routeParams, $companyCurrency, $formattedTotal, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('yoga_classes_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])->with('error', __('Something went wrong. OR Unknown error occurred'));
        }

        return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong.'));
    }

    public function yogaClassesGetPaypalStatus(Request $request)
    {
        $orderData = Session::get('yoga_classes_order_data');
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

        if (($companySettings['paypal_mode'] ?? '') == 'live') {
            config([
                'paypal.live.client_id' => $companySettings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $companySettings['paypal_secret_key'] ?? '',
                'paypal.mode' => $companySettings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $companySettings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $companySettings['paypal_secret_key'] ?? '',
                'paypal.mode' => $companySettings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('yoga_classes_order_data');
        Session::forget('yoga_classes_user_slug');
        Session::forget('yoga_classes_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $course_order->payment_method = 'Paypal';
                    $course_order->payment_status = 'paid';
                    $course_order->transaction_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $course_order->receipt = null;
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

                    return redirect()->route('yoga-classes.frontend.order-success', ['userSlug' => $userSlug, 'reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? $userSlug])
                        ->with('success', __('Payment completed successfully! Order #:number', ['number' => $course_order->order_number]));
                }

                return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])
                    ->with('error', __('Payment failed.'));
            }

            return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', __('Payment was cancelled.'));
        } catch (\Exception $exception) {
            return redirect()->route('yoga-classes.frontend.checkout', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }


    public function hairCareStudioPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
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
        Session::put('hair_care_studio_booking_user_slug', $userSlug);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : 'USD';

        $price = floatval($request->total_amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug' => $userSlug
        ];
        $routeName = 'hair-care-studio.payment.paypal.status';
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $price, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('hair_care_studio_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong.'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
        }
    }

    public function hairCareStudioGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('hair_care_studio_booking_data');
        $userSlug = Session::get('hair_care_studio_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Invalid session.'));
        }
        $company_settings = getCompanyAllSetting($user->id);

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('hair_care_studio_booking_data');
        Session::forget('hair_care_studio_booking_user_slug');
        Session::forget('hair_care_studio_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $haircarepayment->transaction_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $haircarepayment->payment_method = 'Paypal';
                    $haircarepayment->payment_status = 'cleared';
                    $haircarepayment->notes = 'Payment via PayPal';
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
                    return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('hair-care-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function petCarePayWithPaypal(Request $request)
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
                        'payment_method' => 'Paypal',
                        'type' => $paymentType,
                        'amount' => $total,
                    ];
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
                        'payment_method' => 'Paypal',
                        'type' => $paymentType,
                        'amount' => $total,
                    ];
                }

                Session::put('pet_care_booking_data', $bookingData);
                Session::put('pet_care_booking_user_slug', $userSlug);

                // Configure PayPal
                if ($company_settings['paypal_mode'] == 'live') {
                    config([
                        'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                        'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                        'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                    ]);
                } else {
                    config([
                        'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                        'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                        'paypal.mode' => $company_settings['paypal_mode'] ?? '',
                    ]);
                }

                $provider = new PayPalClient;
                $provider->setApiCredentials(config('paypal'));
                $provider->getAccessToken();

                $routeParams = [
                    'return_type' => 'success',
                    'userSlug' => $userSlug
                ];
                $routeName = 'pet-care.payment.paypal.status';
                $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $total, $routeName);

                if (isset($response['id']) && $response['id'] != null) {
                    Session::put('pet_care_paypal_order_id', $response['id']);

                    foreach ($response['links'] as $links) {
                        if ($links['rel'] == 'approve') {
                            return redirect()->away($links['href']);
                        }
                    }
                    return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])->with('error', __('Something went wrong.'));
                } else {
                    return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])->with('error', $response['message'] ?? __('Something went wrong.'));
                }
            } catch (\Exception $e) {
                return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])->with('error', $e->getMessage());
            }
        }
        return redirect()->back()->with('error', __('Invalid request.'));
    }

    public function petCareGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('pet_care_booking_data');
        $userSlug = Session::get('pet_care_booking_user_slug');
        $paymentType = $bookingData['type'] ?? 'membership';

        if (!$bookingData) {
            return redirect()->back()->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        // Configure PayPal
        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('pet_care_booking_data');
        Session::forget('pet_care_booking_user_slug');
        Session::forget('pet_care_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    if ($paymentType === 'service') {
                        // Create service booking record
                        $service = PetCareService::find($bookingData['service']);
                        if (!$service) {
                            return redirect()->route('pet-care.frontend.services', ['userSlug' => $userSlug])
                                ->with('error', __('Service not found.'));
                        }

                        $times = explode('-', $bookingData['time_slot']);

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
                        $booking->payment_method = 'Paypal';
                        $booking->payment_status = 'paid';
                        $booking->created_by = $user->id;
                        $booking->creator_id = $user->id;
                        $booking->save();

                        try {
                            PetCareBookingPayment::dispatch($booking);
                        } catch (\Throwable $th) {
                            return redirect()->route('pet-care.frontend.services', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                        }

                        return redirect()->route('pet-care.frontend.services', ['userSlug' => $userSlug])
                            ->with('success', __('Payment completed successfully! Your service booking has been confirmed.'));
                    } else {
                        // Create membership record
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
                        $membership->payment_method = 'Paypal';
                        $membership->payment_status = 'paid';
                        $membership->created_by = $user->id;
                        $membership->creator_id = $user->id;
                        $membership->save();

                        try {
                            PetCareMembershipPayments::dispatch($membership);
                        } catch (\Throwable $th) {
                            return redirect()->route('pet-care.frontend.pricing', ['userSlug' => $userSlug])->with('error', $th->getMessage());
                        }

                        return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])
                            ->with('success', __('Payment completed successfully! Order #:number', ['number' => $membership->membership_id]));
                    }
                } else {
                    return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])
                    ->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('pet-care.frontend.index', ['userSlug' => $userSlug])
                ->with('error', $exception->getMessage());
        }
    }
    public function boutiqueStudioPayWithPaypal(Request $request)
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

        $price = floatval($request->total_amount ?? 0);
        if ($price <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'               => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'                  => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = ['return_type' => 'success', 'userSlug' => $userSlug];
        $response = $this->createPaypalOrder($provider, $routeParams, $company_currancy, number_format($price, 2, '.', ''), 'boutique-studio.payment.paypal.status');

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('boutique_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()->back()->with('error', __('Something went wrong.'));
        } else {
            return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
        }
    }


    public function boutiqueStudioGetPaypalStatus(Request $request)
    {
        $bookingData = Session::get('boutique_booking_data');
        $userSlug    = Session::get('boutique_booking_user_slug');

        if (!$bookingData) {
            return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('omething went wrong, Please try again,'));
        }

        $user = User::where('slug', $userSlug)->first();
        $company_settings = getCompanyAllSetting($user->id);

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'               => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'                  => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('boutique_booking_data');
        Session::forget('boutique_booking_user_slug');
        Session::forget('boutique_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
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
                    $booking->payment_method = 'Paypal';
                    $booking->payment_status = 'paid';
                    $booking->booking_status = 'confirmed';
                    $booking->transaction_id = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    $booking->creator_id     = $user->id;
                    $booking->created_by     = $user->id;
                    $booking->save();

                    try {
                        BoutiqueBookingPaymentPaypal::dispatch($booking);
                    } catch (\Throwable $th) {
                        return back()->with('error', $th->getMessage());
                    }

                    return redirect()->route('boutique-and-designer-studio.frontend.booking-success', ['userSlug' => $userSlug, 'id' => encrypt($booking->id)])
                        ->with('success', __('Payment completed and booking confirmed successfully!'));
                } else {
                    return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment failed.'));
                }
            } else {
                return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', __('Payment was cancelled.'));
            }
        } catch (\Exception $exception) {
            return redirect()->route('boutique-and-designer-studio.frontend.booking', ['userSlug' => $userSlug])->with('error', $exception->getMessage());
        }
    }

    public function investmentSystemPayWithPaypal(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user     = User::where('slug', $userSlug)->first();

        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        $plan = InvestmentPlan::where('created_by', $user->id)
            ->where('plan_status', '0')
            ->find($request->plan_id);

        if (!$plan) {
            return redirect()->route('investor.plans', ['userSlug' => $userSlug])->with('error', __('Investment plan not found.'));
        }

        $amount = (float) $request->amount;
        if ($amount <= 0) {
            return redirect()->route('investor.deposit', [
                'userSlug' => $userSlug,
                'id'       => $request->investor_id,
                'PlanId'   => $request->plan_id,
            ])->with('error', __('Invalid payment amount.'));
        }

        $depositData = [
            'investor_id'     => $request->investor_id,
            'plan_id'         => $request->plan_id,
            'amount'          => $amount,
            'plan_duration'   => $request->plan_duration,
            'annual_return'   => $request->annual_return,
            'expected_return' => $request->expected_return,
            'purchase_date'   => $request->purchase_date,
            'expiry_date'     => $request->expiry_date,
        ];

        Session::put('investment_system_deposit_data', $depositData);

        $company_settings = getCompanyAllSetting($user->id);
        $company_currancy = !empty($company_settings['defaultCurrency']) ? $company_settings['defaultCurrency'] : '';

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'               => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id'     => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode'                  => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $routeParams = [
            'return_type' => 'success',
            'userSlug'    => $userSlug,
        ];
        $routeName = 'investment-system.payment.paypal.status';
        $response  = $this->createPaypalOrder($provider, $routeParams, $company_currancy, $amount, $routeName);

        if (isset($response['id']) && $response['id'] != null) {
            Session::put('investment_system_paypal_order_id', $response['id']);

            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }

            return redirect()->route('investor.deposit', [
                'userSlug' => $userSlug,
                'id'       => $request->investor_id,
                'PlanId'   => $request->plan_id,
            ])->with('error', __('Something went wrong. OR Unknown error occurred'));
        }

        return redirect()->route('investor.deposit', [
            'userSlug' => $userSlug,
            'id'       => $request->investor_id,
            'PlanId'   => $request->plan_id,
        ])->with('error', $response['message'] ?? __('Something went wrong.'));
    }

    public function investmentSystemGetPaypalStatus(Request $request)
    {
        $userSlug = $request->route('userSlug');
        $user = User::where('slug', $userSlug)->first();
        $depositData = Session::get('investment_system_deposit_data');

        if (!$user) {
            return redirect()->back()->with('error', __('User not found.'));
        }

        if (!$depositData) {
            return redirect()->route('investor.plans', ['userSlug' => $userSlug])->with('error', __('Deposit data not found.'));
        }

        $company_settings = getCompanyAllSetting($user->id);

        if ($company_settings['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.live.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => $company_settings['paypal_client_id'] ?? '',
                'paypal.sandbox.client_secret' => $company_settings['paypal_secret_key'] ?? '',
                'paypal.mode' => $company_settings['paypal_mode'] ?? '',
            ]);
        }

        $provider = app(PayPalClient::class);
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        Session::forget('investment_system_deposit_data');
        Session::forget('investment_system_paypal_order_id');

        try {
            if ($request->return_type == 'success' && $request->token) {
                $response = $provider->capturePaymentOrder($request->token);

                if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                    $plan = InvestmentPlan::where('created_by', $user->id)
                        ->where('plan_status', '0')
                        ->find($depositData['plan_id']);

                    if (!$plan) {
                        return redirect()->route('investor.plans', ['userSlug' => $userSlug])
                            ->with('error', __('Investment plan not found.'));
                    }

                    $existingInvestment = InvestorDeposit::where('investor_id', $depositData['investor_id'])
                        ->where('plan_id', $depositData['plan_id'])
                        ->first();

                    if ($existingInvestment) {
                        $existingInvestment->plan_duration   = $depositData['plan_duration'];
                        $existingInvestment->invested_amount = $depositData['amount'];
                        $existingInvestment->amount          = $depositData['amount'];
                        $existingInvestment->annual_return   = $depositData['annual_return'];
                        $existingInvestment->expected_return = $depositData['expected_return'];
                        $existingInvestment->payment_type    = 'Paypal';
                        $existingInvestment->status          = '1';
                        $existingInvestment->receipt         = '';
                        $existingInvestment->purchase_date   = $depositData['purchase_date'];
                        $existingInvestment->expiry_date     = $depositData['expiry_date'];
                        $existingInvestment->save();
                        $investment = $existingInvestment;
                    } else {
                        $investment                  = new InvestorDeposit();
                        $investment->investor_id     = $depositData['investor_id'];
                        $investment->plan_id         = $depositData['plan_id'];
                        $investment->created_by      = $user->id ?? null;
                        $investment->plan_duration   = $depositData['plan_duration'];
                        $investment->invested_amount = $depositData['amount'];
                        $investment->amount          = $depositData['amount'];
                        $investment->annual_return   = $depositData['annual_return'];
                        $investment->expected_return = $depositData['expected_return'];
                        $investment->payment_type    = 'Paypal';
                        $investment->status          = '1';
                        $investment->receipt         = '';
                        $investment->purchase_date   = $depositData['purchase_date'];
                        $investment->expiry_date     = $depositData['expiry_date'];
                        $investment->save();
                    }

                    $transaction              = new InvestorTransaction();
                    $transaction->plan_id     = $investment->plan_id;
                    $transaction->investor_id = $investment->investor_id;
                    $transaction->trx_id      = 'TXN-' . strtoupper(uniqid());
                    $transaction->amount      = $investment->amount;
                    $transaction->type        = 'credit';
                    $transaction->detail      = 'Deposit Via - Paypal';
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
                    'id' => $depositData['investor_id'],
                    'PlanId' => $depositData['plan_id'],
                ])->with('error', __('Payment was failed.'));
            }

            return redirect()->route('investor.deposit', [
                'userSlug' => $userSlug,
                'id' => $depositData['investor_id'],
                'PlanId' => $depositData['plan_id'],
            ])->with('error', __('Payment was cancelled.'));
        } catch (\Exception $exception) {
            return redirect()->route('investor.deposit', [
                'userSlug' => $userSlug,
                'id' => $depositData['investor_id'],
                'PlanId' => $depositData['plan_id'],
            ])->with('error', $exception->getMessage());
        }
    }
}

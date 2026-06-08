<?php

use Illuminate\Support\Facades\Route;
use Workdo\Paypal\Http\Controllers\PaypalController;
use Workdo\Paypal\Http\Controllers\PaypalSettingsController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Paypal'])->group(function () {
    Route::post('/paypal/settings', [PaypalSettingsController::class, 'update'])->name('paypal.settings.update');
});
Route::middleware(['web'])->group(function () {
    Route::prefix('paypal')->group(function () {
        Route::post('/plan/company/payment', [PaypalController::class, 'planPayWithPaypal'])->name('payment.paypal.store')->middleware(['auth']);
        Route::get('/plan/company/status/{plan_id}', [PaypalController::class, 'planGetPaypalStatus'])->name('payment.paypal.status')->middleware(['auth']);

        // Booking payment routes
        Route::post('{userSlug?}/booking/payment', [PaypalController::class, 'bookingPayWithPaypal'])->name('booking.payment.paypal.store');
        Route::get('{userSlug?}/booking/status', [PaypalController::class, 'bookingGetPaypalStatus'])->name('booking.payment.paypal.status');



        // BeautySpa payment routes
        Route::post('{userSlug?}/beauty-spa/payment', [PaypalController::class, 'beautySpaPayWithPaypal'])->name('beauty-spa.payment.paypal.store');
        Route::get('{userSlug?}/beauty-spa/status', [PaypalController::class, 'beautySpaGetPaypalStatus'])->name('beauty-spa.payment.paypal.status');

        // LMS payment routes
        Route::post('{userSlug?}/lms/payment', [PaypalController::class, 'lmsPayWithPaypal'])->name('lms.payment.paypal.store');
        Route::get('{userSlug?}/lms/status', [PaypalController::class, 'lmsGetPaypalStatus'])->name('lms.payment.paypal.status');

        // Parking payment routes
        Route::post('{userSlug}/parking/payment', [PaypalController::class, 'parkingPayWithPaypal'])->name('parking.payment.paypal.store');
        Route::get('{userSlug}/parking/status', [PaypalController::class, 'parkingGetPaypalStatus'])->name('parking.payment.paypal.status');

        // Laundry payment routes
        Route::post('{userSlug?}/laundry/payment', [PaypalController::class, 'laundryPayWithPaypal'])->name('laundry.payment.paypal.store');
        Route::get('{userSlug?}/laundry/status', [PaypalController::class, 'laundryGetPaypalStatus'])->name('laundry.payment.paypal.status');

        // Events payment routes
        Route::post('{userSlug?}/events/payment', [PaypalController::class, 'eventsPayWithPaypal'])->name('events-management.payment.paypal.store');
        Route::get('{userSlug?}/events/status', [PaypalController::class, 'eventsGetPaypalStatus'])->name('events-management.payment.paypal.status');

        // Holidayz payment routes
        Route::post('{userSlug?}/holidayz/payment', [PaypalController::class, 'holidayzPayWithPaypal'])->name('holidayz.payment.paypal.store');
        Route::get('{userSlug?}/holidayz/status', [PaypalController::class, 'holidayzGetPaypalStatus'])->name('holidayz.payment.paypal.status');

        // Facilities payment routes
        Route::post('{userSlug}/facilities/payment', [PaypalController::class, 'facilitiesPaymentWithPaypal'])->name('facilities.payment.paypal.store');
        Route::get('{userSlug}/facilities/status', [PaypalController::class, 'facilitiesGetPaypalStatus'])->name('facilities.payment.paypal.status');

        // Vehicle Booking payment routes
        Route::post('{userSlug?}/vehicle-booking/payment', [PaypalController::class, 'vehicleBookingPayWithPaypal'])->name('vehicle-booking.payment.paypal.store');
        Route::get('{userSlug?}/vehicle-booking/status', [PaypalController::class, 'vehicleBookingGetPaypalStatus'])->name('vehicle-booking.payment.paypal.status');

        // Movie Booking payment routes
        Route::post('{userSlug?}/movie-booking/payment', [PaypalController::class, 'movieBookingPayWithPaypal'])->name('movie-booking.payment.paypal.store');
        Route::get('{userSlug?}/movie-booking/status', [PaypalController::class, 'movieBookingGetPaypalStatus'])->name('movie-booking.payment.paypal.status');

        // NGO donation payment routes
        Route::post('{userSlug?}/ngo/donation/payment', [PaypalController::class, 'ngoDonationPayWithPaypal'])->name('ngo.donation.payment.paypal.store');
        Route::get('{userSlug?}/ngo/donation/status', [PaypalController::class, 'ngoDonationGetPaypalStatus'])->name('ngo.donation.payment.paypal.status');

        // Coworking Space payment routes
        Route::post('{userSlug?}/coworking-space/payment', [PaypalController::class, 'coworkingSpacePayWithPaypal'])->name('coworking-space.payment.paypal.store');
        Route::get('{userSlug?}/coworking-space/status', [PaypalController::class, 'coworkingSpaceGetPaypalStatus'])->name('coworking-space.payment.paypal.status');

        // Sports Club payment routes
        Route::post('{userSlug?}/sports-club/payment', [PaypalController::class, 'sportsClubPayWithPaypal'])->name('sports-club.payment.paypal.store');
        Route::get('{userSlug?}/sports-club/status', [PaypalController::class, 'sportsClubGetPaypalStatus'])->name('sports-club.payment.paypal.status');

        // Sports Club Plan payment routes
        Route::post('{userSlug?}/sports-club-plan/payment', [PaypalController::class, 'sportsClubPlanPayWithPaypal'])->name('sports-club-plan.payment.paypal.store');
        Route::get('{userSlug?}/sports-club-plan/status', [PaypalController::class, 'sportsClubPlanGetPaypalStatus'])->name('sports-club-plan.payment.paypal.status');

        // InfluencerMarketing payment routes
        Route::post('{userSlug?}/influencer-marketing/payment', [PaypalController::class, 'influencerMarketingPayWithPaypal'])->name('influencer-marketing.payment.paypal.store');
        Route::get('{userSlug?}/influencer-marketing/status', [PaypalController::class, 'influencerMarketingGetPaypalStatus'])->name('influencer-marketing.payment.paypal.status');

        // Water Park Booking payment routes
        Route::post('{userSlug?}/water-park/payment', [PaypalController::class, 'waterParkPayWithPaypal'])->name('water-park.payment.paypal.store');
        Route::get('{userSlug?}/water-park/status', [PaypalController::class, 'waterParkGetPaypalStatus'])->name('water-park.payment.paypal.status');

        // TVStudio payment routes
        Route::post('{userSlug?}/tvstudio/payment', [PaypalController::class, 'tvStudioPayWithPaypal'])->name('tvstudio.payment.paypal.store');
        Route::get('{userSlug?}/tvstudio/status', [PaypalController::class, 'tvStudioGetPaypalStatus'])->name('tvstudio.payment.paypal.status');

        // ArtShowcase payment routes
        Route::post('{userSlug?}/art-showcase/payment', [PaypalController::class, 'artShowcasePayWithPaypal'])->name('art-showcase.payment.paypal.store');
        Route::get('{userSlug?}/art-showcase/status', [PaypalController::class, 'artShowcaseGetPaypalStatus'])->name('art-showcase.payment.paypal.status');

        // Tattoo Studio Booking payment routes
        Route::post('{userSlug?}/tattoo-studio/payment', [PaypalController::class, 'tattooStudioPayWithPaypal'])->name('tattoo-studio.payment.paypal.store');
        Route::get('{userSlug?}/tattoo-studio/status', [PaypalController::class, 'tattooStudioGetPaypalStatus'])->name('tattoo-studio.payment.paypal.status');

        // PhotoStudio payment routes
        Route::post('{userSlug?}/photo-studio/payment', [PaypalController::class, 'photoStudioPayWithPaypal'])->name('photo-studio.payment.paypal.store');
        Route::get('{userSlug?}/photo-studio/status', [PaypalController::class, 'photoStudioGetPaypalStatus'])->name('photo-studio.payment.paypal.status');

        // Ebook payment routes
        Route::post('{userSlug?}/ebook/payment', [PaypalController::class, 'ebookPayWithPaypal'])->name('ebook.payment.paypal.store');
        Route::get('{userSlug?}/ebook/status', [PaypalController::class, 'ebookGetPaypalStatus'])->name('ebook.payment.paypal.status');

        // YogaClasses payment routes
        Route::post('{userSlug?}/yoga-classes/payment', [PaypalController::class, 'yogaClassesPayWithPaypal'])->name('yoga-classes.payment.paypal.store');
        Route::get('{userSlug?}/yoga-classes/status', [PaypalController::class, 'yogaClassesGetPaypalStatus'])->name('yoga-classes.payment.paypal.status');

        // HairCareStudio payment routes
        Route::post('{userSlug?}/hair-care-studio/payment', [PaypalController::class, 'hairCareStudioPayWithPaypal'])->name('hair-care-studio.payment.paypal.store');
        Route::get('{userSlug?}/hair-care-studio/status', [PaypalController::class, 'hairCareStudioGetPaypalStatus'])->name('hair-care-studio.payment.paypal.status');

        // PetCare payment routes
        Route::post('{userSlug?}/pet-care/payment', [PaypalController::class, 'petCarePayWithPaypal'])->name('pet-care.payment.paypal.store');
        Route::get('{userSlug?}/pet-care/status', [PaypalController::class, 'petCareGetPaypalStatus'])->name('pet-care.payment.paypal.status');

        // Boutique Studio payment routes
        Route::post('{userSlug?}/boutique-studio/payment', [PaypalController::class, 'boutiqueStudioPayWithPaypal'])->name('boutique-studio.payment.paypal.store');
        Route::get('{userSlug?}/boutique-studio/status', [PaypalController::class, 'boutiqueStudioGetPaypalStatus'])->name('boutique-studio.payment.paypal.status');

        // InvestmentSystem payment routes
        Route::post('{userSlug?}/investment-system/payment', [PaypalController::class, 'investmentSystemPayWithPaypal'])->name('investment-system.payment.paypal.store');
        Route::get('{userSlug?}/investment-system/status', [PaypalController::class, 'investmentSystemGetPaypalStatus'])->name('investment-system.payment.paypal.status');
    });
});

<?php

use Workdo\Appointment\Http\Controllers\BrandSettingController;

use Workdo\Appointment\Http\Controllers\AppointmentController;
use Workdo\Appointment\Http\Controllers\QuestionController;
use Workdo\Appointment\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;
use Workdo\Appointment\Http\Controllers\AppointmentCallbackController;
use Workdo\Appointment\Http\Controllers\DashboardController;
use Workdo\Appointment\Http\Controllers\PublicController;
use Workdo\Appointment\Http\Controllers\AppointmentSettingController;
use Workdo\Appointment\Http\Middleware\AppointmentSharedDataMiddleware;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:Appointment'])->group(function () {
    Route::get('/appointment', [DashboardController::class, 'index'])->name('appointment.index');
    Route::get('/appointment/settings', [AppointmentSettingController::class, 'index'])->name('appointment.settings.index');
    Route::post('/appointment/settings', [AppointmentSettingController::class, 'update'])->name('appointment.settings.update');
    Route::get('/appointment/settings/faq', [AppointmentSettingController::class, 'faqSettings'])->name('appointment.settings.faq');
    Route::post('/appointment/settings/faq', [AppointmentSettingController::class, 'updateFaq'])->name('appointment.settings.faq.update');
    Route::get('/appointment/settings/privacy', [AppointmentSettingController::class, 'privacySettings'])->name('appointment.settings.privacy');
    Route::post('/appointment/settings/privacy', [AppointmentSettingController::class, 'updatePrivacy'])->name('appointment.settings.privacy.update');
    Route::get('/appointment/settings/terms', [AppointmentSettingController::class, 'termsSettings'])->name('appointment.settings.terms');
    Route::post('/appointment/settings/terms', [AppointmentSettingController::class, 'updateTerms'])->name('appointment.settings.terms.update');

    Route::get('/appointment/settings/hours', [AppointmentSettingController::class, 'appointmentHours'])->name('appointment.settings.hours');
    Route::post('/appointment/settings/hours', [AppointmentSettingController::class, 'storeAppointmentHours'])->name('appointment.settings.hours.store');
    Route::get('/appointment/settings/hours/api', [AppointmentSettingController::class, 'getAppointmentHours'])->name('appointment.settings.hours.get');

    Route::prefix('appointment/questions')->name('appointment.questions.')->group(function () {
        Route::get('/', [QuestionController::class, 'index'])->name('index');
        Route::get('/api', [QuestionController::class, 'api'])->name('api');
        Route::post('/', [QuestionController::class, 'store'])->name('store');
        Route::put('/{question}', [QuestionController::class, 'update'])->name('update');
        Route::delete('/{question}', [QuestionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('appointment/appointments')->name('appointment.appointments.')->group(function () {
        Route::get('/', [AppointmentController::class, 'index'])->name('index');
        Route::get('/calendar', [AppointmentController::class, 'calendar'])->name('calendar');
        Route::post('/', [AppointmentController::class, 'store'])->name('store');
        Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update');
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('appointment/schedules')->name('appointment.schedules.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::post('/{schedule}/approve', [ScheduleController::class, 'approve'])->name('approve');
        Route::post('/{schedule}/reject', [ScheduleController::class, 'reject'])->name('reject');
        Route::post('/{schedule}/complete', [ScheduleController::class, 'complete'])->name('complete');
        Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('appointment/callbacks')->name('appointment.callbacks.')->group(function () {
        Route::get('/', [AppointmentCallbackController::class, 'index'])->name('index');
        Route::post('/{callback}/approve', [AppointmentCallbackController::class, 'approve'])->name('approve');
        Route::post('/{callback}/reject', [AppointmentCallbackController::class, 'reject'])->name('reject');
        Route::post('/{callback}/complete', [AppointmentCallbackController::class, 'complete'])->name('complete');
        Route::delete('/{callback}', [AppointmentCallbackController::class, 'destroy'])->name('destroy');
    });
});

// Public routes with userslug support (no authentication required)
Route::middleware(['web', AppointmentSharedDataMiddleware::class])->group(function () {
    Route::prefix('{userSlug}/appointments')->name('appointment.public.')->group(function () {
        Route::get('/search', [PublicController::class, 'search'])->name('search');
        Route::post('/search', [PublicController::class, 'searchAppointment'])->name('search.post');
        Route::get('/{encryptedId}/book', [PublicController::class, 'book'])->name('book');
        Route::post('/{encryptedId}/book', [PublicController::class, 'store'])->name('store');
        Route::get('/success/{uniqueId}', [PublicController::class, 'success'])->name('success');
        Route::get('/details/{uniqueId}', [PublicController::class, 'details'])->name('details');
        Route::post('/callback/{uniqueId}', [PublicController::class, 'callback'])->name('callback');
        Route::post('/cancel/{uniqueId}', [PublicController::class, 'cancel'])->name('cancel');
        Route::get('/faq', [PublicController::class, 'faq'])->name('faq');
        Route::get('/privacy-policy', [PublicController::class, 'privacyPolicy'])->name('privacy-policy');
        Route::get('/terms-conditions', [PublicController::class, 'termsConditions'])->name('terms-conditions');
        Route::get('/booked-slots/{encryptedId}/{date}', [PublicController::class, 'getBookedSlots'])->name('booked-slots');
    });
});

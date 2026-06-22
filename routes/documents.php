<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentSettingsController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\PublicDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified'])->prefix('documents')->name('documents.')->group(function () {
    Route::get('settings', [DocumentSettingsController::class, 'index'])->middleware('can:manage-document-templates')->name('settings.index');
    Route::get('settings/{type}', [DocumentSettingsController::class, 'index'])
        ->whereIn('type', ['invoice', 'quotation'])
        ->middleware('can:manage-document-templates')
        ->name('settings.type');
    Route::put('settings', [DocumentSettingsController::class, 'update'])->middleware('can:manage-document-templates')->name('settings.update');
    Route::get('settings/sample/{type}/{template}', [DocumentSettingsController::class, 'sample'])->middleware('can:manage-document-templates')->name('settings.sample');
    Route::get('{type}/{id}/preview', [DocumentController::class, 'preview'])->name('preview');
    Route::get('{type}/{id}/pdf', [DocumentController::class, 'pdf'])->name('pdf');
    Route::post('{type}/{id}/share', [DocumentController::class, 'share'])->middleware('can:manage-document-links')->name('share');
    Route::delete('{type}/{id}/share', [DocumentController::class, 'revoke'])->middleware('can:manage-document-links')->name('share.revoke');
    Route::post('{type}/{id}/send', [DocumentController::class, 'send'])->middleware('can:send-documents')->name('send');
    Route::post('invoice/{id}/remind', [DocumentController::class, 'remind'])->middleware('can:send-invoice-reminders')->name('remind');
    Route::get('{type}/{id}/history', [DocumentController::class, 'history'])->middleware('can:view-document-activity')->name('history');
});

Route::middleware('web')->prefix('d')->name('documents.public.')->group(function () {
    Route::get('{token}', [PublicDocumentController::class, 'show'])->name('show');
    Route::get('{token}/pdf', [PublicDocumentController::class, 'pdf'])->name('pdf');
    Route::post('{token}/accept', [PublicDocumentController::class, 'accept'])->name('accept');
    Route::post('{token}/reject', [PublicDocumentController::class, 'reject'])->name('reject');
    Route::post('{token}/pay/{provider}', [InvoicePaymentController::class, 'start'])->name('pay');
    Route::get('{token}/payment/{provider}/return', [InvoicePaymentController::class, 'complete'])->name('payment.return');
    Route::get('{token}/payment/{provider}/cancel', [InvoicePaymentController::class, 'cancel'])->name('payment.cancel');
});

Route::post('document-payments/webhook/stripe', [InvoicePaymentController::class, 'stripeWebhook'])
    ->name('documents.payments.webhook.stripe');

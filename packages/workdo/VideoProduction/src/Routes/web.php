<?php

use Illuminate\Support\Facades\Route;
use Workdo\VideoProduction\Http\Controllers\DashboardController;
use Workdo\VideoProduction\Http\Controllers\ProductionJobController;
use Workdo\VideoProduction\Http\Controllers\ProductionRecordController;
use Workdo\VideoProduction\Http\Controllers\ProductionSettingsController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:VideoProduction'])->group(function () {
    Route::prefix('video-production')->name('video-production.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/jobs', [ProductionJobController::class, 'index'])->name('jobs.index');
        Route::post('/jobs', [ProductionJobController::class, 'store'])->name('jobs.store');
        Route::put('/jobs/{job}', [ProductionJobController::class, 'update'])->name('jobs.update');
        Route::delete('/jobs/{job}', [ProductionJobController::class, 'destroy'])->name('jobs.destroy');
        Route::get('/settings', [ProductionSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [ProductionSettingsController::class, 'update'])->name('settings.update');
        Route::post('/records/{type}', [ProductionRecordController::class, 'store'])->name('records.store');
        Route::put('/records/{type}/{record}', [ProductionRecordController::class, 'update'])->name('records.update');
        Route::delete('/records/{type}/{record}', [ProductionRecordController::class, 'destroy'])->name('records.destroy');
    });
});

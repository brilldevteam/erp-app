<?php

use Illuminate\Support\Facades\Route;
use Workdo\VideoProduction\Http\Controllers\DashboardController;
use Workdo\VideoProduction\Http\Controllers\ProductionRecordController;
use Workdo\VideoProduction\Http\Controllers\ProductionSettingsController;
use Workdo\VideoProduction\Http\Controllers\ProductionReportEmailController;
use Workdo\VideoProduction\Http\Controllers\ClientAccessController;

Route::middleware(['web', 'auth', 'verified', 'PlanModuleCheck:VideoProduction'])->group(function () {
    Route::prefix('video-production')->name('video-production.')->group(function () {
        Route::get('/', [DashboardController::class, 'portal'])->name('index');
        Route::get('/project/{project}', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/project/{project}/client-access', [ClientAccessController::class, 'store'])->name('client-access.store');
        Route::post('/project/{project}/client-access/{user}/attach', [ClientAccessController::class, 'attach'])->name('client-access.attach');
        Route::put('/project/{project}/client-access/{user}', [ClientAccessController::class, 'update'])->name('client-access.update');
        Route::put('/project/{project}/client-access/{user}/password', [ClientAccessController::class, 'changePassword'])->name('client-access.password');
        Route::post('/project/{project}/client-access/{user}/impersonate', [ClientAccessController::class, 'impersonate'])->name('client-access.impersonate');
        Route::post('/project/{project}/claim-unassigned', [DashboardController::class, 'claimUnassigned'])->name('claim-unassigned');
        Route::put('/project/{project}/settings', [ProductionSettingsController::class, 'update'])->name('settings.update');
        Route::post('/project/{project}/reports/email', [ProductionReportEmailController::class, 'send'])->name('reports.email');
        Route::post('/project/{project}/records/{type}', [ProductionRecordController::class, 'store'])->name('records.store');
        Route::put('/project/{project}/records/{type}/{record}', [ProductionRecordController::class, 'update'])->name('records.update');
        Route::delete('/project/{project}/records/{type}/{record}', [ProductionRecordController::class, 'destroy'])->name('records.destroy');
    });
});

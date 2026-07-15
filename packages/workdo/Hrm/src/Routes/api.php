<?php

use Illuminate\Support\Facades\Route;
use Workdo\Hrm\Http\Controllers\Api\AttendanceApiController;
use Workdo\Hrm\Http\Controllers\Api\DashboardApiController;
use Workdo\Hrm\Http\Controllers\Api\HolidayApiController;
use Workdo\Hrm\Http\Controllers\Api\LeaveApiController;
use Workdo\Hrm\Http\Controllers\Api\LeaveTypeApiController;

Route::prefix('api')->middleware(['api.json'])->group(function () {
    Route::group(['middleware' => ['auth:sanctum'], 'prefix' => 'hrm'], function () {
        Route::get('home', [DashboardApiController::class, 'index']);
        Route::post('events', [DashboardApiController::class, 'getEvents']);
        Route::get('holidays-list', [HolidayApiController::class, 'index']);
        
        Route::post('attendence-history', [AttendanceApiController::class, 'history']);
        Route::post('clock-in-out', [AttendanceApiController::class, 'clockInOut']);
        Route::post('attendance/pause', [AttendanceApiController::class, 'pause']);
        Route::post('attendance/resume', [AttendanceApiController::class, 'resume']);
        Route::get('attendance/status', [AttendanceApiController::class, 'status']);
        Route::put('attendance/work-update', [AttendanceApiController::class, 'workUpdate']);
        Route::post('attendance/{attendance}/corrections', [AttendanceApiController::class, 'requestCorrection']);
        
        Route::get('get-leaves', [LeaveApiController::class, 'index']);
        Route::post('leave-request', [LeaveApiController::class, 'store']);
        
        Route::get('get-leaves-types', [LeaveTypeApiController::class, 'index']);
    });
});

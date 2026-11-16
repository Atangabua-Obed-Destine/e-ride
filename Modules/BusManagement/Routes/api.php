<?php

use Illuminate\Support\Facades\Route;
use Modules\BusManagement\Http\Controllers\Api\BusBookingController;
use Modules\BusManagement\Http\Controllers\Api\BusController;

/*
| E-Ride Bus Travel, customer app.
*/
Route::group(['prefix' => 'customer/bus', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::middleware('throttle:120,1,bus-read')->group(function () {
        Route::get('meta', [BusController::class, 'meta']);
        Route::get('search', [BusController::class, 'search']);
        Route::get('agencies', [BusController::class, 'agencies']);
        Route::get('agencies/{id}', [BusController::class, 'agency'])->whereUuid('id');
        Route::get('departures/{id}', [BusController::class, 'departure'])->whereUuid('id');
        Route::get('bookings', [BusBookingController::class, 'index']);
        Route::get('bookings/{id}', [BusBookingController::class, 'show']);
    });
    Route::middleware('throttle:30,1,bus-write')->group(function () {
        Route::post('departures/{id}/request', [BusBookingController::class, 'store'])->whereUuid('id');
        Route::post('departures/{id}/waitlist', [BusController::class, 'waitlist'])->whereUuid('id');
        Route::post('bookings/{id}/pay', [BusBookingController::class, 'pay']);
        Route::post('bookings/{id}/cancel', [BusBookingController::class, 'cancel']);
        Route::post('bookings/{id}/keep', [BusBookingController::class, 'keep']);
    });
});

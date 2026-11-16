<?php

use Illuminate\Support\Facades\Route;
use Modules\HotelManagement\Http\Controllers\Api\HotelController;

/*
| E-Ride hotel partners, customer app.
*/
Route::group(['prefix' => 'customer/hotel', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::middleware('throttle:120,1,hotel-read')->group(function () {
        Route::get('hotels/{id}', [HotelController::class, 'show'])->whereUuid('id');
        Route::get('inquiries', [HotelController::class, 'inquiries']);
        Route::get('inquiries/{id}', [HotelController::class, 'inquiry'])->whereUuid('id');
    });
    Route::middleware('throttle:40,1,hotel-write')->group(function () {
        Route::post('hotels/{id}/ask', [HotelController::class, 'ask'])->whereUuid('id');
        Route::post('inquiries/{id}/messages', [HotelController::class, 'send'])->whereUuid('id');
        Route::post('inquiries/{id}/release', [HotelController::class, 'release'])->whereUuid('id');
    });
});

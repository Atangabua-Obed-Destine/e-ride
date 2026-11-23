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

/*
| POS Innova's hotel module (docs/posinnova-hotel-contract.md). Authenticated by the hotel's erh_ key
| in the controller; rate limits per key (limiters from StoreManagement).
*/
Route::group(['prefix' => 'channel/posinnova/v1/hotel'], function () {
    $c = \Modules\HotelManagement\Http\Controllers\Api\PosHotelController::class;
    Route::middleware('throttle:pos-catalog')->group(function () use ($c) {
        Route::get('hello', [$c, 'hello']);
        Route::post('heartbeat', [$c, 'heartbeat']);
        Route::post('availability/full', [$c, 'availabilityFull']);
        Route::post('availability/changes', [$c, 'availabilityChanges']);
    });
    Route::middleware('throttle:pos-orders')->group(function () use ($c) {
        Route::get('inquiries/pending', [$c, 'pending']);
        Route::get('inquiries/{id}', [$c, 'inquiry']);
        Route::post('inquiries/{id}/kept', [$c, 'kept']);
        Route::post('inquiries/{id}/declined', [$c, 'declined']);
        Route::post('inquiries/{id}/reply', [$c, 'reply']);
        Route::post('inquiries/{id}/event', [$c, 'event']);
        Route::post('inquiries/{id}/release-seen', [$c, 'releaseSeen']);
    });
});

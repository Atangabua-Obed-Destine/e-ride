<?php

use Illuminate\Support\Facades\Route;
use Modules\PlaceManagement\Http\Controllers\Api\Customer\StayController;

/*
| Places to stay, customer app.
*/
Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    // Each call is one live Google search, so keep it modest per user.
    Route::get('stay', [StayController::class, 'index'])->middleware('throttle:20,1');
    // Explore Bamenda. Each category opened is one live Google search.
    Route::get('explore', [StayController::class, 'categories']);
    Route::get('explore/{category}', [StayController::class, 'explore'])->where('category', '[a-z0-9_]+')->middleware('throttle:30,1');
});

// Used as a plain image URL by the app, so no token: a signature guards it instead.
// The limit is per IP and generous because mobile carriers share IPs; the
// monthly photo cap is what protects the bill.
Route::get('customer/stay/photo', [StayController::class, 'photo'])
    ->name('stay.photo')
    ->middleware('throttle:600,1');

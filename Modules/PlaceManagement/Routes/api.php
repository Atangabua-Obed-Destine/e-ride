<?php

use Illuminate\Support\Facades\Route;
use Modules\PlaceManagement\Http\Controllers\Api\Customer\ListingController;
use Modules\PlaceManagement\Http\Controllers\Api\Customer\ListingReservationController;
use Modules\PlaceManagement\Http\Controllers\Api\Customer\ListingVisitController;
use Modules\PlaceManagement\Http\Controllers\Api\Customer\StayController;

/*
| Places to stay, customer app.
*/
Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    // Each call is one live Google search, so keep it modest per user.
    Route::get('stay', [StayController::class, 'index'])->middleware('throttle:20,1,stay');
    // Explore Bamenda. Each category opened is one live Google search.
    Route::get('explore', [StayController::class, 'categories']);
    Route::get('explore/{category}', [StayController::class, 'explore'])->where('category', '[a-z0-9_]+')->middleware('throttle:30,1,explore');

    // Homes & spaces. Each group has its own limit (a named counter), so a busy
    // screen never uses up another feature's allowance.
    Route::middleware('throttle:120,1,listing-read')->group(function () {
        Route::get('listings/meta', [ListingController::class, 'meta']);
        Route::get('listings/mine', [ListingController::class, 'mine']);
        Route::get('listings', [ListingController::class, 'index']);
        Route::get('listings/{listing}', [ListingController::class, 'show']);
        Route::get('listing-visits', [ListingVisitController::class, 'index']);
        Route::get('listing-alerts', [ListingController::class, 'alerts']);
    });
    Route::post('listings', [ListingController::class, 'store'])->middleware('throttle:10,1,listing-create');
    Route::middleware('throttle:30,1,listing-write')->group(function () {
        Route::post('listings/{listing}/update', [ListingController::class, 'update']);
        Route::post('listings/{listing}/confirm', [ListingController::class, 'confirm']);
        Route::post('listings/{listing}/rented', [ListingController::class, 'rented']);
        Route::post('listings/{listing}/verification', [ListingController::class, 'verification']);
        Route::post('listing-alerts', [ListingController::class, 'storeAlert']);
        Route::delete('listing-alerts/{alert}', [ListingController::class, 'destroyAlert'])->whereNumber('alert');
    });
    Route::post('listings/{listing}/report', [ListingController::class, 'report'])->middleware('throttle:10,1,listing-report');
    Route::middleware('throttle:20,1,listing-visit')->group(function () {
        Route::post('listings/{listing}/visits', [ListingVisitController::class, 'store']);
        Route::post('listing-visits/{visit}/respond', [ListingVisitController::class, 'respond']);
        Route::post('listing-visits/{visit}/seeker', [ListingVisitController::class, 'seeker']);
        Route::post('listing-visits/{visit}/outcome', [ListingVisitController::class, 'outcome']);
        Route::post('listing-visits/{visit}/taking', [ListingVisitController::class, 'taking']);
    });
    // Reservation money: answers are the visit, with its reservation.
    Route::middleware('throttle:20,1,listing-reservation')->group(function () {
        Route::post('listing-visits/{visit}/reserve', [ListingReservationController::class, 'store']);
        Route::post('listing-reservations/{reservation}/wallet', [ListingReservationController::class, 'payWithWallet']);
        Route::get('listing-reservations/{reservation}/pay-url', [ListingReservationController::class, 'payUrl']);
        Route::post('listing-reservations/{reservation}/cancel', [ListingReservationController::class, 'cancel']);
        Route::post('listing-reservations/{reservation}/respond', [ListingReservationController::class, 'respond']);
        Route::post('listing-reservations/{reservation}/moved-in', [ListingReservationController::class, 'movedIn']);
        Route::post('listing-reservations/{reservation}/problem', [ListingReservationController::class, 'problem']);
        Route::post('listing-reservations/{reservation}/refund-to-momo', [ListingReservationController::class, 'refundToMomo']);
    });
});

// Opened in the app's payment webview, which sends no token: the signature guards it.
Route::get('customer/listing-reservation-pay/{reservation}', [ListingReservationController::class, 'pay'])
    ->name('listing-reservation.pay')
    ->middleware(['signed:relative', 'throttle:30,1,listing-reservation-pay']);

// Used as a plain image URL by the app, so no token: a signature guards it instead.
// The limit is per IP and generous because mobile carriers share IPs; the
// monthly photo cap is what protects the bill.
Route::get('customer/stay/photo', [StayController::class, 'photo'])
    ->name('stay.photo')
    ->middleware('throttle:600,1,stay-photo');

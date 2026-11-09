<?php

use Illuminate\Support\Facades\Route;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\GooglePlaceController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\ListingAdminController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\ListingReservationAdminController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\OnDutyController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\PlaceCategoryController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\PlaceController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\PlaceSettingController;

/*
| Explore Bamenda, admin panel (sidebar "Explore Bamenda").
*/
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'places', 'as' => 'place.'], function () {
        Route::middleware('can:place_view')->group(function () {
            Route::get('/', [PlaceController::class, 'index'])->name('index');
            Route::get('google', [GooglePlaceController::class, 'index'])->name('google');
            Route::get('categories', [PlaceCategoryController::class, 'index'])->name('categories');
            Route::get('on-duty', [OnDutyController::class, 'index'])->name('on-duty');
        });
        Route::middleware('can:place_add')->group(function () {
            Route::get('create', [PlaceController::class, 'create'])->name('create');
            Route::post('/', [PlaceController::class, 'store'])->name('store');
        });
        Route::middleware('can:place_edit')->group(function () {
            Route::post('google/hide', [GooglePlaceController::class, 'hide'])->name('google.hide');
            Route::post('google/unhide', [GooglePlaceController::class, 'unhide'])->name('google.unhide');
            Route::post('categories', [PlaceCategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [PlaceCategoryController::class, 'update'])->name('categories.update');
            Route::post('on-duty', [OnDutyController::class, 'save'])->name('on-duty.save');
            Route::get('{place}/edit', [PlaceController::class, 'edit'])->name('edit');
            Route::put('{place}', [PlaceController::class, 'update'])->name('update');
            Route::post('{place}/status', [PlaceController::class, 'status'])->name('status');
        });
        Route::delete('{place}', [PlaceController::class, 'destroy'])->name('destroy')->middleware('can:place_delete');
    });

    // Homes & spaces: customers' listings, staff visits, reports, policy.
    Route::group(['prefix' => 'homes', 'as' => 'listing.'], function () {
        Route::middleware('can:place_view')->group(function () {
            Route::get('/', [ListingAdminController::class, 'index'])->name('index');
            Route::get('visits', [ListingAdminController::class, 'visits'])->name('visits');
            Route::get('reports', [ListingAdminController::class, 'reports'])->name('reports');
            Route::get('{listing}', [ListingAdminController::class, 'show'])->name('show')->whereUuid('listing');
            Route::get('reservations', [ListingReservationAdminController::class, 'index'])->name('reservations');
            Route::get('reservations/{reservation}', [ListingReservationAdminController::class, 'show'])->name('reservation')->whereUuid('reservation');
            Route::get('payouts', [ListingReservationAdminController::class, 'payouts'])->name('payouts');
        });
        Route::middleware('can:place_edit')->group(function () {
            Route::get('settings/policy', [ListingAdminController::class, 'settings'])->name('settings');
            Route::post('settings/policy', [ListingAdminController::class, 'saveSettings'])->name('settings.save');
            Route::post('{listing}/approve', [ListingAdminController::class, 'approve'])->name('approve');
            Route::post('{listing}/reviewed', [ListingAdminController::class, 'reviewed'])->name('reviewed');
            Route::post('{listing}/changes', [ListingAdminController::class, 'changes'])->name('changes');
            Route::post('{listing}/refuse', [ListingAdminController::class, 'refuse'])->name('refuse');
            Route::post('{listing}/remove', [ListingAdminController::class, 'remove'])->name('remove');
            Route::post('visits/{verification}/assign', [ListingAdminController::class, 'assign'])->name('assign');
            Route::get('visits/{verification}/at-the-place', [ListingAdminController::class, 'visitPage'])->name('visit');
            Route::post('visits/{verification}/at-the-place', [ListingAdminController::class, 'visitSubmit'])->name('visit.submit');
            Route::post('reports/{report}/decide', [ListingAdminController::class, 'decide'])->name('decide');
            Route::post('reservations/{reservation}/decide', [ListingReservationAdminController::class, 'decide'])->name('reservation.decide');
            Route::post('payouts/{payout}/paid', [ListingReservationAdminController::class, 'markPaid'])->name('payout.paid');
        });
    });

    Route::group(['prefix' => 'place-settings', 'as' => 'place-setting.', 'middleware' => 'can:place_edit'], function () {
        Route::get('/', [PlaceSettingController::class, 'edit'])->name('edit');
        Route::put('/', [PlaceSettingController::class, 'update'])->name('update');
    });
});

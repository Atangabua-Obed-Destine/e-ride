<?php

use Illuminate\Support\Facades\Route;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\GooglePlaceController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\PlaceController;
use Modules\PlaceManagement\Http\Controllers\Web\Admin\PlaceSettingController;

/*
| Places to stay, admin panel (sidebar "Hotels & Stays").
*/
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'places', 'as' => 'place.'], function () {
        Route::middleware('can:place_view')->group(function () {
            Route::get('/', [PlaceController::class, 'index'])->name('index');
            Route::get('google', [GooglePlaceController::class, 'index'])->name('google');
        });
        Route::middleware('can:place_add')->group(function () {
            Route::get('create', [PlaceController::class, 'create'])->name('create');
            Route::post('/', [PlaceController::class, 'store'])->name('store');
        });
        Route::middleware('can:place_edit')->group(function () {
            Route::post('google/hide', [GooglePlaceController::class, 'hide'])->name('google.hide');
            Route::post('google/unhide', [GooglePlaceController::class, 'unhide'])->name('google.unhide');
            Route::get('{place}/edit', [PlaceController::class, 'edit'])->name('edit');
            Route::put('{place}', [PlaceController::class, 'update'])->name('update');
            Route::post('{place}/status', [PlaceController::class, 'status'])->name('status');
        });
        Route::delete('{place}', [PlaceController::class, 'destroy'])->name('destroy')->middleware('can:place_delete');
    });

    Route::group(['prefix' => 'place-settings', 'as' => 'place-setting.', 'middleware' => 'can:place_edit'], function () {
        Route::get('/', [PlaceSettingController::class, 'edit'])->name('edit');
        Route::put('/', [PlaceSettingController::class, 'update'])->name('update');
    });
});

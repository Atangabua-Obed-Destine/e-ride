<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Api\Customer\TripRequestController;

Route::group(['prefix' => 'customer'], function () {
    Route::group(['prefix' => 'parcel', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::controller(\Modules\ParcelManagement\Http\Controllers\Api\Customer\ParcelCategoryController::class)->group(function () {
            Route::get('category', 'categoryFareList');
        });
        Route::controller(\Modules\ParcelManagement\Http\Controllers\Api\Customer\ParcelController::class)->group(function () {
            Route::get('vehicle', 'vehicleList');
            Route::get('suggested-vehicle-category',  'suggestedVehicleCategory');
        });
    });
});

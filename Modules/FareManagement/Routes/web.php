<?php

use Illuminate\Support\Facades\Route;
use Modules\FareManagement\Http\Controllers\Web\Admin\ParcelFareController;
use Modules\FareManagement\Http\Controllers\Web\Admin\SurgePricingController;
use Modules\FareManagement\Http\Controllers\Web\Admin\TripFareController;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'fare', 'as' => 'fare.'], function () {
        Route::group(['prefix' => 'parcel', 'as' => 'parcel.'], function () {
            Route::controller(ParcelFareController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create/{zone_id}', 'create')->name('create');
                Route::post('store', 'store')->name('store');
            });
        });

        Route::group(['prefix' => 'trip', 'as' => 'trip.'], function () {
            Route::controller(TripFareController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create/{zone_id}', 'create')->name('create');
                Route::post('store', 'store')->name('store');
            });
        });

        Route::group(['prefix' => 'surge-pricing', 'as' => 'surge-pricing.'], function () {
            Route::controller(SurgePricingController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'store')->name('store');
                Route::get('status', 'status')->name('status');
                Route::delete('delete/{id}', 'destroy')->name('delete');
                Route::get('show/{id}', 'show')->name('show');
                Route::get('export', 'export')->name('export');
                Route::get('edit/{id}','edit')->name('edit');
                Route::put('update/{id}','update')->name('update');
                Route::get('get-zones/{id}', 'getZones')->name('get-zones');
                Route::get('get-custom-date-list/{id}', 'getCustomDateList')->name('get-custom-date-list');
                Route::get('get-custom-date-list-in-details/{id}', 'getCustomDateListInDetails')->name('get-custom-date-list-in-details');
                Route::get('get-statistics-data/{id}', 'getStatisticsData')->name('get-statistics-data');
                Route::put('update-customer-note/{id}', 'updateCustomerNote')->name('update-customer-note');
                Route::put('update-zone-list/{id}', 'updateZoneList')->name('update-zone-list');
                Route::get('edit-schedule/{id}', 'editSchedule')->name('edit-schedule');
                Route::get('edit-price-applicable-for/{id}', 'editPriceApplicableFor')->name('edit-price-applicable-for');
            });
        });
    });
});

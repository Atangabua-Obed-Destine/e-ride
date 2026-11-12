<?php

use Illuminate\Support\Facades\Route;
use Modules\TripManagement\Http\Controllers\Web\BookingDepositController;
use Modules\TripManagement\Http\Controllers\Web\DriverReportController;
use Modules\TripManagement\Http\Controllers\Web\HealthRideAdminController;
use Modules\TripManagement\Http\Controllers\Web\LostItemAdminController;
use Modules\TripManagement\Http\Controllers\Web\MoveAdminController;
use Modules\TripManagement\Http\Controllers\Web\RefundController;
use Modules\TripManagement\Http\Controllers\Web\SafetyAlertController;

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
    Route::group(['prefix' => 'trip', 'as' => 'trip.'], function () {
        Route::controller(\Modules\TripManagement\Http\Controllers\Web\TripController::class)->group(function () {
            Route::get('list/{type}', 'tripList')->name('index');
            Route::get('details/{id}', 'show')->name('show');
            Route::delete('delete/{id}', 'destroy')->name('delete');
            Route::get('export', 'export')->name('export');
            Route::get('log', 'log')->name('log');
            Route::get('invoice/{id}', 'invoice')->name('invoice');
        });

        Route::group(['prefix' => 'refund', 'as' => 'refund.'], function () {
            Route::controller(RefundController::class)->group(function () {
                Route::get('list/{type}', 'parcelRefundList')->name('index');
                Route::get('details/{id}', 'show')->name('show');
                Route::post('approved/{id}', 'storeApproved')->name('approved');
                Route::post('denied/{id}', 'storeDenied')->name('denied');
                Route::post('store/{id}', 'store')->name('store');
                Route::get('export', 'export')->name('export');
            });
        });
    });

    Route::group(['prefix' => 'booking-deposit', 'as' => 'booking-deposit.'], function () {
        Route::get('/', [BookingDepositController::class, 'index'])->name('index');
        Route::get('settings', [BookingDepositController::class, 'settings'])->name('settings');
        Route::put('settings', [BookingDepositController::class, 'updateSettings'])->name('settings.update');
    });

    Route::group(['prefix' => 'move', 'as' => 'move.'], function () {
        Route::get('/', [MoveAdminController::class, 'index'])->name('index');
        Route::get('settings', [MoveAdminController::class, 'settings'])->name('settings');
        Route::put('settings', [MoveAdminController::class, 'updateSettings'])->name('settings.update');
        Route::get('deposits', [MoveAdminController::class, 'deposits'])->name('deposits');
        Route::put('deposits', [MoveAdminController::class, 'updateDeposits'])->name('deposits.update');
        Route::get('directory', [MoveAdminController::class, 'directory'])->name('directory');
        Route::get('directory/create', [MoveAdminController::class, 'createListing'])->name('directory.create');
        Route::post('directory', [MoveAdminController::class, 'storeListing'])->name('directory.store');
        Route::get('directory/{id}/edit', [MoveAdminController::class, 'editListing'])->name('directory.edit');
        Route::put('directory/{id}', [MoveAdminController::class, 'updateListing'])->name('directory.update');
        Route::put('directory/{id}/toggle', [MoveAdminController::class, 'toggleListing'])->name('directory.toggle');
    });

    // Health rides: every health ride and the settings.
    Route::group(['prefix' => 'health-rides', 'as' => 'health-ride.'], function () {
        Route::get('/', [HealthRideAdminController::class, 'index'])->name('index');
        Route::post('settings', [HealthRideAdminController::class, 'settings'])->name('settings');
    });

    // Lost and found: cases, staff actions and the settings.
    Route::group(['prefix' => 'lost-items', 'as' => 'lost-item.'], function () {
        Route::get('/', [LostItemAdminController::class, 'index'])->name('index');
        Route::post('settings', [LostItemAdminController::class, 'settings'])->name('settings');
        Route::post('{item}/action', [LostItemAdminController::class, 'act'])->name('act');
    });

    Route::group(['prefix' => 'driver-report', 'as' => 'driver-report.'], function () {
        Route::get('/', [DriverReportController::class, 'index'])->name('index');
        Route::put('{report}', [DriverReportController::class, 'update'])->name('update');
    });

    Route::group(['prefix' => 'safety-alert', 'as' => 'safety-alert.'], function () {
        Route::controller(SafetyAlertController::class)->group(function () {
            Route::get('list/{type}', 'index')->name('index');
            Route::get('export/{type}', 'export')->name('export');
            Route::put('mark-as-solved/{id}', 'markAsSolved')->name('mark-as-solved');
            Route::put('ajax-mark-as-solved/{id}', 'ajaxMarkAsSolved')->name('ajax-mark-as-solved');
        });
    });

});

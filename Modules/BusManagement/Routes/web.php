<?php

use Illuminate\Support\Facades\Route;
use Modules\BusManagement\Http\Controllers\Web\Admin\BusAdminController;
use Modules\BusManagement\Http\Controllers\Web\Partner\AuthController;
use Modules\BusManagement\Http\Controllers\Web\Partner\DepartureController;
use Modules\BusManagement\Http\Controllers\Web\Partner\DestinationController;
use Modules\BusManagement\Http\Controllers\Web\Partner\FleetController;
use Modules\BusManagement\Http\Controllers\Web\Partner\PaymentController;
use Modules\BusManagement\Http\Controllers\Web\Partner\StaffController;
use Modules\BusManagement\Http\Middleware\BusPartner;

/*
| E-Ride Bus Travel. The bus agencies' portal is a phone-first site under /partner/bus-agency,
| separate from the Food & Shops partner portal (/partner).
*/

Route::group(['prefix' => 'partner/bus-agency', 'as' => 'bus-partner.'], function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:10,1,bus-login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', fn() => redirect()->route('bus-partner.login'))->name('home');

    // Everyone at the agency.
    Route::middleware(BusPartner::class)->group(function () {
        Route::get('departures', [DepartureController::class, 'index'])->name('departures');
    });

    // The manager only.
    Route::middleware(BusPartner::class . ':manager')->group(function () {
        Route::get('fleet', [FleetController::class, 'index'])->name('fleet');
        Route::post('fleet/{bus?}', [FleetController::class, 'save'])->name('fleet.save')->whereUuid('bus');

        Route::get('destinations', [DestinationController::class, 'index'])->name('destinations');
        Route::post('destinations/rules', [DestinationController::class, 'rules'])->name('destinations.rules');
        Route::post('destinations/{destination?}', [DestinationController::class, 'save'])->name('destinations.save')->whereUuid('destination');

        Route::post('departures/line/{line?}', [DepartureController::class, 'saveLine'])->name('departures.line')->whereUuid('line');
        Route::post('departures/extra', [DepartureController::class, 'extra'])->name('departures.extra');
        Route::post('departures/{departure}/bus', [DepartureController::class, 'vehicle'])->name('departures.vehicle')->whereUuid('departure');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments');
        Route::post('payments/fapshi', [PaymentController::class, 'saveFapshi'])->name('payments.fapshi')->middleware('throttle:20,1,bus-fapshi');
        Route::post('payments/test', [PaymentController::class, 'testFapshi'])->name('payments.test')->middleware('throttle:20,1,bus-fapshi');

        Route::get('staff', [StaffController::class, 'index'])->name('staff');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle')->whereNumber('staff');
        Route::post('staff/{staff}/password', [StaffController::class, 'password'])->name('staff.password')->whereNumber('staff');
    });
});

// E-Ride admin (sidebar "Bus agencies").
Route::group(['prefix' => 'admin/bus', 'as' => 'admin.bus.', 'middleware' => 'admin'], function () {
    Route::get('/', [BusAdminController::class, 'index'])->name('index');
    Route::post('start', [BusAdminController::class, 'start'])->name('start');
    Route::post('settings', [BusAdminController::class, 'settings'])->name('settings');
    Route::get('{agency}', [BusAdminController::class, 'show'])->name('show')->whereUuid('agency');
    Route::post('{agency}/details', [BusAdminController::class, 'details'])->name('details')->whereUuid('agency');
    Route::post('{agency}/check', [BusAdminController::class, 'check'])->name('check')->whereUuid('agency');
    Route::post('{agency}/licence', [BusAdminController::class, 'licence'])->name('licence')->whereUuid('agency');
    Route::get('{agency}/licence', [BusAdminController::class, 'licenceFile'])->name('licence.file')->whereUuid('agency');
    Route::post('{agency}/verify', [BusAdminController::class, 'verify'])->name('verify')->whereUuid('agency');
    Route::post('{agency}/pause', [BusAdminController::class, 'pause'])->name('pause')->whereUuid('agency');
});

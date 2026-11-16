<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;
use Modules\BusManagement\Http\Controllers\Web\Admin\BusAdminController;
use Modules\BusManagement\Http\Controllers\Web\BusPayController;
use Modules\BusManagement\Http\Controllers\Web\Partner\AuthController;
use Modules\BusManagement\Http\Controllers\Web\Partner\BoardingController;
use Modules\BusManagement\Http\Controllers\Web\Partner\SellController;
use Modules\BusManagement\Http\Controllers\Web\Partner\TodayController;
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

        Route::get('boarding', [BoardingController::class, 'index'])->name('boarding');
        Route::get('boarding/{departure}/manifest', [BoardingController::class, 'manifest'])->name('boarding.manifest')->whereUuid('departure');
        Route::get('boarding/{departure}/print', [BoardingController::class, 'print'])->name('boarding.print')->whereUuid('departure');
        Route::post('boarding/{departure}/scan', [BoardingController::class, 'scan'])->name('boarding.scan')->whereUuid('departure')->middleware('throttle:120,1,bus-scan');
        Route::post('boarding/{departure}/undo/{ticket}', [BoardingController::class, 'undo'])->name('boarding.undo')->whereUuid('departure');
        Route::post('boarding/{departure}/start', [BoardingController::class, 'start'])->name('boarding.start')->whereUuid('departure');
        Route::post('boarding/{departure}/departed', [BoardingController::class, 'depart'])->name('boarding.depart')->whereUuid('departure');
    });

    // The manager and the counter agents.
    Route::middleware(BusPartner::class . ':manager,counter')->group(function () {
        Route::get('today', [TodayController::class, 'index'])->name('today');
        Route::get('today/feed', [TodayController::class, 'feed'])->name('today.feed');
        Route::post('bookings/{booking}/accept', [TodayController::class, 'accept'])->name('bookings.accept')->whereUuid('booking');
        Route::post('bookings/{booking}/decline', [TodayController::class, 'decline'])->name('bookings.decline')->whereUuid('booking');

        Route::get('sell', [SellController::class, 'index'])->name('sell');
        Route::get('sell/{departure}/seats', [SellController::class, 'seats'])->name('sell.seats')->whereUuid('departure');
        Route::post('sell/{departure}', [SellController::class, 'sell'])->name('sell.submit')->whereUuid('departure')->middleware('throttle:60,1,bus-sell');
        Route::get('tickets/{code}/print', [SellController::class, 'print'])->name('tickets.print')->where('code', '[A-Z0-9]{6,12}');

        Route::post('boarding/{departure}/delay', [BoardingController::class, 'delay'])->name('boarding.delay')->whereUuid('departure');
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

        Route::post('bookings/{booking}/cancel', [TodayController::class, 'cancel'])->name('bookings.cancel')->whereUuid('booking');
        Route::post('bookings/{booking}/refund-sent', [TodayController::class, 'refundSent'])->name('bookings.refund')->whereUuid('booking');
        Route::post('boarding/{departure}/cancel', [BoardingController::class, 'cancel'])->name('boarding.cancel')->whereUuid('departure');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments');
        Route::post('payments/fapshi', [PaymentController::class, 'saveFapshi'])->name('payments.fapshi')->middleware('throttle:20,1,bus-fapshi');
        Route::post('payments/test', [PaymentController::class, 'testFapshi'])->name('payments.test')->middleware('throttle:20,1,bus-fapshi');
        Route::get('payments/statements/{statement}', [PaymentController::class, 'statement'])->name('payments.statement')->whereUuid('statement');
        Route::get('payments/statements/{statement}/csv', [PaymentController::class, 'csv'])->name('payments.statement.csv')->whereUuid('statement');
        Route::post('payments/statements/{statement}/pay', [PaymentController::class, 'pay'])->name('payments.statement.pay')->whereUuid('statement')->middleware('throttle:10,1,bus-statement-pay');

        Route::get('staff', [StaffController::class, 'index'])->name('staff');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle')->whereNumber('staff');
        Route::post('staff/{staff}/password', [StaffController::class, 'password'])->name('staff.password')->whereNumber('staff');
    });
});

// Mobile money for tickets, at each agency's own Fapshi. None of the URLs before the end contain "success" or
// "fail": the app's payment screen closes on the first one that does.
Route::group(['prefix' => 'bus', 'as' => 'bus.'], function () {
    Route::post('fapshi/{code}', [BusPayController::class, 'webhook'])->name('fapshi.webhook')->where('code', '[a-z0-9]{8,24}')
        ->withoutMiddleware([VerifyCsrfToken::class])->middleware('throttle:120,1,bus-webhook');
    Route::get('pay/{reference}/return', [BusPayController::class, 'return'])->name('pay.return')->where('reference', 'bus-[A-Za-z0-9-]{3,60}');
    Route::get('pay/{reference}/check', [BusPayController::class, 'check'])->name('pay.check')->where('reference', 'bus-[A-Za-z0-9-]{3,60}')
        ->middleware('throttle:30,1,bus-pay-check');
    Route::get('pay/{reference}/{outcome}', [BusPayController::class, 'end'])->name('pay.end')->where('reference', 'bus-[A-Za-z0-9-]{3,60}')
        ->where('outcome', 'success|fail');
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
    Route::post('statements/{statement}/paid', [BusAdminController::class, 'statementPaid'])->name('statement.paid')->whereUuid('statement');
    Route::post('{agency}/sandbox', [BusAdminController::class, 'sandbox'])->name('sandbox')->whereUuid('agency');
});

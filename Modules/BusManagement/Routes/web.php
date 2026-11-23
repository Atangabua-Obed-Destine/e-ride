<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VerifyCsrfToken;
use Modules\BusManagement\Http\Controllers\Web\Admin\BusAdminController;
use Modules\BusManagement\Http\Controllers\Web\BusPayController;
use Modules\BusManagement\Http\Controllers\Web\Partner\AuthController;
use Modules\BusManagement\Http\Controllers\Web\Partner\BoardingController;
use Modules\BusManagement\Http\Controllers\Web\Partner\ClosureController;
use Modules\BusManagement\Http\Controllers\Web\Partner\CollectController;
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
        // Any agent can print tickets (at the counter, and at the bus for a ticket not collected).
        Route::get('tickets/{code}/print', [SellController::class, 'print'])->name('tickets.print')->where('code', '[A-Z0-9]{6,12}');
        Route::get('bookings/{booking}/id/{side}', [TodayController::class, 'idPhoto'])->name('bookings.id')->whereUuid('booking')->where('side', 'front|back');
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
        Route::get('share', [\Modules\BusManagement\Http\Controllers\Web\Partner\ShareController::class, 'index'])->name('share');
        Route::get('collect', [CollectController::class, 'index'])->name('collect');
        Route::get('collect/lookup', [CollectController::class, 'lookup'])->name('collect.lookup')->middleware('throttle:120,1,bus-collect');

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

        Route::get('closures', [ClosureController::class, 'index'])->name('closures');
        Route::post('closures/preview', [ClosureController::class, 'preview'])->name('closures.preview')->middleware('throttle:120,1,bus-closure-preview');
        Route::post('closures', [ClosureController::class, 'store'])->name('closures.store');
        Route::get('closures/{closure}', [ClosureController::class, 'show'])->name('closures.show')->whereUuid('closure');
        Route::post('closures/{closure}/confirm', [ClosureController::class, 'confirm'])->name('closures.confirm')->whereUuid('closure');
        Route::post('closures/{closure}/lift', [ClosureController::class, 'lift'])->name('closures.lift')->whereUuid('closure');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments');
        Route::post('payments/fapshi', [PaymentController::class, 'saveFapshi'])->name('payments.fapshi')->middleware('throttle:20,1,bus-fapshi');
        Route::post('payments/test', [PaymentController::class, 'testFapshi'])->name('payments.test')->middleware('throttle:20,1,bus-fapshi');
        Route::get('payments/statements/{statement}', [PaymentController::class, 'statement'])->name('payments.statement')->whereUuid('statement');
        Route::get('payments/statements/{statement}/csv', [PaymentController::class, 'csv'])->name('payments.statement.csv')->whereUuid('statement');
        Route::post('payments/statements/{statement}/pay', [PaymentController::class, 'pay'])->name('payments.statement.pay')->whereUuid('statement')->middleware('throttle:10,1,bus-statement-pay');

        Route::post('share/logo', [\Modules\BusManagement\Http\Controllers\Web\Partner\ShareController::class, 'saveLogo'])->name('share.logo')->middleware('throttle:10,1,bus-share-logo');
        Route::post('share/look', [\Modules\BusManagement\Http\Controllers\Web\Partner\ShareController::class, 'saveLook'])->name('share.look');
        Route::post('share/design', [\Modules\BusManagement\Http\Controllers\Web\Partner\ShareController::class, 'saveDesign'])->name('share.design')->middleware('throttle:10,1,bus-share-design');
        Route::post('share/design/remove', [\Modules\BusManagement\Http\Controllers\Web\Partner\ShareController::class, 'removeDesign'])->name('share.design.remove');
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

/*
| The bus website for travellers (eride.../bus): anyone can search and look at seats; booking needs the
| E-Ride account (its own sign-in, guard bus_web). What needs the camera, refunds or the waitlist stays in the app.
*/
Route::group(['prefix' => 'bus', 'as' => 'bus.web.'], function () {
    $site = \Modules\BusManagement\Http\Controllers\Web\Site\SiteController::class;
    $auth = \Modules\BusManagement\Http\Controllers\Web\Site\SiteAuthController::class;
    $book = \Modules\BusManagement\Http\Controllers\Web\Site\SiteBookingController::class;
    $signedIn = \Modules\BusManagement\Http\Middleware\BusWebAuth::class;

    Route::middleware('throttle:120,1,bus-web-page')->group(function () use ($site) {
        Route::get('/', [$site, 'home'])->name('home');
        Route::get('search', [$site, 'search'])->name('search');
        Route::get('a/{slug}', [$site, 'agency'])->name('agency')->where('slug', '[a-z0-9-]{2,120}');
        Route::get('d/{id}', [$site, 'departure'])->name('departure')->whereUuid('id');
        Route::get('app', [$site, 'app'])->name('app');
    });
    Route::get('d/{id}/live', [$site, 'liveSeats'])->name('live')->whereUuid('id')->middleware('throttle:60,1,bus-web-live');
    Route::post('d/{id}/seats', [$site, 'pick'])->name('pick')->whereUuid('id')->middleware('throttle:30,1,bus-web-pick');

    Route::get('login', [$auth, 'showLogin'])->name('login');
    Route::post('login', [$auth, 'login'])->name('login.submit')->middleware('throttle:10,1,bus-web-login');
    Route::get('register', [$auth, 'showRegister'])->name('register');
    Route::post('register', [$auth, 'register'])->name('register.submit')->middleware('throttle:6,1,bus-web-register');
    Route::get('register/code', [$auth, 'showCode'])->name('register.code');
    Route::post('register/code', [$auth, 'verifyCode'])->name('register.verify')->middleware('throttle:10,1,bus-web-code');
    Route::post('logout', [$auth, 'logout'])->name('logout');

    Route::middleware($signedIn)->group(function () use ($book) {
        Route::get('d/{id}/travellers', [$book, 'travellers'])->name('travellers')->whereUuid('id');
        Route::post('d/{id}/request', [$book, 'request'])->name('request')->whereUuid('id')->middleware('throttle:10,1,bus-web-request');
        Route::get('trips', [$book, 'trips'])->name('trips');
        Route::get('t/{code}', [$book, 'show'])->name('booking')->where('code', '[A-Z0-9]{6,12}');
        Route::get('t/{code}/status', [$book, 'status'])->name('booking.status')->where('code', '[A-Z0-9]{6,12}')->middleware('throttle:40,1,bus-web-status');
        Route::get('t/{code}/print', [$book, 'print'])->name('booking.print')->where('code', '[A-Z0-9]{6,12}');
        Route::post('t/{code}/pay', [$book, 'pay'])->name('booking.pay')->where('code', '[A-Z0-9]{6,12}')->middleware('throttle:10,1,bus-web-pay');
        Route::post('t/{code}/cancel', [$book, 'cancel'])->name('booking.cancel')->where('code', '[A-Z0-9]{6,12}');
    });

    // Meta's WhatsApp webhook (delivery statuses of the messages sent to website travellers).
    $wa = \Modules\BusManagement\Http\Controllers\Web\Site\WhatsAppWebhookController::class;
    Route::get('whatsapp/webhook', [$wa, 'verify'])->name('whatsapp.verify')->middleware('throttle:30,1,bus-wa');
    Route::post('whatsapp/webhook', [$wa, 'receive'])->name('whatsapp.webhook')
        ->withoutMiddleware([VerifyCsrfToken::class])->middleware('throttle:300,1,bus-wa');
});

// E-Ride admin (sidebar "Bus agencies").
Route::group(['prefix' => 'admin/bus', 'as' => 'admin.bus.', 'middleware' => 'admin'], function () {
    Route::get('/', [BusAdminController::class, 'index'])->name('index');
    Route::post('start', [BusAdminController::class, 'start'])->name('start');
    Route::post('settings', [BusAdminController::class, 'settings'])->name('settings');
    Route::post('web-settings', [BusAdminController::class, 'webSettings'])->name('web-settings');
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

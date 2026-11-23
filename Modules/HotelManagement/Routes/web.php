<?php

use Illuminate\Support\Facades\Route;
use Modules\HotelManagement\Http\Controllers\Web\Admin\HotelAdminController;
use Modules\HotelManagement\Http\Controllers\Web\Partner\AuthController;
use Modules\HotelManagement\Http\Controllers\Web\Partner\InboxController;
use Modules\HotelManagement\Http\Controllers\Web\Partner\NightsController;
use Modules\HotelManagement\Http\Controllers\Web\Partner\RoomsController;
use Modules\HotelManagement\Http\Controllers\Web\Partner\SetupController;
use Modules\HotelManagement\Http\Controllers\Web\Partner\StaffController;
use Modules\HotelManagement\Http\Middleware\HotelPartner;

/*
| E-Ride hotel partners. The hotels' portal is a phone-first site under /partner/hotel,
| separate from the Food & Shops partner portal (/partner) and the bus agencies' one.
*/

Route::group(['prefix' => 'partner/hotel', 'as' => 'hotel-partner.'], function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:10,1,hotel-login');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/', fn() => redirect()->route('hotel-partner.login'))->name('home');

    // Everyone at the hotel.
    Route::middleware(HotelPartner::class)->group(function () {
        Route::get('rooms', [RoomsController::class, 'index'])->name('rooms');
        Route::get('feed', [RoomsController::class, 'feed'])->name('feed');
        Route::post('rooms/{room}/state', [RoomsController::class, 'state'])->name('rooms.state')->whereUuid('room')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);
        Route::post('rooms/{room}/walk-in', [RoomsController::class, 'walkIn'])->name('rooms.walk-in')->whereUuid('room')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);
        Route::post('stays/{stay}/arrive', [RoomsController::class, 'arrive'])->name('stays.arrive')->whereUuid('stay')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);
        Route::post('stays/{stay}/no-show', [RoomsController::class, 'noShow'])->name('stays.no-show')->whereUuid('stay')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);
        Route::post('stays/{stay}/leave', [RoomsController::class, 'leave'])->name('stays.leave')->whereUuid('stay')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);
        Route::post('stays/{stay}/cancel', [RoomsController::class, 'cancel'])->name('stays.cancel')->whereUuid('stay')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);

        Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
        Route::get('inbox/{inquiry}/messages', [InboxController::class, 'messages'])->name('inbox.messages')->whereUuid('inquiry');
        Route::post('inbox/{inquiry}/send', [InboxController::class, 'send'])->name('inbox.send')->whereUuid('inquiry')->middleware('throttle:60,1,hotel-send');
        Route::post('inbox/{inquiry}/price', [InboxController::class, 'price'])->name('inbox.price')->whereUuid('inquiry');
        Route::post('inbox/{inquiry}/keep', [InboxController::class, 'keep'])->name('inbox.keep')->whereUuid('inquiry');
        Route::post('inbox/{inquiry}/close', [InboxController::class, 'close'])->name('inbox.close')->whereUuid('inquiry');

        Route::get('nights', [NightsController::class, 'index'])->name('nights');
        Route::post('nights/book', [NightsController::class, 'book'])->name('nights.book')->middleware(\Modules\HotelManagement\Http\Middleware\HotelNotOnPos::class);
    });

    // The manager only.
    Route::middleware(HotelPartner::class . ':manager')->group(function () {
        Route::get('setup', [SetupController::class, 'index'])->name('setup');
        // POS Innova: the key, and its room types mapped to E-Ride ones (docs/posinnova-hotel-contract.md).
        Route::get('pos', [\Modules\HotelManagement\Http\Controllers\Web\Partner\PosController::class, 'index'])->name('pos');
        Route::post('pos/link', [\Modules\HotelManagement\Http\Controllers\Web\Partner\PosController::class, 'createLink'])->name('pos.link')->middleware('throttle:10,1,hotel-pos-link');
        Route::post('pos/revoke', [\Modules\HotelManagement\Http\Controllers\Web\Partner\PosController::class, 'revoke'])->name('pos.revoke');
        Route::post('pos/types/{type}/map', [\Modules\HotelManagement\Http\Controllers\Web\Partner\PosController::class, 'map'])->name('pos.map')->whereUuid('type');
        Route::post('pos/types/{type}/create', [\Modules\HotelManagement\Http\Controllers\Web\Partner\PosController::class, 'createType'])->name('pos.create')->whereUuid('type');
        Route::post('setup/types', [SetupController::class, 'saveType'])->name('setup.types.store');
        Route::post('setup/types/{type}', [SetupController::class, 'saveType'])->name('setup.types.update')->whereUuid('type');
        Route::post('setup/types/{type}/remove', [SetupController::class, 'removeType'])->name('setup.types.remove')->whereUuid('type');
        Route::post('setup/types/{type}/rooms', [SetupController::class, 'addRooms'])->name('setup.rooms.add')->whereUuid('type');
        Route::post('setup/rooms/{room}/remove', [SetupController::class, 'removeRoom'])->name('setup.rooms.remove')->whereUuid('room');
        Route::post('setup/times', [SetupController::class, 'saveTimes'])->name('setup.times');

        Route::get('staff', [StaffController::class, 'index'])->name('staff');
        Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle')->whereNumber('staff');
        Route::post('staff/{staff}/password', [StaffController::class, 'password'])->name('staff.password')->whereNumber('staff');
    });
});

// E-Ride admin (sidebar "Hotel partners").
Route::group(['prefix' => 'admin/hotel-partners', 'as' => 'admin.hotel.', 'middleware' => 'admin'], function () {
    Route::get('/', [HotelAdminController::class, 'index'])->name('index');
    Route::post('/', [HotelAdminController::class, 'store'])->name('store');
    Route::post('settings', [HotelAdminController::class, 'settings'])->name('settings');
    Route::post('{hotel}/pause', [HotelAdminController::class, 'pause'])->name('pause')->whereUuid('hotel');
});

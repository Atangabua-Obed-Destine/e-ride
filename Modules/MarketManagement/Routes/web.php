<?php

use Illuminate\Support\Facades\Route;
use Modules\MarketManagement\Http\Controllers\Web\Admin\MarketAdminController;
use Modules\MarketManagement\Http\Controllers\Web\MarketPageController;

/*
| Public pages people open from WhatsApp: a shop, an item. No login.
*/
Route::middleware('throttle:120,1,market-page')->group(function () {
    Route::get('s/{slug}', [MarketPageController::class, 'shop'])->where('slug', '[a-z0-9-]+')->name('market.shop');
    Route::get('i/{code}', [MarketPageController::class, 'item'])->where('code', '[a-z0-9]+')->name('market.item');
    Route::get('i/{code}/app', [MarketPageController::class, 'getApp'])->where('code', '[a-z0-9]+')->name('market.get-app');
    Route::get('.well-known/assetlinks.json', [MarketPageController::class, 'assetLinks']);
});

/*
| Admin panel, sidebar "E-Ride Market".
*/
Route::group(['prefix' => 'admin/market', 'as' => 'admin.market.', 'middleware' => 'admin'], function () {
    Route::middleware('can:place_view')->group(function () {
        Route::get('/', [MarketAdminController::class, 'moderation'])->name('moderation');
        Route::get('shops', [MarketAdminController::class, 'shops'])->name('shops');
        Route::get('settings', [MarketAdminController::class, 'settings'])->name('settings');
    });
    Route::middleware('can:place_edit')->group(function () {
        Route::post('items/{item}/decide', [MarketAdminController::class, 'decide'])->name('decide');
        Route::post('shops/{shop}/decide', [MarketAdminController::class, 'decideShop'])->name('decide-shop');
        Route::post('shops/{shop}/verify', [MarketAdminController::class, 'verify'])->name('verify');
        Route::post('shops/{shop}/reopen', [MarketAdminController::class, 'reopen'])->name('reopen');
        Route::post('settings', [MarketAdminController::class, 'saveSettings'])->name('settings.save');
    });
});

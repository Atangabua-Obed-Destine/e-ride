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
        Route::get('orders', [MarketAdminController::class, 'orders'])->name('orders');
        Route::get('auctions', [MarketAdminController::class, 'auctions'])->name('auctions');
        Route::get('services', [MarketAdminController::class, 'services'])->name('services');
    });
    Route::middleware('can:place_edit')->group(function () {
        Route::post('items/{item}/decide', [MarketAdminController::class, 'decide'])->name('decide');
        Route::post('shops/{shop}/decide', [MarketAdminController::class, 'decideShop'])->name('decide-shop');
        Route::post('shops/{shop}/verify', [MarketAdminController::class, 'verify'])->name('verify');
        Route::post('shops/{shop}/reopen', [MarketAdminController::class, 'reopen'])->name('reopen');
        Route::post('settings', [MarketAdminController::class, 'saveSettings'])->name('settings.save');
        Route::post('order-rules', [MarketAdminController::class, 'saveOrderRules'])->name('order-rules');
        Route::post('auction-rules', [MarketAdminController::class, 'saveAuctionRules'])->name('auction-rules');
        Route::post('service-rules', [MarketAdminController::class, 'saveServiceRules'])->name('service-rules');
        Route::post('reviews/{review}/hide', [MarketAdminController::class, 'hideReview'])->name('review-hide');
    });
});

/*
| E-Ride Jobs: the job page people open from WhatsApp, "get the app", and the CV behind a signed link.
*/
Route::middleware('throttle:120,1,jobs-page')->group(function () {
    Route::get('j/{code}', [\Modules\MarketManagement\Http\Controllers\Web\JobPageController::class, 'job'])->where('code', '[a-z0-9]+')->name('jobs.page');
    Route::get('j/{code}/app', [\Modules\MarketManagement\Http\Controllers\Web\JobPageController::class, 'getApp'])->where('code', '[a-z0-9]+')->name('jobs.get-app');
    Route::get('jobs/cv/{card}', [\Modules\MarketManagement\Http\Controllers\Web\JobPageController::class, 'cv'])->middleware('signed')->name('jobs.cv');
});

/*
| Admin panel, sidebar "E-Ride Jobs".
*/
Route::group(['prefix' => 'admin/jobs', 'as' => 'admin.jobs.', 'middleware' => 'admin'], function () {
    Route::middleware('can:place_view')->group(function () {
        Route::get('/', [\Modules\MarketManagement\Http\Controllers\Web\Admin\JobAdminController::class, 'index'])->name('index');
        Route::get('employers', [\Modules\MarketManagement\Http\Controllers\Web\Admin\JobAdminController::class, 'employers'])->name('employers');
        Route::get('employers/{employer}/document', [\Modules\MarketManagement\Http\Controllers\Web\Admin\JobAdminController::class, 'document'])->name('document');
    });
    Route::middleware('can:place_edit')->group(function () {
        Route::post('{job}/decide', [\Modules\MarketManagement\Http\Controllers\Web\Admin\JobAdminController::class, 'decide'])->name('decide');
        Route::post('employers/{employer}/decide', [\Modules\MarketManagement\Http\Controllers\Web\Admin\JobAdminController::class, 'decideEmployer'])->name('decide-employer');
        Route::post('settings', [\Modules\MarketManagement\Http\Controllers\Web\Admin\JobAdminController::class, 'saveSettings'])->name('settings');
    });
});

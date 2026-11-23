<?php

use Illuminate\Support\Facades\Route;
use Modules\StoreManagement\Http\Controllers\Api\Customer\StoreOrderController;
use Modules\StoreManagement\Http\Controllers\Api\Driver\StoreTeamController;

/*
| Food & Shops, customer app.
*/
Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('stores', [StoreOrderController::class, 'stores']);
    Route::get('stores/search', [StoreOrderController::class, 'search'])->middleware('throttle:60,1,store-search');
    // A new rider who installed from a store's public link.
    Route::post('stores/referral', [StoreOrderController::class, 'referral'])->middleware('throttle:10,1,store-referral');
    // Resellers ("Sell for shops"): my links and earnings, joining, and a tap on a reseller's link.
    Route::get('store-resellers/me', [\Modules\StoreManagement\Http\Controllers\Api\Customer\StoreResellerController::class, 'me']);
    Route::post('store-resellers/join', [\Modules\StoreManagement\Http\Controllers\Api\Customer\StoreResellerController::class, 'join'])->middleware('throttle:10,1,store-reseller');
    Route::post('store-resellers/tap', [\Modules\StoreManagement\Http\Controllers\Api\Customer\StoreResellerController::class, 'tap'])->middleware('throttle:30,1,store-reseller-tap');
    Route::get('stores/{store}', [StoreOrderController::class, 'store']);
    Route::get('stores/{store}/branches', [StoreOrderController::class, 'branches']);
    Route::post('store-orders/quote', [StoreOrderController::class, 'quote'])->middleware('throttle:30,1,store-quote');
    Route::post('store-orders', [StoreOrderController::class, 'place'])->middleware('throttle:10,1,store-order');
    Route::get('store-orders', [StoreOrderController::class, 'index']);
    Route::get('store-orders/attention', [StoreOrderController::class, 'attention']);
    Route::get('store-orders/{order}', [StoreOrderController::class, 'show']);
    Route::put('store-orders/{order}/cancel', [StoreOrderController::class, 'cancel']);
    Route::post('store-orders/{order}/payment', [StoreOrderController::class, 'payment'])->middleware('throttle:10,1,store-payment');
    Route::post('store-orders/{order}/changes', [StoreOrderController::class, 'changes'])->middleware('throttle:10,1,store-changes');
    Route::post('store-orders/{order}/review', [StoreOrderController::class, 'review'])->middleware('throttle:10,1,store-review');
    Route::post('store-orders/{order}/deliver', [StoreOrderController::class, 'deliver'])->middleware('throttle:15,1,store-deliver');
    Route::post('store-orders/{order}/keep-way', [StoreOrderController::class, 'keepWay']);
    // "The shop brings it": the customer has their order.
    Route::post('store-orders/{order}/received', [StoreOrderController::class, 'received'])->middleware('throttle:15,1,store-received');
    Route::get('store-orders/{order}/pay', [StoreOrderController::class, 'pay'])
        ->name('store-order.pay')
        ->withoutMiddleware('auth:api');
    Route::get('store-orders/{order}/pay-delivery', [StoreOrderController::class, 'payDelivery'])
        ->name('store-order.pay-delivery')
        ->withoutMiddleware('auth:api');
});

/*
| Driver app: the stores that chose this rider for their deliveries ("My riders").
*/
Route::group(['prefix' => 'driver', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('store-teams', [StoreTeamController::class, 'index']);
    Route::post('store-teams/{id}/leave', [StoreTeamController::class, 'leave'])->middleware('throttle:20,1,store-team');
});

/*
| POS Innova channel v1 (docs/posinnova-channel-contract.md). POS calls E-Ride with a branch key
| (Authorization: Bearer erk_...); three rate buckets per key so a big first sync never starves orders.
*/
Route::group(['prefix' => 'channel/posinnova/v1'], function () {
    $c = \Modules\StoreManagement\Http\Controllers\Api\Channel\PosInnovaController::class;
    Route::post('catalog/full', [$c, 'catalogFull'])->middleware('throttle:pos-catalog');
    Route::post('catalog/changes', [$c, 'catalogChanges'])->middleware('throttle:pos-catalog');
    Route::post('catalog/images', [$c, 'image'])->middleware('throttle:pos-images');
    Route::middleware('throttle:pos-orders')->group(function () use ($c) {
        Route::get('hello', [$c, 'hello']);
        Route::post('heartbeat', [$c, 'heartbeat']);
        Route::get('orders/pending', [$c, 'pending']);
        Route::get('orders/{id}', [$c, 'order'])->whereUuid('id');
        Route::post('orders/{id}/rung-up', [$c, 'rungUp'])->whereUuid('id');
        Route::post('orders/{id}/unrung', [$c, 'unrung'])->whereUuid('id');
        Route::post('orders/{id}/cancel-seen', [$c, 'cancelSeen'])->whereUuid('id');
    });
});

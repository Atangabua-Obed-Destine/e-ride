<?php

use Illuminate\Support\Facades\Route;
use Modules\MarketManagement\Http\Controllers\Api\MarketChatController;
use Modules\MarketManagement\Http\Controllers\Api\MarketController;
use Modules\MarketManagement\Http\Controllers\Api\MarketOrderController;
use Modules\MarketManagement\Http\Controllers\Api\MarketSellerController;

/*
| E-Ride Market, customer app. Shops and items are found by their public slug / code.
*/
Route::group(['prefix' => 'customer/market', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::middleware('throttle:120,1,market-read')->group(function () {
        Route::get('meta', [MarketController::class, 'meta']);
        Route::get('feed', [MarketController::class, 'feed']);
        Route::get('shops/{slug}', [MarketController::class, 'shop'])->where('slug', '[a-z0-9-]+');
        Route::get('items/{code}', [MarketController::class, 'item'])->where('code', '[a-z0-9]+');
        Route::get('shop', [MarketSellerController::class, 'myShop']);
        Route::get('chats', [MarketChatController::class, 'inbox']);
        Route::get('chats/{id}/messages', [MarketChatController::class, 'messages']);
        Route::get('orders', [MarketOrderController::class, 'index']);
        Route::get('orders/{id}', [MarketOrderController::class, 'show']);
        Route::get('shops/{slug}/reviews', [MarketOrderController::class, 'reviews'])->where('slug', '[a-z0-9-]+');
    });
    // Orders: the buyer sends a cart to a shop; the seller answers, sends and completes it.
    Route::middleware('throttle:60,1,market-orders')->group(function () {
        Route::post('orders', [MarketOrderController::class, 'place']);
        Route::post('orders/{id}/respond', [MarketOrderController::class, 'respond']);
        Route::post('orders/{id}/decline', [MarketOrderController::class, 'decline']);
        Route::post('orders/{id}/agree', [MarketOrderController::class, 'agree']);
        Route::post('orders/{id}/cancel', [MarketOrderController::class, 'cancel']);
        Route::post('orders/{id}/ready', [MarketOrderController::class, 'ready']);
        Route::post('orders/{id}/trip', [MarketOrderController::class, 'trip']);
        Route::post('orders/{id}/review', [MarketOrderController::class, 'review']);
        Route::post('shop/order-settings', [MarketSellerController::class, 'orderSettings']);
    });
    // Typing a receive code: few tries, so a code cannot be guessed.
    Route::post('orders/{id}/complete', [MarketOrderController::class, 'complete'])->middleware('throttle:8,1,market-code');
    Route::middleware('throttle:30,1,market-write')->group(function () {
        Route::post('shop', [MarketSellerController::class, 'saveShop']);
        Route::post('shop/verification', [MarketSellerController::class, 'requestVerification']);
        Route::post('items', [MarketSellerController::class, 'storeItem']);
        Route::post('items/{code}/update', [MarketSellerController::class, 'updateItem']);
        Route::post('items/{code}/sold-out', [MarketSellerController::class, 'soldOut']);
        Route::post('items/{code}/delete', [MarketSellerController::class, 'destroyItem']);
        Route::post('shops/{slug}/follow', [MarketController::class, 'follow']);
        Route::post('shops/{slug}/block', [MarketController::class, 'block']);
        Route::post('items/{code}/chat', [MarketChatController::class, 'start']);
        Route::post('chats/{id}/block-buyer', [MarketChatController::class, 'blockBuyer']);
        Route::post('referral', [MarketController::class, 'referral']);
    });
    Route::post('chats/{id}/messages', [MarketChatController::class, 'send'])->middleware('throttle:60,1,market-chat');
    Route::middleware('throttle:10,1,market-report')->group(function () {
        Route::post('items/{code}/report', [MarketController::class, 'reportItem']);
        Route::post('shops/{slug}/report', [MarketController::class, 'reportShop']);
    });
});

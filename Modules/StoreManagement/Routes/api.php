<?php

use Illuminate\Support\Facades\Route;
use Modules\StoreManagement\Http\Controllers\Api\Customer\StoreOrderController;

/*
| Food & Shops, customer app.
*/
Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('stores', [StoreOrderController::class, 'stores']);
    Route::get('stores/search', [StoreOrderController::class, 'search'])->middleware('throttle:60,1,store-search');
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
    Route::get('store-orders/{order}/pay', [StoreOrderController::class, 'pay'])
        ->name('store-order.pay')
        ->withoutMiddleware('auth:api');
    Route::get('store-orders/{order}/pay-delivery', [StoreOrderController::class, 'payDelivery'])
        ->name('store-order.pay-delivery')
        ->withoutMiddleware('auth:api');
});

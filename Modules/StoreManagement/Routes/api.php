<?php

use Illuminate\Support\Facades\Route;
use Modules\StoreManagement\Http\Controllers\Api\Customer\StoreOrderController;

/*
| Food & Shops, customer app.
*/
Route::group(['prefix' => 'customer', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
    Route::get('stores', [StoreOrderController::class, 'stores']);
    Route::get('stores/{store}', [StoreOrderController::class, 'store']);
    Route::post('store-orders/quote', [StoreOrderController::class, 'quote'])->middleware('throttle:30,1');
    Route::post('store-orders', [StoreOrderController::class, 'place'])->middleware('throttle:10,1');
    Route::get('store-orders', [StoreOrderController::class, 'index']);
    Route::get('store-orders/{order}', [StoreOrderController::class, 'show']);
    Route::put('store-orders/{order}/cancel', [StoreOrderController::class, 'cancel']);
    Route::post('store-orders/{order}/review', [StoreOrderController::class, 'review'])->middleware('throttle:10,1');
    Route::get('store-orders/{order}/pay', [StoreOrderController::class, 'pay'])
        ->name('store-order.pay')
        ->withoutMiddleware('auth:api');
});
